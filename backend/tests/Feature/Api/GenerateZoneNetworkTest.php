<?php

namespace Tests\Feature\Api;

use App\Enums\PcoStatus;
use App\Enums\Role;
use App\Models\Jdv;
use App\Models\Pco;
use App\Models\Plaque;
use App\Models\Sro;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GenerateZoneNetworkTest extends TestCase
{
    use RefreshDatabase;

    private function login(Role $role): void
    {
        Sanctum::actingAs(User::create(['name' => 'u', 'email' => "{$role->value}@x.test", 'password' => 'secret123', 'role' => $role]));
    }

    private function zone(string $code = 'ABB01'): Zone
    {
        return Zone::create(['plaque_id' => Plaque::firstOrCreate(['name' => 'ABOBO'])->id, 'code' => $code]);
    }

    public function test_64_pco_give_8_splitters_of_8_with_continuous_names(): void
    {
        $this->login(Role::Admin);
        $zone = $this->zone();

        $this->postJson("/api/v1/zones/{$zone->id}/generate", ['pco_count' => 64, 'sro_code' => 'ABB456'])
            ->assertCreated()
            ->assertJsonPath('data', ['sro_created' => true, 'splitters' => 8, 'pcos' => 64]);

        $this->assertSame('ABB456', $zone->sro->code);
        $this->assertSame(['SPL001', 'SPL002', 'SPL003', 'SPL004', 'SPL005', 'SPL006', 'SPL007', 'SPL008'], Jdv::orderBy('code')->pluck('code')->all());
        $this->assertSame(64, Pco::where('zone_id', $zone->id)->count());

        $second = Jdv::where('code', 'SPL002')->first();
        $this->assertSame(['ABB01_PCO009', 'ABB01_PCO016'], [$second->pcos()->orderBy('number')->first()->code, $second->pcos()->orderByDesc('number')->first()->code]);
    }

    public function test_generated_pco_start_to_pull_with_16_ports_and_a_history_line(): void
    {
        $this->login(Role::Admin);
        $zone = $this->zone();
        $this->postJson("/api/v1/zones/{$zone->id}/generate", ['pco_count' => 1, 'sro_code' => 'ABB456'])->assertCreated();

        $pco = Pco::firstOrFail();
        $this->assertSame(PcoStatus::ToPull, $pco->status);
        $this->assertSame(16, $pco->capacity);
        $this->assertSame(1, $pco->history()->count());
    }

    public function test_any_number_is_allowed_and_the_last_splitter_may_be_smaller(): void
    {
        $this->login(Role::Admin);
        $zone = $this->zone();

        $this->postJson("/api/v1/zones/{$zone->id}/generate", ['pco_count' => 20, 'sro_code' => 'ABB456'])
            ->assertCreated()->assertJsonPath('data.splitters', 3);
        $this->assertSame(4, Jdv::where('code', 'SPL003')->first()->pcos()->count());
    }

    public function test_the_splitter_size_can_be_changed(): void
    {
        $this->login(Role::Admin);
        $zone = $this->zone();

        $this->postJson("/api/v1/zones/{$zone->id}/generate", ['pco_count' => 32, 'splitter_size' => 16, 'sro_code' => 'ABB456'])
            ->assertCreated()->assertJsonPath('data.splitters', 2);
    }

    public function test_the_sro_number_is_required_unless_the_zone_already_has_one(): void
    {
        $this->login(Role::Admin);
        $zone = $this->zone();

        $this->postJson("/api/v1/zones/{$zone->id}/generate", ['pco_count' => 8])->assertStatus(422)->assertJsonValidationErrors('sro_code');
        $this->assertSame(0, Jdv::count());

        Sro::create(['zone_id' => $zone->id, 'code' => 'ABB456']);
        $this->postJson("/api/v1/zones/{$zone->id}/generate", ['pco_count' => 8])
            ->assertCreated()->assertJsonPath('data.sro_created', false);
        $this->assertSame(1, Sro::count());
    }

    public function test_an_existing_sro_code_is_refused_and_nothing_is_created(): void
    {
        $this->login(Role::Admin);
        Sro::create(['zone_id' => $this->zone('ABB02')->id, 'code' => 'ABB456']);
        $zone = $this->zone();

        $this->postJson("/api/v1/zones/{$zone->id}/generate", ['pco_count' => 8, 'sro_code' => 'abb-456'])
            ->assertStatus(422)->assertJsonValidationErrors('sro_code');
        $this->assertSame(0, Jdv::count());
    }

    public function test_generation_is_refused_when_the_zone_already_has_splitters_or_pco(): void
    {
        $this->login(Role::Admin);
        $zone = $this->zone();
        Jdv::create(['zone_id' => $zone->id, 'code' => 'SPL001']);

        $this->postJson("/api/v1/zones/{$zone->id}/generate", ['pco_count' => 8, 'sro_code' => 'ABB456'])
            ->assertStatus(422)->assertJsonValidationErrors('zone');
        $this->assertSame(1, Jdv::count());
        $this->assertSame(0, Sro::count());
    }

    public function test_the_count_is_validated_and_only_the_administrator_generates(): void
    {
        $this->login(Role::Admin);
        $zone = $this->zone();
        $this->postJson("/api/v1/zones/{$zone->id}/generate", ['pco_count' => 0, 'sro_code' => 'ABB456'])->assertStatus(422)->assertJsonValidationErrors('pco_count');

        foreach ([Role::Supervisor, Role::Coordinator, Role::Technician, Role::Dt, Role::Dg] as $role) {
            $this->login($role);
            $this->postJson("/api/v1/zones/{$zone->id}/generate", ['pco_count' => 8, 'sro_code' => 'ABB456'])->assertForbidden();
        }
        $this->assertSame(0, Jdv::count());
    }
}
