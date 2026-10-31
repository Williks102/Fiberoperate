<?php

namespace App\Domain\Study;

use App\Models\CustomerOrder;
use App\Models\FieldOperation;
use App\Models\User;
use App\Support\IvoryCoastBounds;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Traitement des opérations envoyées par le terrain (résultats d'étude et d'installation). Chaque opération est appliquée dans sa
 * propre transaction, dans l'ordre où elle a eu lieu, et reçoit une issue :
 * - `applied` : appliquée ;
 * - `duplicate` : déjà reçue (même `client_uuid`), on renvoie l'issue d'origine sans rien refaire ;
 * - `conflict` : la visite a changé depuis (déjà saisie, reprogrammée, confiée à un autre) : rien n'est appliqué, la saisie est gardée ;
 * - `rejected` : saisie inexploitable (champ manquant ou invalide) : rien n'est appliqué, la saisie est gardée.
 * Le serveur fait foi pour le planning, le terrain pour l'exécution : une saisie n'écrase jamais un état plus récent en silence.
 */
class ProcessFieldOperations
{
    public const TYPES = ['study.result', 'installation.result'];

    public function __construct(private readonly RecordStudy $recordStudy, private readonly RecordInstallation $recordInstallation) {}

    /**
     * @param  list<array<string, mixed>>  $operations
     * @return list<array<string, mixed>> une issue par opération, dans l'ordre reçu
     */
    public function __invoke(User $user, array $operations): array
    {
        $order = collect($operations)->keys()->sortBy(fn (int $i) => (string) ($operations[$i]['occurred_at'] ?? ''))->values();

        $results = [];
        foreach ($order as $index) {
            $results[$index] = $this->one($user, $operations[$index]);
        }
        ksort($results);

        return array_values($results);
    }

    /**
     * @param  array<string, mixed>  $op
     * @return array<string, mixed>
     */
    private function one(User $user, array $op): array
    {
        $envelope = Validator::make($op, [
            'client_uuid' => ['required', 'uuid'],
            'type' => ['required', 'in:'.implode(',', self::TYPES)],
            'customer_order_id' => ['required', 'uuid'],
            'occurred_at' => ['required', 'date'],
            'device' => ['nullable', 'string', 'max:60'],
            'payload' => ['required', 'array'],
        ]);
        if ($envelope->fails()) {
            return ['client_uuid' => is_string($op['client_uuid'] ?? null) ? $op['client_uuid'] : null, 'status' => 'rejected', 'error_code' => 'INVALID_OPERATION', 'message' => $envelope->errors()->first()];
        }

        $existing = FieldOperation::query()->where('client_uuid', $op['client_uuid'])->first();
        if ($existing !== null) {
            return $this->duplicate($user, $existing);
        }

        // Une saisie faite avant la synchronisation garde son heure, sans jamais dépasser le moment présent.
        $at = min(CarbonImmutable::parse($op['occurred_at']), CarbonImmutable::now());
        $isStudy = $op['type'] === 'study.result';

        [$failure, $data] = $this->check($user, $op, $isStudy);

        if ($failure === null) {
            try {
                // Enregistrée dans la même transaction que son application : jamais appliquée sans trace, jamais tracée sans effet.
                // Deux envois simultanés du même uuid : le second attend le premier, puis échoue sur l'unicité et devient « doublon ».
                DB::transaction(function () use ($user, $op, $at, $isStudy, $data) {
                    $this->record($user, $op, $at, 'applied', null, null);
                    $isStudy
                        ? ($this->recordStudy)(CustomerOrder::query()->findOrFail($op['customer_order_id']), $user, $data, $at, $op['device'] ?? null)
                        : ($this->recordInstallation)(CustomerOrder::query()->findOrFail($op['customer_order_id']), $user, $data, $at, $op['device'] ?? null);
                });

                return ['client_uuid' => $op['client_uuid'], 'status' => 'applied', 'error_code' => null, 'message' => null];
            } catch (UniqueConstraintViolationException) {
                return $this->duplicate($user, FieldOperation::query()->where('client_uuid', $op['client_uuid'])->firstOrFail());
            } catch (ValidationException $e) {
                // « status » : plus d'étude ou d'installation en cours pour cette commande (déjà saisie, annulée, reprogrammée).
                $failure = isset($e->errors()['status'])
                    ? ['conflict', 'VISIT_CHANGED', "Cette visite n'est plus à faire (déjà saisie, annulée ou reprogrammée) : votre saisie n'a pas été appliquée."]
                    : ['rejected', 'VALIDATION', (string) collect($e->errors())->flatten()->first()];
            }
        }

        // Conflit ou rejet : rien n'est appliqué, mais la saisie est gardée avec son motif.
        try {
            $this->record($user, $op, $at, ...$failure);
        } catch (UniqueConstraintViolationException) {
            return $this->duplicate($user, FieldOperation::query()->where('client_uuid', $op['client_uuid'])->firstOrFail());
        }

        return ['client_uuid' => $op['client_uuid'], 'status' => $failure[0], 'error_code' => $failure[1], 'message' => $failure[2]];
    }

