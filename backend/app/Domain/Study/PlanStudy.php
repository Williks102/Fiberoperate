<?php

namespace App\Domain\Study;

use App\Enums\InterventionStatus;
use App\Enums\MissionType;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\CustomerOrder;
use App\Models\Intervention;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mise en étude d'une commande : une intervention « étude » est planifiée chez un technicien habilité, et la commande passe
 * de « reçue » à « en étude » (avec sa ligne d'historique), dans une seule transaction sur la commande verrouillée.
 */
class PlanStudy
{
    public function __invoke(CustomerOrder $order, User $technician, CarbonInterface $date, User $by, ?string $observation = null): Intervention
    {
        if (! $technician->hasRole(Role::Technician) || ! $technician->is_active || ! $technician->hasSkill(MissionType::Study)) {
            throw ValidationException::withMessages(['technician_id' => "Ce technicien n'est pas habilité à réaliser des études."]);
        }

        return DB::transaction(function () use ($order, $technician, $date, $by, $observation) {
            $order = CustomerOrder::query()->lockForUpdate()->findOrFail($order->getKey());

            if ($order->status !== OrderStatus::Received) {
                throw ValidationException::withMessages(['status' => "Cette commande n'est plus à l'état « Reçue » : rechargez la page."]);
            }

            $intervention = Intervention::create([
                'type' => MissionType::Study,
                'customer_order_id' => $order->getKey(),
                'technician_id' => $technician->getKey(),
                'planned_date' => $date->toDateString(),
                'status' => InterventionStatus::Planned,
                'observation' => $observation,
                'created_by' => $by->getKey(),
            ]);

            $order->changeStatus(OrderStatus::InStudy, $by, "Étude confiée à {$technician->name} pour le {$date->format('d/m/Y')}");

            return $intervention;
        });
    }
}
