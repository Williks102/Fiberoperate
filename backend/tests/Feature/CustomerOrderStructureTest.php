<?php

namespace Tests\Feature;

use App\Enums\InitialStudy;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Enums\StudyResult;
use App\Models\CustomerOrder;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class CustomerOrderStructureTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $attributes = []): CustomerOrder
    {
        return CustomerOrder::create($attributes + ['rab' => 'RAB0000000000001', 'name' => 'Koné Awa']);
    }

    public function test_an_order_starts_received_and_keeps_the_original_values_next_to_the_normalized_ones(): void
    {
        $order = $this->order([
            'phone_1' => '+2250707123456', 'phone_1_raw' => '707123456',
            'address_raw' => 'Quartier Résidentiel, près du marché', 'declared_latitude' => 5.36, 'declared_longitude' => -4.0083,
            'initial_study_raw' => 'SANS PCO ', 'initial_study' => InitialStudy::Done,
            'ont_serial' => 'ZTEGC1234567890A', 'fixed_line' => '2121234567', 'distance_m' => 150,
            'registered_at' => '2026-10-12', 'operator_line_status' => 'En cours de raccordement',
        ])->fresh();

        $this->assertSame(OrderStatus::Received, $order->status);
        $this->assertSame('707123456', $order->phone_1_raw);
        $this->assertSame(InitialStudy::Done, $order->initial_study);
        $this->assertSame('SANS PCO ', $order->initial_study_raw);
        $this->assertSame('5.3600000', $order->declared_latitude);
        $this->assertSame('2026-10-12', $order->registered_at->toDateString());
        $this->assertNull($order->operator_synced_at);
    }

    public function test_the_rab_is_unique_among_live_orders_and_reusable_after_a_soft_delete(): void
    {
        $first = $this->order();
        $first->delete();
        $this->order(['name' => 'Autre']);

        $this->expectException(QueryException::class);
        $this->order(['name' => 'Doublon']);
    }

    public function test_every_status_change_writes_a_history_line_and_the_status_is_not_mass_assignable(): void
    {
        $user = User::create(['name' => 'u', 'email' => 'c@x.test', 'password' => 'secret123', 'role' => Role::Coordinator]);
        $order = $this->order(['status' => 'activated']);
        $this->assertSame(OrderStatus::Received, $order->fresh()->status, 'le statut ne se fixe pas en masse');

        $order->changeStatus(OrderStatus::InStudy, $user);
        $order->changeStatus(OrderStatus::Studied, $user, 'Étude faite', 'tablette-3', StudyResult::Feasible);

        $history = $order->history()->get();
        $this->assertSame([OrderStatus::Received, OrderStatus::InStudy], [$history[0]->from_status, $history[0]->to_status]);
        $this->assertSame([OrderStatus::InStudy, OrderStatus::Studied], $history->map->to_status->all());
        $this->assertSame('tablette-3', $history[1]->device);
        $this->assertSame($user->id, $history[1]->changed_by);
        $this->assertSame(StudyResult::Feasible, $order->fresh()->study_result);
    }

    public function test_the_history_cannot_be_modified_or_deleted(): void
    {
        $order = $this->order();
        $line = $order->changeStatus(OrderStatus::InStudy);

        $this->expectException(LogicException::class);
        $line->update(['observation' => 'autre']);
    }

    public function test_the_operator_labels_of_the_initial_study_are_never_inverted(): void
    {
        $this->assertSame(InitialStudy::Done, InitialStudy::fromOperatorLabel('SANS PCO '));
        $this->assertSame(InitialStudy::Done, InitialStudy::fromOperatorLabel('sans pco'));
        $this->assertSame(InitialStudy::ToDo, InitialStudy::fromOperatorLabel(' Avec PCO'));
        $this->assertNull(InitialStudy::fromOperatorLabel('Peut-être'));
        $this->assertSame('SANS PCO', InitialStudy::Done->operatorLabel());
        $this->assertSame('AVEC PCO', InitialStudy::ToDo->operatorLabel());
    }
}
