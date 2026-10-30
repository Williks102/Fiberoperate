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

class PcosListApiTest extends TestCase
{
    use RefreshDatabase;

    private function login(Role $role): User
    {
        $user = User::create(['name' => 'u', 'email' => "{$role->value}@x.test", 'password' => 'secret123', 'role' => $role]);
        Sanctum::actingAs($user);

        return $user;
    }

    private function pco(string $plaque, string $zone, string $splitter, string $code, array $extra = []): Pco
    {
        $zoneModel = Zone::firstOrCreate(
            ['plaque_id' => Plaque::firstOrCreate(['name' => $plaque])->id, 'code_normalized' => CodeNormalizer::zone($zone)],
            ['code' => $zone],
        );
        $jdv = Jdv::firstOrCreate(['zone_id' => $zoneModel->id, 'code_normalized' => $splitter], ['code' => $splitter]);

        return Pco::create(['jdv_id' => $jdv->id, 'code' => $code] + $extra);
    }

    public function test_the_list_returns_splitter_zone_and_plaque_names(): void
    {
        $this->login(Role::Admin);
        $this->pco('FAYA', 'FAY03', 'SPL001', 'FAY03_PCO001', ['status_raw' => 'En attente BE']);

        $row = $this->getJson('/api/v1/pcos')->assertOk()->json('data.0');

        $this->assertSame('SPL001', $row['jdv']['code']);
        $this->assertSame('FAY03', $row['zone']['code']);
        $this->assertSame('FAYA', $row['zone']['plaque']['name']);
        $this->assertSame('En attente BE', $row['status_raw']);
        $this->assertNull($row['status']);
    }

    public function test_filters_by_plaque_zone_status_blocked_and_code(): void
    {
        $this->login(Role::Admin);
        $a = $this->pco('FAYA', 'FAY03', 'SPL001', 'FAY03_PCO001', ['status' => PcoStatus::Pulled]);
        $this->pco('FAYA', 'FAY04', 'SPL001', 'FAY04_PCO001', ['is_blocked' => true]);
        $this->pco('ARCADE', 'ACA003', 'SPL001', 'ACA003_PCO001');

        $count = fn (string $query) => count($this->getJson("/api/v1/pcos?{$query}&per_page=100")->assertOk()->json('data'));

        $this->assertSame(2, $count('plaque_id='.$a->zone->plaque_id));
        $this->assertSame(1, $count('zone_id='.$a->zone_id));
        $this->assertSame(1, $count('status=pulled'));
        $this->assertSame(1, $count('is_blocked=1'));
        $this->assertSame(1, $count('q=aca3_pco-1'));
        $this->assertSame(3, $count('q='));
    }

    public function test_the_supervisor_only_lists_the_pcos_of_their_plaque(): void
    {
        $supervisor = User::create(['name' => 'S', 'email' => 's@x.test', 'password' => 'secret123', 'role' => Role::Supervisor]);
        $faya = $this->pco('FAYA', 'FAY03', 'SPL001', 'FAY03_PCO001');
        $this->pco('ARCADE', 'ACA003', 'SPL001', 'ACA003_PCO001');
        Plaque::where('name', 'FAYA')->update(['supervisor_id' => $supervisor->id]);
        Sanctum::actingAs($supervisor);

        $this->assertSame([$faya->id], collect($this->getJson('/api/v1/pcos?per_page=100')->assertOk()->json('data'))->pluck('id')->all());
    }
}
