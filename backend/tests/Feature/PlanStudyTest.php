<?php

namespace Tests\Feature;

use App\Enums\MissionType;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\CustomerOrder;
use App\Models\Intervention;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlanStudyTest extends TestCase
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

    private function url(CustomerOrder $order): string
    {
        return "/api/v1/customer-orders/{$order->id}/study";
    }

    public function test_coordinator_puts_an_order_in_study_with_an_able_technician(): void
    {
        Sanctum::actingAs($this->user(Role::Coordinator, 'Coord'));
        $tech = $this->user(Role::Technician, 'Kouamé Tech', MissionType::Study);
        $order = CustomerOrder::create(['customer_ref' => 'RAB1', 'name' => 'Koné']);
        $date = now()->addDays(2)->toDateString();

        $this->postJson($this->url($order), ['technician_id' => $tech->id, 'planned_date' => $date, 'observation' => 'Appeler avant'])
            ->assertOk()
            ->assertJsonPath('data.status.value', 'in_study')
            ->assertJsonPath('data.interventions.0.technician', 'Kouamé Tech')
            ->assertJsonPath('data.interventions.0.planned_date', $date)
            ->assertJsonPath('data.interventions.0.type', 'study')
            ->assertJsonPath('data.history.0.to', 'in_study');

        $this->assertSame(OrderStatus::InStudy, $order->fresh()->status);
        $this->assertSame(1, Intervention::count());
    }

    public function test_a_technician_without_the_study_skill_cannot_be_planned(): void
    {
        Sanctum::actingAs($this->user(Role::Admin, 'Admin'));
        $order = CustomerOrder::create(['customer_ref' => 'RAB2', 'name' => 'Yao']);
        $date = now()->addDay()->toDateString();

        foreach ([
            $this->user(Role::Technician, 'Sans Habilitation', MissionType::Deployment),
            $this->user(Role::Technician, 'Inactif', MissionType::Study, active: false),
            $this->user(Role::Supervisor, 'Superviseur', MissionType::Study),
        ] as $tech) {
            $this->postJson($this->url($order), ['technician_id' => $tech->id, 'planned_date' => $date])
                ->assertUnprocessable()->assertJsonValidationErrors('technician_id');
        }

        $this->assertSame(OrderStatus::Received, $order->fresh()->status);
        $this->assertSame(0, Intervention::count());
    }

    public function test_only_a_received_order_can_be_put_in_study_and_only_once(): void
    {
        Sanctum::actingAs($this->user(Role::Coordinator, 'Coord'));
        $tech = $this->user(Role::Technician, 'Tech', MissionType::Study);
        $payload = ['technician_id' => $tech->id, 'planned_date' => now()->toDateString()];
        $order = CustomerOrder::create(['customer_ref' => 'RAB3', 'name' => 'Soro']);

        $this->postJson($this->url($order), $payload)->assertOk();
        $this->postJson($this->url($order), $payload)->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertSame(1, Intervention::count());

        $installed = CustomerOrder::create(['customer_ref' => 'RAB4', 'name' => 'Bamba']);
        $installed->changeStatus(OrderStatus::Installed);
        $this->postJson($this->url($installed), $payload)->assertUnprocessable();
    }

    public function test_the_date_is_required_and_cannot_be_in_the_past(): void
    {
        Sanctum::actingAs($this->user(Role::Coordinator, 'Coord'));
        $tech = $this->user(Role::Technician, 'Tech', MissionType::Study);
        $order = CustomerOrder::create(['customer_ref' => 'RAB5', 'name' => 'Diallo']);

        $this->postJson($this->url($order), ['technician_id' => $tech->id, 'planned_date' => now()->subDay()->toDateString()])
            ->assertUnprocessable()->assertJsonValidationErrors('planned_date');
        $this->postJson($this->url($order), ['technician_id' => $tech->id])->assertUnprocessable()->assertJsonValidationErrors('planned_date');
    }

    public function test_only_coordinator_and_admin_can_plan_and_list_technicians(): void
    {
        $tech = $this->user(Role::Technician, 'Tech', MissionType::Study);
        $this->user(Role::Technician, 'Autre', MissionType::Deployment);
        $order = CustomerOrder::create(['customer_ref' => 'RAB6', 'name' => 'Traoré']);
        $payload = ['technician_id' => $tech->id, 'planned_date' => now()->toDateString()];

        foreach ([Role::Dg, Role::Dt, Role::Supervisor, Role::BeManager, Role::Technician] as $i => $role) {
            Sanctum::actingAs($this->user($role, "Autre {$i}", MissionType::Study));
            $this->postJson($this->url($order), $payload)->assertForbidden();
            $this->getJson('/api/v1/customer-orders/study-technicians')->assertForbidden();
        }
        $this->assertSame(OrderStatus::Received, $order->fresh()->status);

        Sanctum::actingAs($this->user(Role::Coordinator, 'Coord'));
        $this->getJson('/api/v1/customer-orders/study-technicians')->assertOk()
            ->assertJsonCount(2, 'data') // « Tech » et « Autre 4 » (technicien habilité étude)
            ->assertJsonMissingPath('data.0.email');
    }

    public function test_unknown_order_is_not_found(): void
    {
        Sanctum::actingAs($this->user(Role::Coordinator, 'Coord'));
        $tech = $this->user(Role::Technician, 'Tech', MissionType::Study);

        $this->postJson('/api/v1/customer-orders/'.fake()->uuid().'/study', ['technician_id' => $tech->id, 'planned_date' => now()->toDateString()])->assertNotFound();
    }
}
