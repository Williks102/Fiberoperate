<?php

namespace Tests\Feature\Api;

use App\Enums\PcoStatus;
use App\Enums\Role;
use App\Models\Jdv;
use App\Models\Pco;
use App\Models\Plaque;
use App\Models\User;
use App\Models\Zone;
use App\Support\CodeNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PcoFormApiTest extends TestCase
{
    use RefreshDatabase;

    private function login(Role $role): User
    {
        $user = User::create(['name' => 'u', 'email' => "{$role->value}@x.test", 'password' => 'secret123', 'role' => $role]);
        Sanctum::actingAs($user);

        return $user;
    }

    private function jdv(string $plaque = 'FAYA', string $zone = 'FAY03', string $splitter = 'SPL001'): Jdv
    {
        $zoneModel = Zone::firstOrCreate(
            ['plaque_id' => Plaque::firstOrCreate(['name' => $plaque])->id, 'code_normalized' => CodeNormalizer::zone($zone)],
            ['code' => $zone],
        );

        return Jdv::firstOrCreate(['zone_id' => $zoneModel->id, 'code_normalized' => $splitter], ['code' => $splitter]);
    }

    public function test_a_pco_is_created_in_the_zone_of_its_splitter_with_its_details(): void
    {
        $this->login(Role::Admin);
        $jdv = $this->jdv();

        $this->postJson('/api/v1/pcos', [
            'jdv_id' => $jdv->id, 'code' => 'FAY03_PCO001', 'latitude' => 5.36, 'longitude' => -4.0083,
            'olt_slot' => '1', 'olt_port' => '2', 'fiber_strand' => '3', 'comment' => 'Pose prévue',
        ])->assertCreated()
            ->assertJsonPath('data.zone.code', 'FAY03')
            ->assertJsonPath('data.zone.plaque.name', 'FAYA')
            ->assertJsonPath('data.jdv.code', 'SPL001')
            ->assertJsonPath('data.fiber_strand', '3')
            ->assertJsonPath('data.status', null);

        $this->assertSame($jdv->zone_id, Pco::firstOrFail()->zone_id);
    }

    public function test_status_zone_and_blocking_cannot_be_set_through_the_form(): void
    {
        $this->login(Role::Admin);
        $jdv = $this->jdv();
        $pco = Pco::create(['jdv_id' => $jdv->id, 'code' => 'FAY03_PCO001', 'status_raw' => 'En attente BE']);

        foreach (['status' => 'activated', 'zone_id' => $jdv->zone_id, 'status_raw' => 'Posé', 'is_blocked' => true, 'block_reason_id' => $jdv->id] as $field => $value) {
            $this->patchJson("/api/v1/pcos/{$pco->id}", [$field => $value])->assertStatus(422)->assertJsonValidationErrors($field);
        }

        $fresh = $pco->fresh();
        $this->assertNull($fresh->status);
        $this->assertSame('En attente BE', $fresh->status_raw);
        $this->assertFalse($fresh->is_blocked);
    }

    public function test_a_pco_moves_to_another_splitter_only_inside_its_zone(): void
    {
        $this->login(Role::Admin);
        $first = $this->jdv('FAYA', 'FAY03', 'SPL001');
        $second = $this->jdv('FAYA', 'FAY03', 'SPL002');
        $elsewhere = $this->jdv('ARCADE', 'ACA003', 'SPL001');
        $pco = Pco::create(['jdv_id' => $first->id, 'code' => 'FAY03_PCO001']);

        $this->patchJson("/api/v1/pcos/{$pco->id}", ['jdv_id' => $second->id])->assertOk()->assertJsonPath('data.jdv.code', 'SPL002');
        $this->patchJson("/api/v1/pcos/{$pco->id}", ['jdv_id' => $elsewhere->id])
            ->assertStatus(422)->assertJsonPath('errors.jdv_id.0', 'Le splitter doit appartenir à la même sous-plaque que le PCO.');
        $this->assertSame($second->id, $pco->fresh()->jdv_id);
    }

    public function test_coordinates_must_be_complete_and_inside_the_country_and_never_corrected(): void
    {
        $this->login(Role::Admin);
        $jdv = $this->jdv();
        $base = ['jdv_id' => $jdv->id, 'code' => 'FAY03_PCO001'];

        $this->postJson('/api/v1/pcos', $base + ['latitude' => -4.0083, 'longitude' => 5.36])
            ->assertStatus(422)->assertJsonPath('errors.latitude.0', 'La latitude et la longitude semblent inversées. Vérifiez les coordonnées.');
        $this->postJson('/api/v1/pcos', $base + ['latitude' => 48.85, 'longitude' => 2.35])
            ->assertStatus(422)->assertJsonPath('errors.latitude.0', "Ces coordonnées ne sont pas en Côte d'Ivoire. Vérifiez-les.");
        $this->postJson('/api/v1/pcos', $base + ['latitude' => 5.36])->assertStatus(422)->assertJsonValidationErrors('longitude');
        $this->postJson('/api/v1/pcos', $base + ['latitude' => 95, 'longitude' => -4])->assertStatus(422)->assertJsonValidationErrors('latitude');
        $this->assertSame(0, Pco::count());

        $this->postJson('/api/v1/pcos', $base + ['latitude' => null, 'longitude' => null])->assertCreated();
    }

    public function test_the_same_code_is_refused_in_a_zone_whatever_its_spelling_but_allowed_elsewhere(): void
    {
        $this->login(Role::Admin);
        $faya = $this->jdv('FAYA', 'FAY03', 'SPL001');
        $arcade = $this->jdv('ARCADE', 'ACA003', 'SPL001');
        Pco::create(['jdv_id' => $faya->id, 'code' => 'FAY03_PCO001']);

        $this->postJson('/api/v1/pcos', ['jdv_id' => $arcade->id, 'code' => 'FAY03_PCO001'])->assertCreated();
        $this->postJson('/api/v1/pcos', [])->assertStatus(422)->assertJsonValidationErrors(['jdv_id', 'code']);
        // En dernier : sous PostgreSQL, une erreur SQL annule la transaction partagée du test.
        $this->postJson('/api/v1/pcos', ['jdv_id' => $faya->id, 'code' => 'fay3_pco-1'])->assertStatus(422)->assertJsonPath('code', 'DUPLICATE');
    }

    public function test_editing_a_pco_never_touches_its_status(): void
    {
        $this->login(Role::Admin);
        $pco = Pco::create(['jdv_id' => $this->jdv()->id, 'code' => 'FAY03_PCO001', 'status' => PcoStatus::Pulled]);

        $this->patchJson("/api/v1/pcos/{$pco->id}", ['comment' => 'Corrigé'])->assertOk()->assertJsonPath('data.status', 'pulled');
        $this->assertSame(0, $pco->history()->count());
    }

    public function test_only_the_administrator_writes_pcos(): void
    {
        $jdv = $this->jdv();

        foreach ([Role::Dg, Role::Dt, Role::Supervisor, Role::Coordinator, Role::Technician, Role::BeManager] as $role) {
            $this->login($role);
            $this->postJson('/api/v1/pcos', ['jdv_id' => $jdv->id, 'code' => 'FAY03_PCO001'])->assertForbidden();
        }
        $this->assertSame(0, Pco::count());
    }
}
