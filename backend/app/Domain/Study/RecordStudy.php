<?php

namespace App\Domain\Study;

use App\Enums\InterventionStatus;
use App\Enums\MissionType;
use App\Enums\OrderStatus;
use App\Enums\StudyResult;
use App\Models\CustomerOrder;
use App\Models\Intervention;
use App\Models\Pco;
use App\Models\Study;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Résultat d'une étude : l'étude est enregistrée, l'intervention passe « réalisée » et la commande « étudiée » (faisable, non
 * faisable ou client absent). Un client faisable est rattaché à son PCO, et la sous-plaque s'en déduit. La distance est celle
 * saisie, sinon poteaux × écart. Une seule transaction, sur la commande verrouillée.
 */
class RecordStudy
{
    /** @param array<string, mixed> $data */
    public function __invoke(CustomerOrder $order, User $by, array $data, ?CarbonInterface $at = null, ?string $device = null): Study
    {
        return DB::transaction(function () use ($order, $by, $data, $at, $device) {
            $order = CustomerOrder::query()->lockForUpdate()->findOrFail($order->getKey());

            $intervention = Intervention::query()
                ->where('customer_order_id', $order->getKey())->where('type', MissionType::Study->value)
                ->where('status', InterventionStatus::Planned->value)->first();

            if ($order->status !== OrderStatus::InStudy || $intervention === null) {
                throw ValidationException::withMessages(['status' => "Cette commande n'a pas d'étude en cours : rechargez la page."]);
            }

            $result = StudyResult::from($data['result']);
            $study = new Study(['result' => $result]);
            $study->forceFill(['intervention_id' => $intervention->getKey(), 'customer_order_id' => $order->getKey(), 'recorded_by' => $by->getKey()]);

            foreach (['concrete_poles_existing', 'concrete_poles_to_install', 'metal_poles_existing', 'metal_poles_to_install'] as $pole) {
                $study->{$pole} = $data[$pole] ?? 0;
            }
            foreach (['problem', 'field_comments', 'internal_comments'] as $text) {
                $study->{$text} = $data[$text] ?? null;
            }
            $study->field_latitude = $data['latitude'] ?? null;
            $study->field_longitude = $data['longitude'] ?? null;
            $study->distance_m = $data['distance_m'] ?? ($result === StudyResult::Feasible ? $study->countedPoles() * config('study.pole_spacing_m') : null);

            $pco = $result === StudyResult::Feasible ? Pco::query()->findOrFail($data['pco_id']) : null;
            $study->pco_id = $pco?->getKey();
            if ($at !== null) {
                $study->created_at = $at;
            }
            $study->save();

            $intervention->update(['status' => InterventionStatus::Done]);

            $order->forceFill([
                'pco_id' => $pco?->getKey() ?? $order->pco_id,
                'zone_id' => $pco?->zone_id ?? $order->zone_id,
                'distance_m' => $study->distance_m ?? $order->distance_m,
                'field_latitude' => $study->field_latitude ?? $order->field_latitude,
                'field_longitude' => $study->field_longitude ?? $order->field_longitude,
            ])->save();
            $order->changeStatus(OrderStatus::Studied, $by, $study->problem ?: $study->field_comments, $device, $result, $at);

            return $study;
        });
    }
}
