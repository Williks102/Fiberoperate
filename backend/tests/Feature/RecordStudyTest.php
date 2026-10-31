<?php

namespace Tests\Feature;

use App\Domain\Study\PlanStudy;
use App\Enums\InterventionStatus;
use App\Enums\MissionType;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\CustomerOrder;
use App\Models\Intervention;
use App\Models\Jdv;
use App\Models\Pco;
use App\Models\Plaque;
use App\Models\Study;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecordStudyTest extends TestCase
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

    private function pco(string $zoneCode = 'FAY03'): Pco
    {
        $zone = Zone::firstOrCreate(['code' => $zoneCode], ['plaque_id' => Plaque::firstOrCreate(['name' => 'FAYA'])->id]);

        $jdv = Jdv::firstOrCreate(['zone_id' => $zone->id, 'code' => 'SPL001']);

        return Pco::create(['zone_id' => $zone->id, 'jdv_id' => $jdv->id, 'code' => "{$zoneCode}_PCO001", 'number' => 1]);
    }

    /** Commande « en étude » confiée à $tech. */
    private function inStudy(User $tech, string $ref = 'RAB1'): CustomerOrder
    {
        $order = CustomerOrder::create(['customer_ref' => $ref, 'name' => 'Koné Awa']);
        app(PlanStudy::class)($order, $tech, now(), $this->user(Role::Coordinator, "Coord {$ref}"));

        return $order->fresh();
    }

    private function url(CustomerOrder $order): string
    {
        return "/api/v1/customer-orders/{$order->id}/study-result";
    }

    public function test_feasible_study_links_the_pco_and_proposes_the_distance_from_the_poles(): void
    {
        $tech = $this->user(Role::Technician, 'Tech', MissionType::Study);
        $order = $this->inStudy($tech);
        $pco = $this->pco();
        Sanctum::actingAs($this->user(Role::Coordinator, 'Coordinatrice'));

        $this->postJson($this->url($order), [
            'result' => 'feasible', 'pco_id' => $pco->id,
            'concrete_poles_existing' => 2, 'concrete_poles_to_install' => 1, 'metal_poles_existing' => 1, 'metal_poles_to_install' => 0,
            'field_comments' => 'Accès par la ruelle', 'latitude' => 5.3, 'longitude' => -4.0,
        ])->assertOk()
            ->assertJsonPath('data.status.value', 'studied')
            ->assertJsonPath('data.study_result', 'feasible')
            ->assertJsonPath('data.distance_m', 200) // (2 + 1 + 1 + 0) poteaux × 50 m
            ->assertJsonPath('data.pco.code', 'FAY03_PCO001')
            ->assertJsonPath('data.zone.code', 'FAY03')
            ->assertJsonPath('data.studies.0.result', 'feasible')
            ->assertJsonPath('data.interventions.0.status', 'done');

        $this->assertSame(OrderStatus::Studied, $order->fresh()->status);
        $this->assertSame('5.3000000', (string) $order->fresh()->field_latitude);
        $this->assertSame(InterventionStatus::Done, Intervention::first()->status);
    }

    public function test_the_distance_entered_by_the_technician_wins_and_the_counted_types_are_configurable(): void
    {
        $tech = $this->user(Role::Technician, 'Tech', MissionType::Study);
        $pco = $this->pco();
        Sanctum::actingAs($tech);

        $manual = $this->inStudy($tech, 'RAB2');
        $this->postJson($this->url($manual), ['result' => 'feasible', 'pco_id' => $pco->id, 'concrete_poles_existing' => 3, 'distance_m' => 120])
            ->assertOk()->assertJsonPath('data.distance_m', 120);

        config(['study.distance_pole_types' => ['metal']]);
        $metalOnly = $this->inStudy($tech, 'RAB3');
        $this->postJson($this->url($metalOnly), ['result' => 'feasible', 'pco_id' => $pco->id, 'concrete_poles_existing' => 3, 'metal_poles_to_install' => 2])
            ->assertOk()->assertJsonPath('data.distance_m', 100);
    }

    public function test_not_feasible_needs_a_problem_and_keeps_the_order_in_instances_without_a_pco(): void
    {
        $tech = $this->user(Role::Technician, 'Tech', MissionType::Study);
        $order = $this->inStudy($tech);
        Sanctum::actingAs($tech);

        $this->postJson($this->url($order), ['result' => 'not_feasible'])->assertUnprocessable()->assertJsonValidationErrors('problem');
        $this->postJson($this->url($order), ['result' => 'not_feasible', 'problem' => 'Aucun poteau à moins de 400 m'])
            ->assertOk()->assertJsonPath('data.study_result', 'not_feasible')->assertJsonPath('data.stage', 'instances')->assertJsonPath('data.pco', null);
    }

    public function test_customer_absent_needs_nothing_more(): void
    {
        $tech = $this->user(Role::Technician, 'Tech', MissionType::Study);
        $order = $this->inStudy($tech);
        Sanctum::actingAs($tech);

        $this->postJson($this->url($order), ['result' => 'customer_absent'])->assertOk()->assertJsonPath('data.study_result', 'customer_absent');
    }

    public function test_feasible_needs_a_pco_and_valid_gps_and_poles(): void
    {
        $tech = $this->user(Role::Technician, 'Tech', MissionType::Study);
        $order = $this->inStudy($tech);
        $pco = $this->pco();
        Sanctum::actingAs($tech);

        $this->postJson($this->url($order), ['result' => 'feasible'])->assertUnprocessable()->assertJsonValidationErrors('pco_id');
        $this->postJson($this->url($order), ['result' => 'feasible', 'pco_id' => $pco->id, 'latitude' => -4.0, 'longitude' => 5.3])->assertUnprocessable()->assertJsonValidationErrors('latitude');
        $this->postJson($this->url($order), ['result' => 'feasible', 'pco_id' => $pco->id, 'metal_poles_existing' => -1])->assertUnprocessable()->assertJsonValidationErrors('metal_poles_existing');
        $this->assertSame(OrderStatus::InStudy, $order->fresh()->status);
        $this->assertSame(0, Study::count());
    }

    public function test_a_result_can_only_be_recorded_once_and_only_on_an_order_in_study(): void
    {
        $tech = $this->user(Role::Technician, 'Tech', MissionType::Study);
        $order = $this->inStudy($tech);
        Sanctum::actingAs($tech);

        $this->postJson($this->url($order), ['result' => 'customer_absent'])->assertOk();
        // Plus d'étude en cours : même le coordinateur est refusé, et le technicien ne peut plus rien saisir.
        $this->postJson($this->url($order), ['result' => 'customer_absent'])->assertForbidden();
        Sanctum::actingAs($this->user(Role::Coordinator, 'Coordinatrice'));
        $this->postJson($this->url($order), ['result' => 'customer_absent'])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertSame(1, Study::count());

        $received = CustomerOrder::create(['customer_ref' => 'RAB9', 'name' => 'Yao']);
        $this->postJson($this->url($received), ['result' => 'customer_absent'])->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_who_may_record_a_result(): void
    {
        $tech = $this->user(Role::Technician, 'Tech', MissionType::Study);
        $order = $this->inStudy($tech);
        $payload = ['result' => 'customer_absent'];

        foreach ([
            $this->user(Role::Technician, 'Autre Tech', MissionType::Study), // une étude confiée à un autre
            $this->user(Role::Dg, 'DG'), $this->user(Role::Dt, 'DT'), $this->user(Role::Supervisor, 'Superviseur'), $this->user(Role::BeManager, 'BE'),
        ] as $other) {
            Sanctum::actingAs($other);
            $this->postJson($this->url($order), $payload)->assertForbidden();
        }
        $this->assertSame(OrderStatus::InStudy, $order->fresh()->status);

        Sanctum::actingAs($this->user(Role::Admin, 'Admin'));
        $this->postJson($this->url($order), $payload)->assertOk();
    }

    public function test_internal_comments_are_hidden_from_technicians(): void
    {
        $tech = $this->user(Role::Technician, 'Tech', MissionType::Study);
        $order = $this->inStudy($tech);
        Sanctum::actingAs($tech);

        $this->postJson($this->url($order), ['result' => 'customer_absent', 'internal_comments' => 'À rappeler par le chef'])
            ->assertOk()->assertJsonMissingPath('data.studies.0.internal_comments');

        Sanctum::actingAs($this->user(Role::Coordinator, 'Coordinatrice'));
        $this->getJson("/api/v1/customer-orders/{$order->id}")->assertJsonPath('data.studies.0.internal_comments', 'À rappeler par le chef');
    }

    public function test_defaults_and_pco_choices(): void
    {
        $this->pco('FAY03');
        $this->pco('ABB01');
        Sanctum::actingAs($this->user(Role::Coordinator, 'Coordinatrice'));

        $this->getJson('/api/v1/customer-orders/study-defaults')->assertOk()
            ->assertJsonPath('data.pole_spacing_m', 50)->assertJsonPath('data.counted_pole_types', ['concrete', 'metal']);
        $this->getJson('/api/v1/customer-orders/study-pcos')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/customer-orders/study-pcos?q=ABB')->assertJsonCount(1, 'data')->assertJsonPath('data.0.zone', 'ABB01');

        Sanctum::actingAs($this->user(Role::Supervisor, 'Superviseur'));
        $this->getJson('/api/v1/customer-orders/study-pcos')->assertForbidden();
    }
}
