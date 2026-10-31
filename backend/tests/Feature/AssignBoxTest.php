<?php

namespace Tests\Feature;

use App\Domain\Study\PlanInstallation;
use App\Enums\MissionType;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Enums\StudyResult;
use App\Models\CustomerOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssignBoxTest extends TestCase
{
    use RefreshDatabase;

    private function user(Role $role, string $name): User
    {
        return User::create(['name' => $name, 'email' => strtolower(str_replace(' ', '', $name)).'@x.test', 'password' => 'secret123', 'role' => $role]);
    }

    private function connectable(string $ref = 'RAB1'): CustomerOrder
    {
        $order = CustomerOrder::create(['customer_ref' => $ref, 'name' => 'Koné Awa']);
        $order->changeStatus(OrderStatus::Studied, result: StudyResult::Feasible);

        return $order;
    }

    private function url(CustomerOrder $order): string
    {
        return "/api/v1/customer-orders/{$order->id}/box";
    }

    public function test_the_coordinator_assigns_the_ont_and_the_fixed_line_of_a_client_whose_study_is_done(): void
    {
        Sanctum::actingAs($this->user(Role::Coordinator, 'Coord'));
        $order = $this->connectable();

        $this->putJson($this->url($order), ['ont_serial' => 'HW@TC', 'fixed_line' => '2721234567'])->assertUnprocessable()->assertJsonValidationErrors('ont_serial');

        $this->putJson($this->url($order), ['ont_serial' => 'hwtc 1234-5678', 'fixed_line' => '27 21 23 45 67'])->assertOk()
            ->assertJsonPath('data.ont_serial', 'HWTC1234-5678')
            ->assertJsonPath('data.fixed_line', '2721234567')
            ->assertJsonPath('data.status.value', 'studied')
            ->assertJsonPath('data.history.1.observation', 'Box attribuée : SN ONT HWTC1234-5678, ligne fixe 2721234567');
    }

    public function test_the_box_can_be_corrected_until_the_client_is_installed(): void
    {
        Sanctum::actingAs($this->user(Role::Admin, 'Admin'));

        foreach ([OrderStatus::AppointmentSet, OrderStatus::Postponed, OrderStatus::Failed] as $i => $status) {
            $order = CustomerOrder::create(['customer_ref' => "R{$i}", 'name' => 'Yao']);
            $order->changeStatus($status);
            $this->putJson($this->url($order), ['ont_serial' => "SN{$i}", 'fixed_line' => "2721234{$i}00"])->assertOk()->assertJsonPath('data.status.value', $status->value);
            $this->putJson($this->url($order), ['ont_serial' => "SN{$i}B", 'fixed_line' => "2721234{$i}00"])->assertOk()->assertJsonPath('data.ont_serial', "SN{$i}B");
        }
    }

    public function test_only_a_client_whose_study_is_done_and_who_is_not_installed_can_get_a_box(): void
    {
        Sanctum::actingAs($this->user(Role::Coordinator, 'Coord'));
        $received = CustomerOrder::create(['customer_ref' => 'A', 'name' => 'A']);
        $notFeasible = CustomerOrder::create(['customer_ref' => 'B', 'name' => 'B']);
        $notFeasible->changeStatus(OrderStatus::Studied, result: StudyResult::NotFeasible);
        $installed = CustomerOrder::create(['customer_ref' => 'C', 'name' => 'C']);
        $installed->changeStatus(OrderStatus::Installed);

        foreach ([$received, $notFeasible, $installed] as $order) {
            $this->putJson($this->url($order), ['ont_serial' => 'SN1', 'fixed_line' => '2721234567'])->assertUnprocessable()->assertJsonValidationErrors('status');
        }
        $this->assertNull($received->fresh()->ont_serial);
    }

    public function test_a_serial_or_a_line_cannot_belong_to_two_clients_and_both_are_mandatory(): void
    {
        Sanctum::actingAs($this->user(Role::Coordinator, 'Coord'));
        $first = $this->connectable('R1');
        $second = $this->connectable('R2');
        $this->putJson($this->url($first), ['ont_serial' => 'SN1', 'fixed_line' => '2721234567'])->assertOk();

        $this->putJson($this->url($second), ['ont_serial' => 'SN1', 'fixed_line' => '2729999999'])->assertUnprocessable()->assertJsonValidationErrors('ont_serial');
        $this->putJson($this->url($second), ['ont_serial' => 'SN2', 'fixed_line' => '2721234567'])->assertUnprocessable()->assertJsonValidationErrors('fixed_line');
        $this->putJson($this->url($second), ['ont_serial' => 'SN2'])->assertUnprocessable()->assertJsonValidationErrors('fixed_line');
        $this->putJson($this->url($second), ['fixed_line' => '2729999999'])->assertUnprocessable()->assertJsonValidationErrors('ont_serial');
        $this->putJson($this->url($second), [])->assertUnprocessable()->assertJsonValidationErrors(['ont_serial', 'fixed_line']);
        $this->putJson($this->url($second), ['fixed_line' => '123'])->assertUnprocessable()->assertJsonValidationErrors('fixed_line');
        $this->putJson($this->url($first), ['ont_serial' => 'SN1', 'fixed_line' => '2721234567'])->assertOk(); // son propre numéro : pas un doublon
    }

    public function test_only_the_coordinator_and_the_administrator_assign_the_box(): void
    {
        $order = $this->connectable();
        $tech = $this->user(Role::Technician, 'Tech');
        $tech->skills()->create(['mission_type' => MissionType::CustomerConnection->value]);

        foreach ([$tech, $this->user(Role::Dg, 'DG'), $this->user(Role::Dt, 'DT'), $this->user(Role::Supervisor, 'Superviseur'), $this->user(Role::BeManager, 'BE')] as $other) {
            Sanctum::actingAs($other);
            $this->putJson($this->url($order), ['ont_serial' => 'SN1', 'fixed_line' => '2721234567'])->assertForbidden();
        }
        $this->assertNull($order->fresh()->ont_serial);
    }

    public function test_the_technician_sees_the_box_on_his_visit(): void
    {
        $tech = $this->user(Role::Technician, 'Tech');
        $tech->skills()->create(['mission_type' => MissionType::CustomerConnection->value]);
        $coordinator = $this->user(Role::Coordinator, 'Coord');
        $order = $this->connectable();
        app(PlanInstallation::class)($order, $tech, now(), $coordinator, 'SN-OLD', '2700000000');
        Sanctum::actingAs($coordinator);
        $this->putJson($this->url($order), ['ont_serial' => 'SN9', 'fixed_line' => '2721234567'])->assertOk();

        Sanctum::actingAs($tech);
        $this->getJson('/api/v1/my-visits')->assertOk()
            ->assertJsonPath('data.0.order.ont_serial', 'SN9')->assertJsonPath('data.0.order.fixed_line', '2721234567');
    }
}
