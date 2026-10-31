<?php

namespace Tests\Feature;

use App\Domain\Study\PlanInstallation;
use App\Domain\Study\PlanStudy;
use App\Enums\MissionType;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Enums\StudyResult;
use App\Models\CustomerOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MyVisitsTest extends TestCase
{
    use RefreshDatabase;

    private function user(Role $role, string $name, MissionType ...$skills): User
    {
        $user = User::create(['name' => $name, 'email' => strtolower(str_replace(' ', '', $name)).'@x.test', 'password' => 'secret123', 'role' => $role]);
        foreach ($skills as $skill) {
            $user->skills()->create(['mission_type' => $skill->value]);
        }

        return $user;
    }

    private function toStudy(User $tech, string $ref, string $date): void
    {
        $order = CustomerOrder::create(['customer_ref' => $ref, 'name' => "Client {$ref}", 'phone_1' => '+2250707123456', 'address_raw' => 'Cité Verte']);
        app(PlanStudy::class)($order, $tech, now()->parse($date), $this->user(Role::Coordinator, "Coord {$ref}"));
    }

    private function toInstall(User $tech, string $ref, string $date): void
    {
        $order = CustomerOrder::create(['customer_ref' => $ref, 'name' => "Client {$ref}"]);
        $order->changeStatus(OrderStatus::Studied, result: StudyResult::Feasible);
        app(PlanInstallation::class)($order, $tech, now()->parse($date), $this->user(Role::Coordinator, "Coord {$ref}"), "SN{$ref}", '27'.str_pad((string) crc32($ref), 8, '0'));
    }

    public function test_a_technician_sees_only_their_own_planned_visits_oldest_first(): void
    {
        $me = $this->user(Role::Technician, 'Moi', MissionType::Study, MissionType::CustomerConnection);
        $other = $this->user(Role::Technician, 'Autre', MissionType::Study, MissionType::CustomerConnection);
        $this->toInstall($me, 'R2', now()->addDays(3)->toDateString());
        $this->toStudy($me, 'R1', now()->addDay()->toDateString());
        $this->toStudy($other, 'R3', now()->toDateString());
        Sanctum::actingAs($me);

        $response = $this->getJson('/api/v1/my-visits')->assertOk()->assertJsonCount(2, 'data');
        $this->assertSame(['R1', 'R2'], collect($response->json('data'))->pluck('order.customer_ref')->all());
        $response->assertJsonPath('data.0.type', 'study')
            ->assertJsonPath('data.0.order.phone_1', '+2250707123456')
            ->assertJsonPath('data.0.order.address_raw', 'Cité Verte')
            ->assertJsonPath('data.1.type', 'customer_connection');
    }

    public function test_a_visit_that_is_done_or_replaced_is_no_longer_listed(): void
    {
        $me = $this->user(Role::Technician, 'Moi', MissionType::Study);
        $this->toStudy($me, 'R1', now()->toDateString());
        Sanctum::actingAs($me);
        $order = CustomerOrder::first();

        $this->postJson("/api/v1/customer-orders/{$order->id}/study-result", ['result' => 'customer_absent'])->assertOk();

        $this->getJson('/api/v1/my-visits')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_only_technicians_have_visits(): void
    {
        foreach ([Role::Admin, Role::Dg, Role::Dt, Role::Supervisor, Role::Coordinator, Role::BeManager] as $i => $role) {
            Sanctum::actingAs($this->user($role, "Agent {$i}"));
            $this->getJson('/api/v1/my-visits')->assertForbidden();
        }

        $this->getJson('/api/v1/my-visits')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/my-visits')->assertUnauthorized();
    }
}
