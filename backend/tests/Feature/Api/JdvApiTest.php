<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Jdv;
use App\Models\Plaque;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JdvApiTest extends TestCase
{
    use RefreshDatabase;

    private function login(Role $role): User
    {
        $user = User::create(['name' => 'u', 'email' => "{$role->value}@x.test", 'password' => 'secret123', 'role' => $role]);
        Sanctum::actingAs($user);

        return $user;
    }

    private function zone(string $plaque = 'FAYA', string $code = 'FAY03'): Zone
    {
        return Zone::create(['plaque_id' => Plaque::firstOrCreate(['name' => $plaque])->id, 'code' => $code]);
    }

    public function test_splitter_is_created_and_returns_its_zone_and_plaque(): void
    {
        $this->login(Role::Admin);
        $zone = $this->zone();

        $this->postJson('/api/v1/jdvs', ['zone_id' => $zone->id, 'code' => 'SPL001'])
            ->assertCreated()
            ->assertJsonPath('data.zone.code', 'FAY03')
            ->assertJsonPath('data.zone.plaque.name', 'FAYA');

        $this->assertSame('FAYA', $this->getJson('/api/v1/jdvs')->json('data.0.zone.plaque.name'));
    }

    public function test_a_splitter_never_changes_zone(): void
    {
        $this->login(Role::Admin);
        $jdv = Jdv::create(['zone_id' => $this->zone()->id, 'code' => 'SPL001']);
        $other = $this->zone('ARCADE', 'ACA003');

        $this->patchJson("/api/v1/jdvs/{$jdv->id}", ['zone_id' => $other->id])->assertStatus(422)->assertJsonValidationErrors('zone_id');
        $this->patchJson("/api/v1/jdvs/{$jdv->id}", ['code' => 'SPL010'])->assertOk()->assertJsonPath('data.code', 'SPL010');
        $this->assertSame($jdv->zone_id, $jdv->fresh()->zone_id);
    }

    public function test_filters_by_plaque_zone_and_code(): void
    {
        $this->login(Role::Admin);
        $faya = $this->zone('FAYA', 'FAY03');
        $arcade = $this->zone('ARCADE', 'ACA003');
        Jdv::create(['zone_id' => $faya->id, 'code' => 'SPL001']);
        Jdv::create(['zone_id' => $faya->id, 'code' => 'SPL002']);
        Jdv::create(['zone_id' => $arcade->id, 'code' => 'SPL001']);

        $count = fn (string $query) => count($this->getJson("/api/v1/jdvs?{$query}")->assertOk()->json('data'));

        $this->assertSame(2, $count('plaque_id='.$faya->plaque_id));
        $this->assertSame(1, $count('zone_id='.$arcade->id));
        $this->assertSame(2, $count('q=spl-001'));
    }

    public function test_required_fields_and_same_code_allowed_in_another_zone(): void
    {
        $this->login(Role::Admin);
        $faya = $this->zone('FAYA', 'FAY03');
        $arcade = $this->zone('ARCADE', 'ACA003');
        Jdv::create(['zone_id' => $faya->id, 'code' => 'SPL001']);

        $this->postJson('/api/v1/jdvs', [])->assertStatus(422)->assertJsonValidationErrors(['zone_id', 'code']);
        $this->postJson('/api/v1/jdvs', ['zone_id' => $arcade->id, 'code' => 'SPL001'])->assertCreated();
        // En dernier : sous PostgreSQL, une erreur SQL annule la transaction partagée du test.
        $this->postJson('/api/v1/jdvs', ['zone_id' => $faya->id, 'code' => 'spl 001'])->assertStatus(422)->assertJsonPath('code', 'DUPLICATE');
    }

    public function test_only_the_administrator_writes_splitters(): void
    {
        $zone = $this->zone();

        foreach ([Role::Dg, Role::Dt, Role::Supervisor, Role::Coordinator, Role::Technician, Role::BeManager] as $role) {
            $this->login($role);
            $this->postJson('/api/v1/jdvs', ['zone_id' => $zone->id, 'code' => 'SPL001'])->assertForbidden();
        }
    }
}
