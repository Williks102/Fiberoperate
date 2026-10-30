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
        return User::firstOrCreate(['email' => $email], ['name' => ucfirst($email), 'password' => 'secret123', 'role' => $role]);
    }

    public function test_a_plaque_cannot_be_created_alone_it_is_born_with_its_project(): void
    {
        Sanctum::actingAs($this->user(Role::Admin, 'admin@x.test'));

        $this->postJson('/api/v1/plaques', ['name' => 'FAYA'])->assertMethodNotAllowed();
    }

    public function test_the_olt_sent_by_the_client_is_set_by_the_admin_or_the_be_and_the_supervisor_only_by_the_dt(): void
    {
        $olt = Olt::create(['name' => 'OLT1']);
        $supervisor = $this->user(Role::Supervisor, 'sup@x.test');
        $plaque = Plaque::create(['name' => 'FAYA']);
        $url = "/api/v1/plaques/{$plaque->id}";

        foreach ([Role::Admin, Role::BeManager] as $role) {
            Sanctum::actingAs($this->user($role, "{$role->value}@x.test"));
            $this->patchJson($url, ['olt_id' => $olt->id])->assertOk()->assertJsonPath('data.olt.name', 'OLT1');
            $this->patchJson($url, ['supervisor_id' => $supervisor->id])->assertStatus(422)->assertJsonValidationErrors('supervisor_id');
        }

        Sanctum::actingAs($this->user(Role::Dt, 'dt@x.test'));
        $this->patchJson($url, ['supervisor_id' => $supervisor->id])->assertOk()->assertJsonPath('data.supervisor.name', 'Sup@x.test');
        $this->patchJson($url, ['name' => 'AUTRE'])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->patchJson($url, ['olt_id' => null])->assertStatus(422)->assertJsonValidationErrors('olt_id');

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

        Sanctum::actingAs($this->user(Role::Dt, 'dt@x.test'));
        $this->patchJson("/api/v1/plaques/{$other->id}", ['supervisor_id' => $coordinator->id])
            ->assertStatus(422)->assertJsonValidationErrors('supervisor_id');
        Sanctum::actingAs($this->user(Role::Admin, 'admin@x.test'));
        $this->patchJson("/api/v1/plaques/{$other->id}", ['name' => ''])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->patchJson("/api/v1/plaques/{$other->id}", ['name' => 'FAYA'])->assertStatus(422)->assertJsonPath('code', 'DUPLICATE');
    }

    public function test_assigning_a_supervisor_gives_them_the_plaque_in_their_perimeter(): void
    {
        $supervisor = $this->user(Role::Supervisor, 'sup@x.test');
        $plaque = Plaque::create(['name' => 'FAYA']);

        Sanctum::actingAs($supervisor);
        $this->assertSame([], $this->getJson('/api/v1/plaques')->json('data'));

        Sanctum::actingAs($this->user(Role::Dt, 'dt@x.test'));
        $this->patchJson("/api/v1/plaques/{$plaque->id}", ['supervisor_id' => $supervisor->id])->assertOk();

        Sanctum::actingAs($supervisor);
        $this->assertSame([$plaque->id], collect($this->getJson('/api/v1/plaques')->json('data'))->pluck('id')->all());
    }
}
