<?php

namespace Tests\Feature\Api;

use App\Enums\MissionType;
use App\Enums\Role;
use App\Models\Jdv;
use App\Models\Pco;
use App\Models\Plaque;
use App\Models\TechnicianSkill;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PcoMeasureTest extends TestCase
{
    use RefreshDatabase;

    private Pco $pco;

    protected function setUp(): void
    {
        parent::setUp();
        $zone = Zone::create(['plaque_id' => Plaque::create(['name' => 'ABOBO'])->id, 'code' => 'ABB01']);
        $jdv = Jdv::create(['zone_id' => $zone->id, 'code' => 'SPL001']);
        $this->pco = Pco::create(['jdv_id' => $jdv->id, 'code' => 'ABB01_PCO001', 'latitude' => 5.3600000, 'longitude' => -4.0083000]);
    }

    private function login(Role $role, string $email, ?MissionType $skill = null): User
    {
        $user = User::create(['name' => ucfirst($email), 'email' => $email, 'password' => 'secret123', 'role' => $role]);
        if ($skill !== null) {
            TechnicianSkill::create(['user_id' => $user->id, 'mission_type' => $skill]);
        }
        Sanctum::actingAs($user);

        return $user;
    }

    private function measure(array $body = ['latitude' => 5.3601, 'longitude' => -4.0084])
    {
        return $this->putJson("/api/v1/pcos/{$this->pco->id}/measured-position", $body);
    }

    public function test_a_survey_technician_records_the_measured_position_with_author_date_and_deviation(): void
    {
        $author = $this->login(Role::Technician, 'releve@x.test', MissionType::Survey);

        $this->measure()->assertOk()
            ->assertJsonPath('data.measured_latitude', '5.3601000')
            ->assertJsonPath('data.measured_by.id', $author->id)
            ->assertJsonPath('data.latitude', '5.3600000');

        $fresh = $this->pco->fresh();
        $this->assertNotNull($fresh->measured_at);
        $this->assertSame(16, $fresh->deviationMeters());
    }

    public function test_the_deviation_is_empty_when_a_position_is_missing(): void
    {
        $this->pco->update(['latitude' => null, 'longitude' => null]);
        $this->login(Role::BeManager, 'be@x.test');

        $this->measure()->assertOk()->assertJsonPath('data.deviation_m', null);
    }

    public function test_the_position_must_be_complete_in_ivory_coast_and_never_corrected(): void
    {
        $this->login(Role::Admin, 'admin@x.test');

        $this->measure(['latitude' => -4.0084, 'longitude' => 5.3601])->assertStatus(422)->assertJsonValidationErrors('latitude');
        $this->measure(['latitude' => 48.85, 'longitude' => 2.35])->assertStatus(422)->assertJsonValidationErrors('latitude');
        $this->measure(['latitude' => 5.36])->assertStatus(422)->assertJsonValidationErrors('longitude');
        $this->assertNull($this->pco->fresh()->measured_at);
    }

    public function test_who_may_measure(): void
    {
        foreach ([
            [Role::Admin, null], [Role::BeManager, null],
            [Role::Technician, MissionType::Survey], [Role::Technician, MissionType::Drawing],
        ] as $i => [$role, $skill]) {
            $this->login($role, "ok{$i}@x.test", $skill);
            $this->measure()->assertOk();
        }

        foreach ([
            [Role::Dg, null], [Role::Dt, null], [Role::Supervisor, null], [Role::Coordinator, null],
            [Role::Technician, null], [Role::Technician, MissionType::Deployment],
        ] as $i => [$role, $skill]) {
            $this->login($role, "ko{$i}@x.test", $skill);
            $this->measure()->assertForbidden();
        }
    }

    public function test_survey_technicians_see_the_whole_network_and_the_olts(): void
    {
        $this->login(Role::Technician, 'releve@x.test', MissionType::Survey);

        $this->assertCount(1, $this->getJson('/api/v1/plaques')->json('data'));
        $this->getJson('/api/v1/olts')->assertOk();
        $this->getJson("/api/v1/pcos/{$this->pco->id}")->assertOk();
    }
}
