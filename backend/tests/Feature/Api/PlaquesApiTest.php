<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Olt;
use App\Models\Plaque;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlaquesApiTest extends TestCase
{
    use RefreshDatabase;

    private function user(Role $role, string $email): User
    {
        return User::create(['name' => ucfirst($email), 'email' => $email, 'password' => 'secret123', 'role' => $role]);
    }

    public function test_a_plaque_cannot_be_created_alone_it_is_born_with_its_project(): void
    {
        Sanctum::actingAs($this->user(Role::Admin, 'admin@x.test'));

        $this->postJson('/api/v1/plaques', ['name' => 'FAYA'])->assertMethodNotAllowed();
    }

    public function test_plaque_gets_olt_and_supervisor_and_returns_their_names(): void
    {
        Sanctum::actingAs($this->user(Role::Admin, 'admin@x.test'));
        $olt = Olt::create(['name' => 'OLT1']);
        $supervisor = $this->user(Role::Supervisor, 'sup@x.test');
        $plaque = Plaque::create(['name' => 'FAYA']);

        $this->patchJson("/api/v1/plaques/{$plaque->id}", ['olt_id' => $olt->id, 'supervisor_id' => $supervisor->id])
            ->assertOk()
            ->assertJsonPath('data.olt.name', 'OLT1')
            ->assertJsonPath('data.supervisor.name', 'Sup@x.test');

        $this->assertSame('OLT1', $this->getJson('/api/v1/plaques')->json('data.0.olt.name'));
    }

    public function test_olt_can_be_set_later_and_cleared(): void
    {
        Sanctum::actingAs($this->user(Role::Admin, 'admin@x.test'));
        $plaque = Plaque::create(['name' => 'ARCADE']);
        $olt = Olt::create(['name' => 'OLT1']);

        $this->patchJson("/api/v1/plaques/{$plaque->id}", ['olt_id' => $olt->id])->assertOk()->assertJsonPath('data.olt.id', $olt->id);
        $this->patchJson("/api/v1/plaques/{$plaque->id}", ['olt_id' => null])->assertOk()->assertJsonPath('data.olt', null);
    }

    public function test_supervisor_must_have_the_supervisor_role_and_name_must_be_unique(): void
    {
        Sanctum::actingAs($this->user(Role::Admin, 'admin@x.test'));
        $coordinator = $this->user(Role::Coordinator, 'coord@x.test');
        Plaque::create(['name' => 'FAYA']);
        $other = Plaque::create(['name' => 'ARCADE']);

        $this->patchJson("/api/v1/plaques/{$other->id}", ['supervisor_id' => $coordinator->id])
            ->assertStatus(422)->assertJsonValidationErrors('supervisor_id');
        $this->patchJson("/api/v1/plaques/{$other->id}", ['name' => ''])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->patchJson("/api/v1/plaques/{$other->id}", ['name' => 'FAYA'])->assertStatus(422)->assertJsonPath('code', 'DUPLICATE');
    }

    public function test_assigning_a_supervisor_gives_them_the_plaque_in_their_perimeter(): void
    {
        $admin = $this->user(Role::Admin, 'admin@x.test');
        $supervisor = $this->user(Role::Supervisor, 'sup@x.test');
        $plaque = Plaque::create(['name' => 'FAYA']);

        Sanctum::actingAs($supervisor);
        $this->assertSame([], $this->getJson('/api/v1/plaques')->json('data'));

        Sanctum::actingAs($admin);
        $this->patchJson("/api/v1/plaques/{$plaque->id}", ['supervisor_id' => $supervisor->id])->assertOk();

        Sanctum::actingAs($supervisor);
        $this->assertSame([$plaque->id], collect($this->getJson('/api/v1/plaques')->json('data'))->pluck('id')->all());
    }
}