    /** @param array<string, mixed> $op */
    private function record(User $user, array $op, CarbonImmutable $at, string $status, ?string $code, ?string $message): void
    {
        FieldOperation::create([
            'client_uuid' => $op['client_uuid'], 'user_id' => $user->getKey(), 'type' => $op['type'], 'customer_order_id' => $op['customer_order_id'],
            'payload' => $op['payload'], 'device' => $op['device'] ?? null, 'occurred_at' => $at,
            'status' => $status, 'error_code' => $code, 'message' => $message, 'processed_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function duplicate(User $user, FieldOperation $existing): array
    {
        if ($existing->user_id !== $user->getKey()) {
            return ['client_uuid' => $existing->client_uuid, 'status' => 'rejected', 'error_code' => 'INVALID_OPERATION', 'message' => 'Opération inconnue.'];
        }

        return ['client_uuid' => $existing->client_uuid, 'status' => 'duplicate', 'original_status' => $existing->status, 'error_code' => $existing->error_code, 'message' => $existing->message];
    }

    /**
     * Contrôle l'opération avant de l'appliquer : commande connue, visite encore la sienne, saisie valide.
     *
     * @param  array<string, mixed>  $op
     * @return array{0: ?array{0: string, 1: ?string, 2: ?string}, 1: array<string, mixed>} (échec éventuel : statut, code, message), saisie validée
     */
    private function check(User $user, array $op, bool $isStudy): array
    {
        $order = CustomerOrder::query()->find($op['customer_order_id']);
        if ($order === null) {
            return [['conflict', 'ORDER_NOT_FOUND', "Ce client n'existe plus : votre saisie n'a pas été appliquée."], []];
        }

        if (! Gate::forUser($user)->allows($isStudy ? 'recordStudy' : 'recordInstallation', $order)) {
            return [['conflict', 'NOT_ASSIGNED', "Cette visite n'est plus la vôtre (reprogrammée, confiée à un autre technicien ou déjà saisie) : votre saisie n'a pas été appliquée."], []];
        }

        // Photo obligatoire pour toute étude et toute installation saisie sur le terrain (décision du client) : la saisie doit déclarer
        // au moins une photo ; les photos elles-mêmes peuvent arriver après (envoi en arrière-plan, réseau faible).
        $photos = Validator::make($op['payload'], ['photo_uuids' => ['required', 'array', 'min:1', 'max:6'], 'photo_uuids.*' => ['uuid']], [
            'photo_uuids.required' => 'Une photo est obligatoire : ajoutez-en au moins une.',
            'photo_uuids.min' => 'Une photo est obligatoire : ajoutez-en au moins une.',
        ]);
        if ($photos->fails()) {
            return [['rejected', 'PHOTO_REQUIRED', $photos->errors()->first()], []];
        }

        $validator = Validator::make($op['payload'], $isStudy ? ResultRules::study() : ResultRules::installation(), $isStudy ? ResultRules::studyMessages() : ResultRules::installationMessages());
        $validator->after(function ($validator) use ($op, $isStudy) {
            $payload = $op['payload'];
            if ($isStudy && ! $validator->errors()->hasAny(['latitude', 'longitude']) && isset($payload['latitude'], $payload['longitude'])) {
                $problem = IvoryCoastBounds::problem((float) $payload['latitude'], (float) $payload['longitude']);
                if ($problem !== null) {
                    $validator->errors()->add('latitude', $problem);
                }
            }
        });
        if ($validator->fails()) {
            return [['rejected', 'VALIDATION', $validator->errors()->first()], []];
        }

        return [null, $validator->validated()];
    }
}
