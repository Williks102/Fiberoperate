<?php

namespace App\Domain\Study;

use App\Enums\InterventionStatus;
use App\Enums\MissionType;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Enums\StudyResult;
use App\Models\CustomerOrder;
use App\Models\Intervention;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Programmation de l'installation : une intervention « raccordement client » est planifiée chez un technicien habilité, et la
 * commande passe « installation programmée » (avec sa ligne d'historique). Possible pour un client raccordable, un report ou
 * un échec ; une installation déjà programmée se reprogramme, avec un motif (l'ancienne intervention est annulée, jamais effacée).
 * Une seule transaction, sur la commande verrouillée.
 */
class PlanInstallation
{
    public function __invoke(CustomerOrder $order, User $technician, CarbonInterface $date, User $by, ?string $observation = null, ?string $reason = null, ?string $ontSerial = null, ?string $fixedLine = null): Intervention
    {
        if (! $technician->hasRole(Role::Technician) || ! $technician->is_active || ! $technician->hasSkill(MissionType::CustomerConnection)) {
            throw ValidationException::withMessages(['technician_id' => "Ce technicien n'est pas habilité au raccordement client."]);
        }

        return DB::transaction(function () use ($order, $technician, $date, $by, $observation, $reason, $ontSerial, $fixedLine) {
            $order = CustomerOrder::query()->lockForUpdate()->findOrFail($order->getKey());
            $reprogramming = $order->status === OrderStatus::AppointmentSet;

            $schedulable = $reprogramming
                || in_array($order->status, [OrderStatus::Postponed, OrderStatus::Failed], true)
                || ($order->status === OrderStatus::Studied && $order->study_result === StudyResult::Feasible);

            if (! $schedulable) {
                throw ValidationException::withMessages(['status' => "Cette commande n'est pas prête pour l'installation : rechargez la page."]);
            }
            if ($reprogramming && blank($reason)) {
                throw ValidationException::withMessages(['reason' => 'Indiquez le motif de la reprogrammation.']);
            }

            $previous = $order->interventions()->where('type', MissionType::CustomerConnection->value)->where('status', InterventionStatus::Planned->value)->first();
            $previous?->update(['status' => InterventionStatus::Cancelled, 'observation' => trim(($previous->observation ? $previous->observation.' · ' : '')."Annulée : {$reason}")]);

            $intervention = Intervention::create([
                'type' => MissionType::CustomerConnection,
                'customer_order_id' => $order->getKey(),
                'technician_id' => $technician->getKey(),
                'planned_date' => $date->toDateString(),
                'status' => InterventionStatus::Planned,
                'observation' => $observation,
                'created_by' => $by->getKey(),
            ]);

            // La box se donne en même temps que la programmation ; une valeur absente ne change pas celle déjà attribuée.
            $order->forceFill(['ont_serial' => $ontSerial ?? $order->ont_serial, 'fixed_line' => $fixedLine ?? $order->fixed_line])->save();
            $box = implode(', ', array_filter([$ontSerial ? "SN ONT {$ontSerial}" : null, $fixedLine ? "ligne fixe {$fixedLine}" : null]));

            $label = $reprogramming ? 'Installation reprogrammée' : 'Installation programmée';
            $order->changeStatus(
                OrderStatus::AppointmentSet,
                $by,
                "{$label} : {$technician->name} le {$date->format('d/m/Y')}".($reprogramming ? " (motif : {$reason})" : '').($box !== '' ? " · Box : {$box}" : ''),
            );

            return $intervention;
        });
    }
}
