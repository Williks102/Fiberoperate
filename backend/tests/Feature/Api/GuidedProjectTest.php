<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Jdv;
use App\Models\Olt;
use App\Models\Pco;
use App\Models\Plaque;
use App\Models\Project;
use App\Models\Sro;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GuidedProjectTest extends TestCase
{
    use RefreshDatabase;

    private function login(Role $role): User
    {
        $user = User::create(['name' => 'u', 'email' => "{$role->value}@x.test", 'password' => 'secret123', 'role' => $role]);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_the_plaque_is_born_with_the_project_its_new_olt_and_generated_zones(): void
    {
        $this->login(Role::BeManager);

        $this->postJson('/api/v1/projects', [
            'plaque_name' => 'ABOBO',
            'olt_name' => 'OLT-ABOBO-1',
            'zones' => [
                ['code' => 'ABB01', 'pco_count' => 64, 'sro_code' => 'ABB456'],
                ['code' => 'ABB02'],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.phase', 'pre_project')
            ->assertJsonPath('data.plaque.name', 'ABOBO');

        $plaque = Plaque::where('name', 'ABOBO')->firstOrFail();
        $this->assertSame('OLT-ABOBO-1', $plaque->olt->name);
        $this->assertSame(['ABB01', 'ABB02'], Zone::where('plaque_id', $plaque->id)->orderBy('code')->pluck('code')->all());
        $this->assertSame(8, Jdv::count());
        $this->assertSame(64, Pco::count());
        $this->assertSame('ABB456', Sro::firstOrFail()->code);
        $this->assertSame(1, Project::where('plaque_id', $plaque->id)->count());
    }

    public function test_an_existing_olt_is_chosen_without_creating_another(): void
    {
        $this->login(Role::Admin);
        $olt = Olt::create(['name' => 'OLT-1']);

        $this->postJson('/api/v1/projects', ['plaque_name' => 'ABOBO', 'olt_id' => $olt->id])->assertCreated();

        $this->assertSame($olt->id, Plaque::firstOrFail()->olt_id);
        $this->assertSame(1, Olt::count());
    }

    public function test_the_olt_is_optional(): void
    {
        $this->login(Role::Admin);
        $this->postJson('/api/v1/projects', ['plaque_name' => 'ABOBO'])->assertCreated();
        $this->assertNull(Plaque::firstOrFail()->olt_id);
    }

    public function test_a_failure_creates_nothing(): void
    {
        $this->login(Role::Admin);
        Sro::create(['zone_id' => Zone::create(['plaque_id' => Plaque::create(['name' => 'FAYA'])->id, 'code' => 'FAY03'])->id, 'code' => 'ABB456']);

        $this->postJson('/api/v1/projects', [
            'plaque_name' => 'ABOBO', 'olt_name' => 'OLT-X',
            'zones' => [['code' => 'ABB01', 'pco_count' => 8, 'sro_code' => 'abb-456']],
        ])->assertStatus(422)->assertJsonValidationErrors('zones.0.sro_code');

        $this->assertSame(1, Plaque::count());
        $this->assertSame(0, Olt::count());
        $this->assertSame(0, Project::count());
    }

    public function test_input_errors_are_reported_in_french_per_field(): void
    {
        $this->login(Role::Admin);
        Plaque::create(['name' => 'ABOBO']);
        Olt::create(['name' => 'OLT-1']);

        $this->postJson('/api/v1/projects', ['plaque_name' => 'ABOBO'])->assertStatus(422)->assertJsonValidationErrors('plaque_name');
        $this->postJson('/api/v1/projects', ['plaque_name' => 'COCODY', 'olt_name' => 'OLT-1'])->assertStatus(422)->assertJsonValidationErrors('olt_name');
        $this->postJson('/api/v1/projects', ['plaque_name' => 'COCODY', 'zones' => [['code' => 'COC01'], ['code' => 'coc-01']]])
            ->assertStatus(422)->assertJsonValidationErrors('zones.1.code');
        $this->postJson('/api/v1/projects', ['plaque_name' => 'COCODY', 'zones' => [['code' => 'COC01', 'pco_count' => 8]]])
            ->assertStatus(422)->assertJsonValidationErrors('zones.0.sro_code');
        $this->postJson('/api/v1/projects', [])->assertStatus(422)->assertJsonValidationErrors(['plaque_id', 'plaque_name']);
        $this->assertSame(0, Project::count());
    }

    public function test_an_existing_plaque_and_a_new_one_cannot_be_mixed(): void
    {
        $this->login(Role::Admin);
        $plaque = Plaque::create(['name' => 'FAYA']);

        $this->postJson('/api/v1/projects', ['plaque_id' => $plaque->id, 'plaque_name' => 'ABOBO'])->assertStatus(422);
        $this->postJson('/api/v1/projects', ['plaque_id' => $plaque->id, 'zones' => [['code' => 'FAY03']]])->assertStatus(422);
        $this->assertSame(0, Project::count());
    }

    public function test_only_the_be_manager_and_the_administrator_create_projects(): void
    {
        foreach ([Role::Dg, Role::Dt, Role::Supervisor, Role::Coordinator, Role::Technician] as $role) {
            $this->login($role);
            $this->postJson('/api/v1/projects', ['plaque_name' => 'ABOBO'])->assertForbidden();
        }
        $this->assertSame(0, Plaque::count());
    }
}
