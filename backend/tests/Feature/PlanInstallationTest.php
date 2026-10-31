<?php

namespace Tests\Feature;

use App\Enums\InterventionStatus;
use App\Enums\MissionType;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Enums\StudyResult;
use App\Models\CustomerOrder;
use App\Models\Intervention;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlanInstallationTest extends TestCase
{
    use RefreshDatabase;

    private function user(Role $role, string $name, ?MissionType $skill = null, bool $active = true): User
    {
        $user = User::create(['name' => $name, 'email' => strtolower(str_replace(' ', '', $name)).'@x.test', 'password' => 'secret123', 'role' => $role, 'is_active' => $active]);
        if ($skill !== null) {
            $user->skills()->create(['mission_type' => $skill->value]);
        }

        return $user;
    }

    private function connectable(string $ref = 'RAB1'): CustomerOrder
    {
        $order = CustomerOrder::create(['customer_ref' => $ref, 'name' => 'Koné Awa']);
        $order->changeStatus(OrderStatus::Studied, result: StudyResult::Feasible);

        return $order;
    }

    private function url(CustomerOrder $order): string
    {
        return "/api/v1/customer-orders/{$order->id}/installation";
    }

    private function payload(User $tech, array $extra = []): array
    {
        static $n = 0;
        $n++;

        // La box (n° de série et numéro fixe) est obligatoire ; chaque appel en donne une différente (une box = un client).
        return $extra + ['technician_id' => $tech->id, 'planned_date' => now()->addDays(3)->toDateString(), 'ont_serial' => "SN{$n}", 'fixed_line' => sprintf('27%08d', $n)];
    }

    public function test_coordinator_schedules_the_installation_of_a_connectable_client(): void
    {
        Sanctum::actingAs($this->user(Role::Coordinator, 'Coord'));
        $tech = $this->user(Role::Technician, 'Équipe Raccordement', MissionType::CustomerConnection);
        $order = $this->connectable();

        $this->postJson($this->url($order), $this->payload($tech, ['observation' => 'Prévoir 2 connecteurs']))->assertOk()
            ->assertJsonPath('data.status.value', 'appointment_set')
            ->assertJsonPath('data.stage', 'raccordables')
            ->assertJsonPath('data.interventions.0.type', 'customer_connection')
            ->assertJsonPath('data.interventions.0.technician', 'Équipe Raccordement')
            ->assertJsonPath('data.history.1.to', 'appointment_set');

        $this->assertSame(OrderStatus::AppointmentSet, $order->fresh()->status);
    }

    public function test_a_postponed_or_failed_order_can_be_scheduled_again(): void
    {
        Sanctum::actingAs($this->user(Role::Coordinator, 'Coord'));
        $tech = $this->user(Role::Technician, 'Tech', MissionType::CustomerConnection);

        foreach ([OrderStatus::Postponed, OrderStatus::Failed] as $i => $status) {
            $order = CustomerOrder::create(['customer_ref' => "RAB{$i}", 'name' => 'Yao']);
            $order->changeStatus($status);
            $this->postJson($this->url($order), $this->payload($tech))->assertOk()->assertJsonPath('data.status.value', 'appointment_set');
        }
    }

    public function test_the_technician_must_be_active_and_able_to_connect_customers(): void
    {
        Sanctum::actingAs($this->user(Role::Admin, 'Admin'));
        $order = $this->connectable();

        foreach ([
            $this->user(Role::Technician, 'Études seulement', MissionType::Study),
            $this->user(Role::Technician, 'Inactif', MissionType::CustomerConnection, active: false),
            $this->user(Role::Supervisor, 'Superviseur', MissionType::CustomerConnection),
        ] as $tech) {
            $this->postJson($this->url($order), $this->payload($tech))->assertUnprocessable()->assertJsonValidationErrors('technician_id');
        }

        $this->assertSame(OrderStatus::Studied, $order->fresh()->status);
        $this->assertSame(0, Intervention::count());
    }

    public function test_only_a_client_ready_for_installation_can_be_scheduled(): void
    {
        Sanctum::actingAs($this->user(Role::Coordinator, 'Coord'));
        $tech = $this->user(Role::Technician, 'Tech', MissionType::CustomerConnection);

        $received = CustomerOrder::create(['customer_ref' => 'R1', 'name' => 'A']);
        $notFeasible = CustomerOrder::create(['customer_ref' => 'R2', 'name' => 'B']);
        $notFeasible->changeStatus(OrderStatus::Studied, result: StudyResult::NotFeasible);
        $installed = CustomerOrder::create(['customer_ref' => 'R3', 'name' => 'C']);
        $installed->changeStatus(OrderStatus::Installed);

        foreach ([$received, $notFeasible, $installed] as $order) {
            $this->postJson($this->url($order), $this->payload($tech))->assertUnprocessable()->assertJsonValidationErrors('status');
        }
        $this->assertSame(0, Intervention::count());
    }

    public function test_rescheduling_needs_a_reason_and_cancels_the_previous_visit_without_deleting_it(): void
    {
        Sanctum::actingAs($this->user(Role::Coordinator, 'Coord'));
        $first = $this->user(Role::Technician, 'Premier', MissionType::CustomerConnection);
        $second = $this->user(Role::Technician, 'Second', MissionType::CustomerConnection);
        $order = $this->connectable();
        $this->postJson($this->url($order), $this->payload($first))->assertOk();

        $this->postJson($this->url($order), $this->payload($second))->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->assertSame(1, Intervention::count());

        $this->postJson($this->url($order), $this->payload($second, ['reason' => 'Client indisponible']))->assertOk()
            ->assertJsonPath('data.status.value', 'appointment_set');

        $this->assertSame(2, Intervention::count());
        $this->assertSame(InterventionStatus::Cancelled, Intervention::where('technician_id', $first->id)->first()->status);
        $this->assertStringContainsString('Client indisponible', (string) Intervention::where('technician_id', $first->id)->first()->observation);
        $this->assertSame(1, Intervention::where('status', 'planned')->count());
        $this->assertSame('Second', CustomerOrder::first()->interventions()->where('status', 'planned')->first()->technician->name);
    }

    public function test_the_date_cannot_be_in_the_past(): void
    {
        Sanctum::actingAs($this->user(Role::Coordinator, 'Coord'));
        $tech = $this->user(Role::Technician, 'Tech', MissionType::CustomerConnection);

        $this->postJson($this->url($this->connectable()), ['technician_id' => $tech->id, 'planned_date' => now()->subDay()->toDateString()])
            ->assertUnprocessable()->assertJsonValidationErrors('planned_date');
    }

    public function test_only_coordinator_and_admin_can_schedule_and_list_the_technicians(): void
    {
        $tech = $this->user(Role::Technician, 'Tech', MissionType::CustomerConnection);
        $this->user(Role::Technician, 'Autre', MissionType::Study);
        $order = $this->connectable();

        foreach ([Role::Dg, Role::Dt, Role::Supervisor, Role::BeManager, Role::Technician] as $i => $role) {
            Sanctum::actingAs($this->user($role, "Autre {$i}", MissionType::CustomerConnection));
            $this->postJson($this->url($order), $this->payload($tech))->assertForbidden();
            $this->getJson('/api/v1/customer-orders/installation-technicians')->assertForbidden();
        }
        $this->assertSame(OrderStatus::Studied, $order->fresh()->status);

        Sanctum::actingAs($this->user(Role::Coordinator, 'Coord'));
        $this->getJson('/api/v1/customer-orders/installation-technicians')->assertOk()->assertJsonCount(2, 'data')->assertJsonMissingPath('data.0.email');
    }

    public function test_the_list_shows_the_planned_installation(): void
    {
        Sanctum::actingAs($this->user(Role::Coordinator, 'Coord'));
        $tech = $this->user(Role::Technician, 'Tech Raccordement', MissionType::CustomerConnection);
        $order = $this->connectable();
        $this->postJson($this->url($order), $this->payload($tech))->assertOk();

        $this->getJson('/api/v1/customer-orders?stage=raccordables')->assertOk()
            ->assertJsonPath('data.0.interventions.0.technician', 'Tech Raccordement')
            ->assertJsonPath('data.0.interventions.0.status', 'planned');
    }

    public function test_the_box_is_given_at_the_same_time_as_the_installation_is_scheduled(): void
    {
        Sanctum::actingAs($this->user(Role::Coordinator, 'Coord'));
        $tech = $this->user(Role::Technician, 'Tech', MissionType::CustomerConnection);
        $order = $this->connectable();

        $this->postJson($this->url($order), $this->payload($tech, ['ont_serial' => 'hwtc 1234', 'fixed_line' => '27 21 23 45 67']))->assertOk()
            ->assertJsonPath('data.status.value', 'appointment_set')
            ->assertJsonPath('data.ont_serial', 'HWTC1234')
            ->assertJsonPath('data.fixed_line', '2721234567')
            ->assertJsonCount(2, 'data.history'); // une seule ligne pour la programmation et la box

        $note = $order->fresh()->history()->get()->last()->observation;
        $this->assertStringContainsString('Installation programmée : Tech', $note);
        $this->assertStringContainsString('Box : SN ONT HWTC1234, ligne fixe 2721234567', $note);
    }

    public function test_the_box_is_mandatory_unique_and_well_formed(): void
    {
        Sanctum::actingAs($this->user(Role::Coordinator, 'Coord'));
        $tech = $this->user(Role::Technician, 'Tech', MissionType::CustomerConnection);
        $taken = $this->connectable('R0');
        $taken->forceFill(['ont_serial' => 'SN0', 'fixed_line' => '2721111111'])->save();
        $order = $this->connectable('R1');
        $date = now()->addDay()->toDateString();

        $this->postJson($this->url($order), ['technician_id' => $tech->id, 'planned_date' => $date])->assertUnprocessable()->assertJsonValidationErrors(['ont_serial', 'fixed_line']);
        $this->postJson($this->url($order), ['technician_id' => $tech->id, 'planned_date' => $date, 'ont_serial' => 'SN1'])->assertUnprocessable()->assertJsonValidationErrors('fixed_line');
        $this->postJson($this->url($order), ['technician_id' => $tech->id, 'planned_date' => $date, 'fixed_line' => '2721234567'])->assertUnprocessable()->assertJsonValidationErrors('ont_serial');
        $this->postJson($this->url($order), $this->payload($tech, ['ont_serial' => 'SN0']))->assertUnprocessable()->assertJsonValidationErrors('ont_serial');
        $this->postJson($this->url($order), $this->payload($tech, ['fixed_line' => '2721111111']))->assertUnprocessable()->assertJsonValidationErrors('fixed_line');
        $this->postJson($this->url($order), $this->payload($tech, ['fixed_line' => '12']))->assertUnprocessable()->assertJsonValidationErrors('fixed_line');

        $this->assertSame(OrderStatus::Studied, $order->fresh()->status);
        $this->assertSame(0, Intervention::count());
    }

    public function test_rescheduling_keeps_the_same_box_without_clashing_with_itself(): void
    {
        Sanctum::actingAs($this->user(Role::Coordinator, 'Coord'));
        $tech = $this->user(Role::Technician, 'Tech', MissionType::CustomerConnection);
        $order = $this->connectable();
        $box = ['ont_serial' => 'SN77', 'fixed_line' => '2721234567'];
        $this->postJson($this->url($order), $this->payload($tech, $box))->assertOk();

        $this->postJson($this->url($order), $this->payload($tech, $box + ['reason' => 'Client absent']))->assertOk()
            ->assertJsonPath('data.ont_serial', 'SN77');
    }
}
