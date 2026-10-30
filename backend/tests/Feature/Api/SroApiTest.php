<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Plaque;
use App\Models\Sro;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SroApiTest extends TestCase
{
    use RefreshDatabase;

    private function login(Role $role, ?Plaque $plaque = null): User
    {
        $user = User::create(['name' => 'u', 'email' => "{$role->value}@x.test", 'password' => 'secret123', 'role' => $role]);
        if ($plaque !== null) {
            $plaque->update(['supervisor_id' => $user->id]);
        }
        Sanctum::actingAs($user);

        return $user;
    }

    private function zone(string $plaque = 'FAYA', string $code = 'FAY03'): Zone
    {
        return Zone::create(['plaque_id' => Plaque::firstOrCreate(['name' => $plaque])->id, 'code' => $code]);
    }

    public function test_sro_is_created_with_its_olt_link_and_gps(): void
    {
        $this->login(Role::Admin);
        $zone = $this->zone();

        $this->postJson('/api/v1/sros', [
            'zone_id' => $zone->id, 'code' => 'ABB456', 'latitude' => 5.3, 'longitude' => -4.0,
            'olt_slot' => '1', 'olt_port' => '3', 'fiber_strand' => '12',
        ])->assertCreated()
            ->assertJsonPath('data.code', 'ABB456')
            ->assertJsonPath('data.olt_port', '3')
            ->assertJsonPath('data.zone.plaque.name', 'FAYA');

        $this->assertSame($zone->id, $zone->sro->zone_id);
    }

    public function test_gps_must_be_in_ivory_coast_and_not_inverted(): void
    {
        $this->login(Role::Admin);
        $zone = $this->zone();

        $this->postJson('/api/v1/sros', ['zone_id' => $zone->id, 'code' => 'ABB456', 'latitude' => -4.0, 'longitude' => 5.3])
            ->assertStatus(422)->assertJsonValidationErrors('latitude');
        $this->postJson('/api/v1/sros', ['zone_id' => $zone->id, 'code' => 'ABB456', 'latitude' => 48.8, 'longitude' => 2.3])
            ->assertStatus(422)->assertJsonValidationErrors('latitude');
        $this->postJson('/api/v1/sros', ['zone_id' => $zone->id, 'code' => 'ABB456', 'latitude' => 5.3])
            ->assertStatus(422)->assertJsonValidationErrors('longitude');
    }

    public function test_a_zone_has_one_sro_and_the_code_is_unique(): void
    {
        $this->login(Role::Admin);
        $zone = $this->zone();
        Sro::create(['zone_id' => $zone->id, 'code' => 'ABB456']);

        $this->postJson('/api/v1/sros', ['zone_id' => $zone->id, 'code' => 'ABB457'])
            ->assertStatus(422)->assertJsonValidationErrors('zone_id');
        $this->postJson('/api/v1/sros', ['zone_id' => $this->zone('ARCADE', 'ACA003')->id, 'code' => 'abb-456'])
            ->assertStatus(422)->assertJsonValidationErrors('code');
    }

    public function test_a_sro_never_changes_zone_but_can_be_renamed(): void
    {
        $this->login(Role::Admin);
        $sro = Sro::create(['zone_id' => $this->zone()->id, 'code' => 'ABB456']);

        $this->patchJson("/api/v1/sros/{$sro->id}", ['zone_id' => $this->zone('ARCADE', 'ACA003')->id])
            ->assertStatus(422)->assertJsonValidationErrors('zone_id');
        $this->patchJson("/api/v1/sros/{$sro->id}", ['code' => 'ABB456'])->assertOk();
        $this->patchJson("/api/v1/sros/{$sro->id}", ['code' => 'ABB460'])->assertOk()->assertJsonPath('data.code', 'ABB460');
    }

    public function test_filters_by_zone_plaque_and_code(): void
    {
        $this->login(Role::Admin);
        Sro::create(['zone_id' => $this->zone('FAYA', 'FAY03')->id, 'code' => 'ABB456']);
        Sro::create(['zone_id' => $this->zone('ARCADE', 'ACA003')->id, 'code' => 'ACA010']);

        $this->assertCount(2, $this->getJson('/api/v1/sros')->json('data'));
        $this->assertCount(1, $this->getJson('/api/v1/sros?plaque_id='.Plaque::where('name', 'ARCADE')->value('id'))->json('data'));
        $this->assertSame('ABB456', $this->getJson('/api/v1/sros?q=abb')->json('data.0.code'));
    }

    public function test_access_by_role_and_perimeter(): void
    {
        $faya = Plaque::create(['name' => 'FAYA']);
        $sro = Sro::create(['zone_id' => Zone::create(['plaque_id' => $faya->id, 'code' => 'FAY03'])->id, 'code' => 'ABB456']);
        $other = Sro::create(['zone_id' => $this->zone('ARCADE', 'ACA003')->id, 'code' => 'ACA010']);

        $this->login(Role::Supervisor, $faya);
        $this->assertSame(['ABB456'], collect($this->getJson('/api/v1/sros')->json('data'))->pluck('code')->all());
        $this->getJson("/api/v1/sros/{$other->id}")->assertForbidden();
        $this->patchJson("/api/v1/sros/{$sro->id}", ['code' => 'ABB999'])->assertForbidden();
        $this->deleteJson("/api/v1/sros/{$sro->id}")->assertForbidden();
        $this->postJson('/api/v1/sros', ['zone_id' => $sro->zone_id, 'code' => 'X1'])->assertForbidden();
    }
}
