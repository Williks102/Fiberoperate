<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeamsApiTest extends TestCase
{
    use RefreshDatabase;

    private function user(Role $role, string $email): User
    {
        return User::create(['name' => ucfirst($email), 'email' => $email, 'password' => 'secret123', 'role' => $role]);
    }

    public function test_team_is_created_with_members_and_returns_names(): void
    {
        Sanctum::actingAs($this->user(Role::Admin, 'admin@x.test'));
        $coordinator = $this->user(Role::Coordinator, 'coord@x.test');
        $leader = $this->user(Role::Technician, 'chef@x.test');
        $member = $this->user(Role::Technician, 'membre@x.test');

        $response = $this->postJson('/api/v1/teams', [
            'name' => 'Équipe raccordement',
            'coordinator_id' => $coordinator->id,
            'leader_id' => $leader->id,
            'member_ids' => [$leader->id, $member->id],
        ])->assertCreated();

        $response->assertJsonPath('data.coordinator.name', 'Coord@x.test')
            ->assertJsonPath('data.leader.id', $leader->id)
            ->assertJsonCount(2, 'data.members');

        $list = $this->getJson('/api/v1/teams')->assertOk();
        $this->assertSame('Chef@x.test', $list->json('data.0.leader.name'));
        $this->assertCount(2, $list->json('data.0.members'));
    }

    public function test_members_are_replaced_on_update_and_kept_when_omitted(): void
    {
        Sanctum::actingAs($this->user(Role::Dt, 'dt@x.test'));
        $a = $this->user(Role::Technician, 'a@x.test');
        $b = $this->user(Role::Technician, 'b@x.test');
        $team = Team::create(['name' => 'E1']);
        $team->members()->sync([$a->id]);

        $this->patchJson("/api/v1/teams/{$team->id}", ['name' => 'E1 bis'])->assertOk()->assertJsonCount(1, 'data.members');
        $this->patchJson("/api/v1/teams/{$team->id}", ['member_ids' => [$b->id]])->assertOk()
            ->assertJsonPath('data.members.0.id', $b->id);
    }

    public function test_leader_must_be_a_technician_and_coordinator_a_coordinator(): void
    {
        Sanctum::actingAs($this->user(Role::Admin, 'admin@x.test'));
        $dg = $this->user(Role::Dg, 'dg@x.test');

        $this->postJson('/api/v1/teams', ['name' => 'X', 'leader_id' => $dg->id])
            ->assertStatus(422)->assertJsonValidationErrors('leader_id');
        $this->postJson('/api/v1/teams', ['name' => 'X', 'coordinator_id' => $dg->id])
            ->assertStatus(422)->assertJsonValidationErrors('coordinator_id');
    }

    public function test_coordinator_sees_names_only_for_their_own_teams(): void
    {
        $mine = $this->user(Role::Coordinator, 'mine@x.test');
        $other = $this->user(Role::Coordinator, 'other@x.test');
        Team::create(['name' => 'Mienne', 'coordinator_id' => $mine->id]);
        Team::create(['name' => 'Autre', 'coordinator_id' => $other->id])->members()->sync([$this->user(Role::Technician, 't@x.test')->id]);

        Sanctum::actingAs($mine);
        $names = collect($this->getJson('/api/v1/teams')->assertOk()->json('data'))->pluck('name')->all();

        $this->assertSame(['Mienne'], $names);
    }
}
