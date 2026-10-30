<?php

namespace Tests\Feature\Api;

use App\Enums\MissionType;
use App\Enums\ProjectPhase;
use App\Enums\Role;
use App\Models\Jdv;
use App\Models\Pco;
use App\Models\Plaque;
use App\Models\Project;
use App\Models\TechnicianSkill;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BeNetworkRightsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Plaque $plaque;

    private Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->user(Role::Admin, 'admin@x.test');
        $this->plaque = Plaque::create(['name' => 'ABOBO']);
        Project::startFor($this->plaque, $this->admin);
        $this->zone = Zone::create(['plaque_id' => $this->plaque->id, 'code' => 'ABB01']);
    }

    private function user(Role $role, string $email, ?MissionType $skill = null): User
    {
        $user = User::firstOrCreate(['email' => $email], ['name' => 'u', 'password' => 'secret123', 'role' => $role]);
        if ($skill !== null) {
            TechnicianSkill::firstOrCreate(['user_id' => $user->id, 'mission_type' => $skill]);
        }

        return $user;
    }

    private function beManager(): User
    {
        return $this->user(Role::BeManager, 'be@x.test');
    }

    private function beTechnician(): User
    {
        return $this->user(Role::Technician, 'tech-be@x.test', MissionType::Drawing);
    }

    private function lockPlan(): void
    {
        Project::firstOrFail()->forceFill(['phase' => ProjectPhase::BeManagerReview])->save();
    }

    public function test_the_be_manager_and_the_be_technician_adjust_the_network_during_the_pre_project(): void
    {
        foreach ([$this->beManager(), $this->beTechnician()] as $user) {
            Sanctum::actingAs($user);
            $this->postJson('/api/v1/zones', ['plaque_id' => $this->plaque->id, 'code' => 'ZZ'.random_int(10, 99)])->assertCreated();
        }

        Sanctum::actingAs($this->beManager());
        $jdv = Jdv::create(['zone_id' => $this->zone->id, 'code' => 'SPL001']);
        $this->postJson('/api/v1/jdvs', ['zone_id' => $this->zone->id, 'code' => 'SPL002'])->assertCreated();
        $this->postJson('/api/v1/pcos', ['jdv_id' => $jdv->id, 'code' => 'ABB01_PCO001'])->assertCreated();
        $this->postJson('/api/v1/sros', ['zone_id' => $this->zone->id, 'code' => 'ABB456'])->assertCreated();
        $this->deleteJson('/api/v1/jdvs/'.Jdv::where('code', 'SPL002')->value('id'))->assertNoContent();
    }

    public function test_the_be_generates_the_network_of_a_zone(): void
    {
        Sanctum::actingAs($this->beTechnician());
        $this->postJson("/api/v1/zones/{$this->zone->id}/generate", ['pco_count' => 16, 'sro_code' => 'ABB456'])->assertCreated();
        $this->assertSame(16, Pco::count());
    }

    public function test_the_plan_is_locked_for_the_be_once_it_goes_to_validation(): void
    {
        $this->lockPlan();

        foreach ([$this->beManager(), $this->beTechnician()] as $user) {
            Sanctum::actingAs($user);
            $this->postJson('/api/v1/zones', ['plaque_id' => $this->plaque->id, 'code' => 'ZZ01'])->assertForbidden();
            $this->postJson("/api/v1/zones/{$this->zone->id}/generate", ['pco_count' => 8, 'sro_code' => 'ABB456'])->assertForbidden();
        }
        $this->assertSame(0, Pco::count());

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/v1/zones', ['plaque_id' => $this->plaque->id, 'code' => 'ZZ01'])->assertCreated();
    }

    public function test_the_be_cannot_write_on_a_plaque_without_project_nor_other_roles_anywhere(): void
    {
        $alone = Plaque::create(['name' => 'SOLO']);
        Sanctum::actingAs($this->beManager());
        $this->postJson('/api/v1/zones', ['plaque_id' => $alone->id, 'code' => 'SOL01'])->assertForbidden();

        foreach ([
            $this->user(Role::Dg, 'dg@x.test'), $this->user(Role::Dt, 'dt@x.test'), $this->user(Role::Supervisor, 's@x.test'),
            $this->user(Role::Coordinator, 'c@x.test'), $this->user(Role::Technician, 't@x.test'),
            $this->user(Role::Technician, 'survey@x.test', MissionType::Survey),
        ] as $user) {
            Sanctum::actingAs($user);
            $this->postJson('/api/v1/zones', ['plaque_id' => $this->plaque->id, 'code' => 'ZZ01'])->assertForbidden();
            $this->patchJson("/api/v1/zones/{$this->zone->id}", ['name' => 'X'])->assertForbidden();
        }
        $this->assertSame(1, Zone::count());
    }

    public function test_a_zone_never_moves_to_another_plaque(): void
    {
        $other = Plaque::create(['name' => 'ARCADE']);
        Sanctum::actingAs($this->beManager());

        $this->patchJson("/api/v1/zones/{$this->zone->id}", ['plaque_id' => $other->id])->assertStatus(422)->assertJsonValidationErrors('plaque_id');
        $this->assertSame($this->plaque->id, $this->zone->fresh()->plaque_id);
    }

    public function test_the_be_technician_sees_every_plaque_and_the_other_technicians_do_not(): void
    {
        Plaque::create(['name' => 'ARCADE']);

        Sanctum::actingAs($this->beTechnician());
        $this->assertCount(2, $this->getJson('/api/v1/plaques')->json('data'));

        Sanctum::actingAs($this->user(Role::Technician, 'field@x.test', MissionType::Deployment));
        $this->assertCount(0, $this->getJson('/api/v1/plaques')->json('data'));
    }
}
