<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Plaque;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ZonesApiTest extends TestCase
{
    use RefreshDatabase;

    private function login(Role $role): User
    {
        $user = User::create(['name' => 'u', 'email' => "{$role->value}@x.test", 'password' => 'secret123', 'role' => $role]);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_zone_is_created_with_vendor_label_status_and_plaque_name(): void
    {
        $this->login(Role::Admin);
        $plaque = Plaque::create(['name' => 'FAYA']);

        $this->postJson('/api/v1/zones', ['plaque_id' => $plaque->id, 'code' => 'FAY03', 'name' => 'FAYA 03', 'vendor' => 'nokia'])
            ->assertCreated()
            ->assertJsonPath('data.access_point_label', 'SRO')
            ->assertJsonPath('data.status', 'planned')
            ->assertJsonPath('data.plaque.name', 'FAYA');

        $this->assertSame('FAYA', $this->getJson('/api/v1/zones')->json('data.0.plaque.name'));
    }

    public function test_code_variants_collide_inside_a_plaque_but_not_across_plaques(): void
    {
        $this->login(Role::Admin);
        $faya = Plaque::create(['name' => 'FAYA']);
        $arcade = Plaque::create(['name' => 'ARCADE']);
        Zone::create(['plaque_id' => $faya->id, 'code' => 'FAY03']);

        $this->postJson('/api/v1/zones', ['plaque_id' => $arcade->id, 'code' => 'FAY03'])->assertCreated();
        // En dernier : sous PostgreSQL, une erreur SQL annule la transaction partagée du test.
        $this->postJson('/api/v1/zones', ['plaque_id' => $faya->id, 'code' => 'FAY003'])
            ->assertStatus(422)->assertJsonPath('code', 'DUPLICATE');
    }

    public function test_validation_of_vendor_status_and_required_fields(): void
    {
        $this->login(Role::Admin);
        $plaque = Plaque::create(['name' => 'FAYA']);

        $this->postJson('/api/v1/zones', [])->assertStatus(422)->assertJsonValidationErrors(['plaque_id', 'code']);
        $this->postJson('/api/v1/zones', ['plaque_id' => $plaque->id, 'code' => 'X1', 'vendor' => 'samsung'])
            ->assertStatus(422)->assertJsonValidationErrors('vendor');
        $this->postJson('/api/v1/zones', ['plaque_id' => $plaque->id, 'code' => 'X1', 'status' => 'inconnu'])
            ->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_status_and_vendor_can_be_updated_and_vendor_cleared(): void
    {
        $this->login(Role::Admin);
        $zone = Zone::create(['plaque_id' => Plaque::create(['name' => 'FAYA'])->id, 'code' => 'FAY03', 'vendor' => 'huawei']);

        $this->patchJson("/api/v1/zones/{$zone->id}", ['status' => 'deploying'])->assertOk()->assertJsonPath('data.status', 'deploying');
        $this->patchJson("/api/v1/zones/{$zone->id}", ['vendor' => null])->assertOk()
            ->assertJsonPath('data.vendor', null)->assertJsonPath('data.access_point_label', null);
    }

    public function test_only_the_administrator_writes_zones(): void
    {
        $plaque = Plaque::create(['name' => 'FAYA']);

        foreach ([Role::Dg, Role::Dt, Role::Supervisor, Role::Coordinator, Role::Technician, Role::BeManager] as $role) {
            $this->login($role);
            $this->postJson('/api/v1/zones', ['plaque_id' => $plaque->id, 'code' => 'FAY03'])->assertForbidden();
        }
    }
}
