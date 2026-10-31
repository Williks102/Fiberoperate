<?php

namespace App\Domain\Study;

use App\Enums\OrderStatus;
use App\Enums\StudyResult;
use App\Models\CustomerOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Attribution de la box d'un client en raccordement (étude faite, avant ou pendant l'installation) : le coordinateur lui donne le
 * numéro de série de l'ONT et le numéro fixe. Modifiable tant que le client n'est pas installé ; chaque attribution écrit une
 * ligne d'historique. Une seule transaction, sur la commande verrouillée.
 */
class AssignBox
{
    public function __invoke(CustomerOrder $order, User $by, string $ontSerial, string $fixedLine): CustomerOrder
    {
        return DB::transaction(function () use ($order, $by, $ontSerial, $fixedLine) {
            $order = CustomerOrder::query()->lockForUpdate()->findOrFail($order->getKey());

            $inConnection = in_array($order->status, [OrderStatus::AppointmentSet, OrderStatus::Postponed, OrderStatus::Failed], true)
                || ($order->status === OrderStatus::Studied && $order->study_result === StudyResult::Feasible);

            if (! $inConnection) {
                throw ValidationException::withMessages(['status' => "La box s'attribue à un client dont l'étude est faite et qui n'est pas encore installé : rechargez la page."]);
            }

            $order->forceFill(['ont_serial' => $ontSerial, 'fixed_line' => $fixedLine])->save();

            $order->changeStatus($order->status, $by, "Box attribuée : SN ONT {$ontSerial}, ligne fixe {$fixedLine}");

            return $order;
        });
    }
}
