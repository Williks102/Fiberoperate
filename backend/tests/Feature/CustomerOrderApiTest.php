<?php

namespace Tests\Feature;

use App\Enums\MissionType;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Enums\StudyResult;
use App\Models\CustomerOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerOrderApiTest extends TestCase
{
    use RefreshDatabase;

    private function login(Role $role, ?MissionType $skill = null): User
    {
        $user = User::create(['name' => 'Agent', 'email' => $role->value.($skill?->value ?? '').'@x.test', 'password' => 'secret123', 'role' => $role]);
        if ($skill !== null) {
            $user->skills()->create(['mission_type' => $skill->value]);
        }
        Sanctum::actingAs($user);

        return $user;
    }

    private function order(string $ref, string $name, OrderStatus $status = OrderStatus::Received, array $extra = []): CustomerOrder
    {
        $order = CustomerOrder::create(['customer_ref' => $ref, 'name' => $name] + $extra);
        if ($status !== OrderStatus::Received) {
            $order->changeStatus($status);
        }

        return $order;
    }

    public function test_coordinator_lists_orders_and_filters_by_status_and_search(): void
    {
        $this->login(Role::Coordinator);
        $this->order('RAB001', 'Koné Awa', extra: ['phone_1' => '+2250707123456']);
        $this->order('RAB002', 'Traoré Moussa', OrderStatus::InStudy);

        $this->getJson('/api/v1/customer-orders')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/customer-orders?status=in_study')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.customer_ref', 'RAB002')
            ->assertJsonPath('data.0.status.label', 'Étude programmée');
        $this->getJson('/api/v1/customer-orders?q=KONÉ')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/customer-orders?q=0707123456')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Koné Awa');
        $this->getJson('/api/v1/customer-orders?q=rab002')->assertJsonCount(1, 'data');
        // Un nom sans chiffre ne doit pas être pris pour un numéro de téléphone vide (qui correspondrait à tout le monde).
        $this->getJson('/api/v1/customer-orders?q=Traoré')->assertJsonCount(1, 'data')->assertJsonPath('data.0.customer_ref', 'RAB002');
        $this->getJson('/api/v1/customer-orders?q=Inconnu')->assertJsonCount(0, 'data');
    }

    public function test_stages_split_orders_into_instances_raccordables_and_raccordes(): void
    {
        $this->login(Role::Coordinator);
        $this->order('R1', 'Nouveau');
        $this->order('R2', 'Client R2', OrderStatus::InStudy);
        $absent = $this->order('R3', 'Absent');
        $absent->changeStatus(OrderStatus::Studied, result: StudyResult::CustomerAbsent);
        $ok = $this->order('R4', 'Raccordable');
        $ok->changeStatus(OrderStatus::Studied, result: StudyResult::Feasible);
        $this->order('R5', 'Programmé', OrderStatus::AppointmentSet);
        $this->order('R6', 'Reporté', OrderStatus::Postponed);
        $this->order('R7', 'Échec', OrderStatus::Failed);
        $this->order('R8', 'Installé', OrderStatus::Installed);
        $this->order('R9', 'Activé', OrderStatus::Activated);

        $refs = fn (string $stage) => collect($this->getJson("/api/v1/customer-orders?stage={$stage}&per_page=50")->assertOk()->json('data'))->pluck('customer_ref')->sort()->values()->all();

        $this->assertSame(['R1', 'R2', 'R3'], $refs('instances'));
        $this->assertSame(['R4', 'R5', 'R6', 'R7'], $refs('raccordables'));
        $this->assertSame(['R8', 'R9'], $refs('raccordes'));
        $this->assertSame([], $refs('inconnu'));

        // L'étape affichée sur chaque client est celle de la liste qui le contient.
        foreach (['instances', 'raccordables', 'raccordes'] as $stage) {
            $stages = collect($this->getJson("/api/v1/customer-orders?stage={$stage}&per_page=50")->json('data'))->pluck('stage')->unique()->all();
            $this->assertSame([$stage], $stages);
        }
    }

    public function test_detail_carries_the_status_history(): void
    {
        $this->login(Role::Dt);
        $order = $this->order('RAB003', 'Diallo', OrderStatus::InStudy);

        $this->getJson("/api/v1/customer-orders/{$order->id}")->assertOk()
            ->assertJsonPath('data.history.0.to', 'in_study')
            ->assertJsonPath('data.history.0.to_label', 'Étude programmée');
    }

    public function test_access_by_role_and_skill(): void
    {
        $order = $this->order('RAB004', 'Yao');

        foreach ([Role::Admin, Role::Dg, Role::Dt, Role::Coordinator] as $role) {
            $this->login($role);
            $this->getJson('/api/v1/customer-orders')->assertOk();
        }

        $this->login(Role::Technician, MissionType::Study);
        $this->getJson("/api/v1/customer-orders/{$order->id}")->assertOk();
        $this->login(Role::Technician, MissionType::CustomerConnection);
        $this->getJson('/api/v1/customer-orders')->assertOk();

        $this->login(Role::Technician, MissionType::Deployment);
        $this->getJson('/api/v1/customer-orders')->assertForbidden();
        $this->getJson("/api/v1/customer-orders/{$order->id}")->assertForbidden();
        $this->login(Role::Supervisor);
        $this->getJson('/api/v1/customer-orders')->assertForbidden();
        $this->login(Role::BeManager);
        $this->getJson('/api/v1/customer-orders')->assertForbidden();
    }

    public function test_orders_are_read_only(): void
    {
        $this->login(Role::Admin);
        $order = $this->order('RAB005', 'Soro');

        $this->postJson('/api/v1/customer-orders', [])->assertStatus(405);
        $this->deleteJson("/api/v1/customer-orders/{$order->id}")->assertStatus(405);
    }
}
