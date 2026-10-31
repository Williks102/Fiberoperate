<?php

namespace App\Domain\Study;

use App\Enums\InterventionStatus;
use App\Enums\MissionType;
use App\Enums\OrderStatus;
use App\Models\CustomerOrder;
use App\Models\Intervention;
use App\Models\OutcomeReason;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Issue d'une visite d'installation : installé, reporté ou en échec. Un report ou un échec exige un motif du référentiel (client
 * absent, accès impossible…). La visite passe « réalisée » avec son issue ; la commande change de statut (avec sa ligne
 * d'historique) et peut être reprogrammée ensuite. Une seule transaction, sur la commande verrouillée.
 */
class RecordInstallation
{
    /** @param array{result: string, reason_id?: ?string, ont_serial?: ?string, fixed_line?: ?string, observation?: ?string} $data */
    public function __invoke(CustomerOrder $order, User $by, array $data): Intervention
    {
        $result = OrderStatus::from($data['result']);
        if (! in_array($result, [OrderStatus::Installed, OrderStatus::Postponed, OrderStatus::Failed], true)) {
            throw ValidationException::withMessages(['result' => 'Résultat inconnu : choisissez installé, reporté ou en échec.']);
        }

        return DB::transaction(function () use ($order, $by, $data, $result) {
            $order = CustomerOrder::query()->lockForUpdate()->findOrFail($order->getKey());

            $visit = Intervention::query()
                ->where('customer_order_id', $order->getKey())->where('type', MissionType::CustomerConnection->value)
                ->where('status', InterventionStatus::Planned->value)->first();

            if ($order->status !== OrderStatus::AppointmentSet || $visit === null) {
                throw ValidationException::withMessages(['status' => "Cette commande n'a pas d'installation programmée : rechargez la page."]);
            }

            $reason = null;
            if ($result !== OrderStatus::Installed) {
                $reason = OutcomeReason::query()->where('is_active', true)->find($data['reason_id'] ?? null);
                if ($reason === null) {
                    throw ValidationException::withMessages(['reason_id' => 'Choisissez le motif du '.($result === OrderStatus::Postponed ? 'report' : "l'échec").'.']);
                }
            }

            $visit->update([
                'status' => InterventionStatus::Done,
                'outcome' => $result->value,
                'reason_id' => $reason?->getKey(),
                'observation' => $data['observation'] ?? $visit->observation,
            ]);

            if ($result === OrderStatus::Installed) {
                $order->forceFill([
                    'ont_serial' => $data['ont_serial'] ?? $order->ont_serial,
                    'fixed_line' => $data['fixed_line'] ?? $order->fixed_line,
                ])->save();
            }

            $note = $reason ? $reason->label : null;
            if (! empty($data['observation'])) {
                $note = trim("{$note} — {$data['observation']}", ' —');
            }
            $order->changeStatus($result, $by, $note ?: null);

            return $visit;
        });
    }
}
