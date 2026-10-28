<?php

namespace Tests\Feature;

use App\Enums\MissionType;
use App\Enums\PcoStatus;
use App\Enums\Role;
use App\Enums\Vendor;
use App\Models\Jdv;
use App\Models\Pco;
use App\Models\Plaque;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class HierarchyTest extends TestCase
{
    use RefreshDatabase;

    private function zone(string $code = 'FAY03'): Zone
    {
        $plaque = Plaque::firstOrCreate(['name' => 'FAYA']);

        return Zone::create(['plaque_id' => $plaque->id, 'code' => $code]);
    }

    public function test_hierarchy_relations_and_normalized_codes(): void
    {
        $zone = $this->zone();
        $jdv = Jdv::create(['zone_id' => $zone->id, 'code' => 'SPL001']);
        $pco = Pco::create(['jdv_id' => $jdv->id, 'code' => 'FAY03_PCO-001']);

        $this->assertSame('FAY003', $zone->code_normalized);
        $this->assertSame('FAY003_PCO001', $pco->code_normalized);
        $this->assertSame(1, $pco->number);
        $this->assertTrue($pco->zone->is($zone));
        $this->assertCount(1, $zone->pcos);
        $this->assertSame($zone->plaque->id, Plaque::first()->id);
    }

    public function test_no_fixed_counts(): void
    {
        $zone = $this->zone();
        $jdv = Jdv::create(['zone_id' => $zone->id, 'code' => 'SPL001']);
        foreach (range(1, 16) as $n) {
            Pco::create(['jdv_id' => $jdv->id, 'code' => sprintf('FAY03_PCO%03d', $n)]);
        }
        $this->assertCount(16, $jdv->pcos);
    }

    public function test_duplicate_zone_variant_is_rejected_and_soft_delete_frees_code(): void
    {
        $zone = $this->zone('FAY03');

        try {
            $this->zone('FAY003');
            $this->fail('Duplicate expected');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $zone->delete();
        $this->assertNotNull($this->zone('FAY003')->id);
    }

    public function test_change_status_writes_history(): void
    {
        $zone = $this->zone();
        $pco = Pco::create(['zone_id' => $zone->id, 'code' => 'FAY03_PCO001']);
        $user = User::create(['name' => 'T', 'email' => 't@x.test', 'password' => 'secret123', 'role' => Role::Technician]);

        $pco->changeStatus(PcoStatus::Pulled, $user, 'tirage ok', 'phone-1');

        $this->assertSame(PcoStatus::Pulled, $pco->fresh()->status);
        $h = $pco->history()->first();
        $this->assertNull($h->from_status);
        $this->assertSame(PcoStatus::Pulled, $h->to_status);
        $this->assertSame($user->id, $h->changed_by);
        $this->assertSame('phone-1', $h->device);
    }

    public function test_unmapped_raw_status_keeps_null_status(): void
    {
        $zone = $this->zone();
        $pco = Pco::create(['zone_id' => $zone->id, 'code' => 'FAY03_PCO002', 'status_raw' => 'En attente BE']);

        $this->assertNull($pco->fresh()->status);
        $this->assertSame('En attente BE', $pco->status_raw);
    }

    public function test_jdv_zone_mismatch_is_rejected(): void
    {
        $a = $this->zone('FAY03');
        $b = $this->zone('FAY04');
        $jdv = Jdv::create(['zone_id' => $a->id, 'code' => 'SPL001']);

        $this->expectException(InvalidArgumentException::class);
        Pco::create(['zone_id' => $b->id, 'jdv_id' => $jdv->id, 'code' => 'FAY04_PCO001']);
    }

    public function test_skills_and_vendor_label(): void
    {
        $user = User::create(['name' => 'T', 'email' => 't@x.test', 'password' => 'secret123', 'role' => Role::Technician]);
        $user->skills()->create(['mission_type' => MissionType::Study]);

        $this->assertTrue($user->hasSkill(MissionType::Study));
        $this->assertFalse($user->hasSkill(MissionType::Deployment));

        $zone = $this->zone();
        $this->assertNull($zone->accessPointLabel());
        $zone->update(['vendor' => Vendor::Nokia]);
        $this->assertSame('SRO', $zone->accessPointLabel());
    }
}
