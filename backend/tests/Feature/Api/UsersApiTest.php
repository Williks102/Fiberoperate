<?php

namespace Tests\Feature\Api;

use App\Enums\MissionType;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UsersApiTest extends TestCase
{
    use RefreshDatabase;

    private function login(Role $role): User
    {
        $user = User::create(['name' => $role->value, 'email' => "{$role->value}@x.test", 'password' => 'secret123', 'role' => $role]);
        Sanctum::actingAs($user);

        return $user;
    }

    private function payload(array $extra = []): array
    {
        return $extra + ['name' => 'Kouassi', 'email' => 'k@x.test', 'password' => 'motdepasse1', 'role' => 'technician'];
    }

    public function test_only_admin_manages_users(): void
    {
        foreach ([Role::Dg, Role::Dt, Role::Supervisor, Role::Coordinator, Role::Technician, Role::BeManager] as $role) {
            $this->login($role);
            $this->postJson('/api/v1/users', $this->payload(['email' => "{$role->value}-new@x.test"]))->assertForbidden();
        }

        $this->login(Role::Admin);
        $this->postJson('/api/v1/users', $this->payload())->assertCreated()->assertJsonPath('data.role', 'technician');
    }

    public function test_dg_dt_can_list_users_but_not_supervisor(): void
    {
        $this->login(Role::Dg);
        $this->getJson('/api/v1/users')->assertOk();

        $this->login(Role::Supervisor);
        $this->getJson('/api/v1/users')->assertForbidden();
    }

    public function test_password_is_hashed_never_returned_and_policy_enforced(): void
    {
        $this->login(Role::Admin);

        $response = $this->postJson('/api/v1/users', $this->payload())->assertCreated();
        $response->assertJsonMissingPath('data.password');
        $this->assertNotSame('motdepasse1', User::where('email', 'k@x.test')->value('password'));

        $this->postJson('/api/v1/users', $this->payload(['email' => 'faible@x.test', 'password' => 'abc']))
            ->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_skills_only_for_technicians_and_synced(): void
    {
        $this->login(Role::Admin);

        $this->postJson('/api/v1/users', $this->payload(['role' => 'coordinator', 'skills' => ['study']]))
            ->assertStatus(422)->assertJsonValidationErrors('skills');

        $id = $this->postJson('/api/v1/users', $this->payload(['skills' => ['study', 'maintenance']]))
            ->assertCreated()->json('data.id');
        $user = User::findOrFail($id);
        $this->assertTrue($user->hasSkill(MissionType::Study));
        $this->assertFalse($user->hasSkill(MissionType::Deployment));

        $this->patchJson("/api/v1/users/{$id}", ['skills' => ['deployment']])->assertOk();
        $this->assertTrue($user->hasSkill(MissionType::Deployment));
        $this->assertFalse($user->hasSkill(MissionType::Study));
    }

    public function test_deactivation_revokes_tokens_and_self_deactivation_is_refused(): void
    {
        $admin = $this->login(Role::Admin);
        $target = User::create(['name' => 'T', 'email' => 'tt@x.test', 'password' => 'secret123', 'role' => Role::Technician]);
        $target->createToken('web');

        $this->patchJson("/api/v1/users/{$target->id}", ['is_active' => false])->assertOk();
        $this->assertSame(0, $target->tokens()->count());

        $this->patchJson("/api/v1/users/{$admin->id}", ['is_active' => false])->assertStatus(422);
        $this->deleteJson("/api/v1/users/{$admin->id}")->assertForbidden();
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $this->login(Role::Admin);
        $this->postJson('/api/v1/users', $this->payload())->assertCreated();

        $this->postJson('/api/v1/users', $this->payload())->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_validation_messages_are_in_french(): void
    {
        $this->login(Role::Admin);

        $response = $this->postJson('/api/v1/users', $this->payload(['email' => 'x@x.test', 'password' => 'abc']))->assertStatus(422);

        $message = $response->json('errors.password.0');
        $this->assertStringNotContainsString('validation.', $message);
        $this->assertStringContainsString('mot de passe', $message);
        $this->assertStringContainsString('au moins', $message);
    }

    public function test_be_manager_and_the_new_skills_can_be_created_by_the_admin(): void
    {
        $this->login(Role::Admin);

        $this->postJson('/api/v1/users', $this->payload(['email' => 'chef@x.test', 'role' => 'be_manager']))
            ->assertCreated()->assertJsonPath('data.role', 'be_manager')->assertJsonPath('data.role_label', "Chef du bureau d'études");

        $id = $this->postJson('/api/v1/users', $this->payload(['email' => 'dessin@x.test', 'skills' => ['drawing', 'survey']]))
            ->assertCreated()->json('data.id');
        $user = User::findOrFail($id);
        $this->assertTrue($user->hasSkill(MissionType::Drawing));
        $this->assertTrue($user->hasSkill(MissionType::Survey));
        $this->assertFalse($user->hasSkill(MissionType::Study));
    }

    public function test_skills_are_still_reserved_to_technicians_for_the_be_manager(): void
    {
        $this->login(Role::Admin);

        $this->postJson('/api/v1/users', $this->payload(['email' => 'chef@x.test', 'role' => 'be_manager', 'skills' => ['drawing']]))
            ->assertStatus(422)->assertJsonValidationErrors('skills');
    }

    public function test_be_manager_reads_the_network_but_cannot_manage_users_or_teams(): void
    {
        $this->login(Role::BeManager);

        $this->getJson('/api/v1/plaques')->assertOk();
        $this->getJson('/api/v1/olts')->assertOk();
        $this->getJson('/api/v1/users')->assertForbidden();
        $this->getJson('/api/v1/teams')->assertForbidden();
        $this->postJson('/api/v1/imports/network', [])->assertForbidden();
    }
}
