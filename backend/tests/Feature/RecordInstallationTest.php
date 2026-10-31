<?php

namespace Tests\Feature;

use App\Domain\Study\PlanInstallation;
use App\Enums\InterventionStatus;
use App\Enums\MissionType;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Enums\StudyResult;
use App\Models\CustomerOrder;
use App\Models\Intervention;
use App\Models\OutcomeReason;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecordInstallationTest extends TestCase
{
    use RefreshDatabase;

    private function user(Role $role, string $name, ?MissionType $skill = null): User
    {
        $user = User::create(['name' => $name, 'email' => strtolower(str_replace(' ', '', $name)).'@x.test', 'password' => 'secret123', 'role' => $role]);
        if ($skill !== null) {
            $user->skills()->create(['mission_type' => $skill->value]);
        }

        return $user;
    }

    /** Commande « installation programmée » chez $tech. */
    private function scheduled(User $tech, string $ref = 'RAB1'): CustomerOrder
    {
        $order = CustomerOrder::create(['customer_ref' => $ref, 'name' => 'Koné Awa']);
        $order->changeStatus(OrderStatus::Studied, result: StudyResult::Feasible);
        app(PlanInstallation::class)($order, $tech, now(), $this->user(Role::Coordinator, "Coord {$ref}"), 'SN'.$ref, '27'.str_pad((string) crc32($ref), 8, '0'));

        return $order->fresh();
    }

    private function url(CustomerOrder $order): string
    {
        return "/api/v1/customer-orders/{$order->id}/installation-result";
    }

    private function reason(string $code = 'customer_absent'): OutcomeReason
    {
        return OutcomeReason::where('code', $code)->firstOrFail();
    }

    public function test_the_starting_list_of_reasons_is_available(): void
    {
        Sanctum::actingAs($this->user(Role::Coordinator, 'Coord'));

        $this->getJson('/api/v1/customer-orders/outcome-reasons')->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonFragment(['label' => 'Client absent'])->assertJsonFragment(['label' => 'Accès impossible'])
            ->assertJsonFragment(['label' => 'Câble ou poteau manquant'])->assertJsonFragment(['label' => 'PCO saturé']);

        Sanctum::actingAs($this->user(Role::Supervisor, 'Superviseur'));
        $this->getJson('/api/v1/customer-orders/outcome-reasons')->assertForbidden();
    }

    public function test_an_installed_client_moves_to_the_connected_list_and_keeps_the_box_given_by_the_coordinator(): void
    {
        $tech = $this->user(Role::Technician, 'Tech', MissionType::CustomerConnection);
        $order = $this->scheduled($tech);
        $order->forceFill(['ont_serial' => 'HWTC12345678', 'fixed_line' => '2721234567'])->save();
        Sanctum::actingAs($tech);

        $this->postJson($this->url($order), ['result' => 'installed', 'ont_serial' => 'AUTRE'])->assertOk()
            ->assertJsonPath('data.status.value', 'installed')
            ->assertJsonPath('data.stage', 'raccordes')
            ->assertJsonPath('data.interventions.0.status', 'done')
            ->assertJsonPath('data.interventions.0.outcome', 'installed');

        $this->assertSame(['HWTC12345678', '2721234567'], [$order->fresh()->ont_serial, $order->fresh()->fixed_line]);
    }

    public function test_a_postponement_needs_a_reason_and_keeps_the_client_connectable(): void
    {
        $tech = $this->user(Role::Technician, 'Tech', MissionType::CustomerConnection);
        $order = $this->scheduled($tech);
        Sanctum::actingAs($tech);

        $this->postJson($this->url($order), ['result' => 'postponed'])->assertUnprocessable()->assertJsonValidationErrors('reason_id');
        $this->assertSame(OrderStatus::AppointmentSet, $order->fresh()->status);

        $this->postJson($this->url($order), ['result' => 'postponed', 'reason_id' => $this->reason()->id, 'observation' => 'Repasser samedi'])->assertOk()
            ->assertJsonPath('data.status.value', 'postponed')
            ->assertJsonPath('data.stage', 'raccordables')
            ->assertJsonPath('data.interventions.0.reason', 'Client absent')
            ->assertJsonPath('data.history.2.observation', 'Client absent — Repasser samedi');

        $this->assertSame(InterventionStatus::Done, Intervention::first()->status);
    }

    public function test_a_failure_needs_an_active_reason(): void
    {
        $tech = $this->user(Role::Technician, 'Tech', MissionType::CustomerConnection);
        $order = $this->scheduled($tech);
        $inactive = OutcomeReason::create(['code' => 'old', 'label' => 'Ancien', 'is_active' => false]);
        Sanctum::actingAs($tech);

        $this->postJson($this->url($order), ['result' => 'failed', 'reason_id' => $inactive->id])->assertUnprocessable()->assertJsonValidationErrors('reason_id');
        $this->postJson($this->url($order), ['result' => 'failed', 'reason_id' => $this->reason('missing_material')->id])->assertOk()
            ->assertJsonPath('data.status.value', 'failed');
    }

    public function test_a_postponed_client_can_be_scheduled_again_and_the_list_shows_the_new_visit_and_the_past_reason(): void
    {
        $tech = $this->user(Role::Technician, 'Tech', MissionType::CustomerConnection);
        $order = $this->scheduled($tech);
        Sanctum::actingAs($tech);
        $this->postJson($this->url($order), ['result' => 'postponed', 'reason_id' => $this->reason('access_impossible')->id])->assertOk();

        Sanctum::actingAs($this->user(Role::Coordinator, 'Coord'));
        $this->getJson('/api/v1/customer-orders?stage=raccordables')->assertOk()
            ->assertJsonPath('data.0.status.value', 'postponed')
            ->assertJsonPath('data.0.interventions.0.reason', 'Accès impossible');

        $this->postJson("/api/v1/customer-orders/{$order->id}/installation", ['technician_id' => $tech->id, 'planned_date' => now()->addDay()->toDateString(), 'ont_serial' => 'SNRAB1', 'fixed_line' => '2721234567'])
            ->assertOk()->assertJsonPath('data.status.value', 'appointment_set');
        $this->assertSame(2, Intervention::count());
    }

    public function test_only_a_scheduled_installation_can_be_recorded_and_only_once(): void
    {
        $tech = $this->user(Role::Technician, 'Tech', MissionType::CustomerConnection);
        $order = $this->scheduled($tech);
        Sanctum::actingAs($tech);

        $this->postJson($this->url($order), ['result' => 'installed'])->assertOk();
        $this->postJson($this->url($order), ['result' => 'installed'])->assertForbidden(); // plus de visite planifiée pour ce technicien

        Sanctum::actingAs($this->user(Role::Coordinator, 'Coord'));
        $this->postJson($this->url($order), ['result' => 'installed'])->assertUnprocessable()->assertJsonValidationErrors('status');
        $received = CustomerOrder::create(['customer_ref' => 'R9', 'name' => 'Yao']);
        $this->postJson($this->url($received), ['result' => 'installed'])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->postJson($this->url($received), ['result' => 'activated'])->assertUnprocessable()->assertJsonValidationErrors('result');
    }

    public function test_who_may_record_an_installation(): void
    {
        $tech = $this->user(Role::Technician, 'Tech', MissionType::CustomerConnection);
        $order = $this->scheduled($tech);

        foreach ([
            $this->user(Role::Technician, 'Autre Tech', MissionType::CustomerConnection),
            $this->user(Role::Dg, 'DG'), $this->user(Role::Dt, 'DT'), $this->user(Role::Supervisor, 'Superviseur'), $this->user(Role::BeManager, 'BE'),
        ] as $other) {
            Sanctum::actingAs($other);
            $this->postJson($this->url($order), ['result' => 'installed'])->assertForbidden();
        }
        $this->assertSame(OrderStatus::AppointmentSet, $order->fresh()->status);

        Sanctum::actingAs($this->user(Role::Admin, 'Admin'));
        $this->postJson($this->url($order), ['result' => 'installed'])->assertOk();
    }
}
