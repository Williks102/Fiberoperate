<?php

namespace Tests\Feature\Api;

use App\Enums\PcoStatus;
use App\Enums\ProjectPhase;
use App\Enums\Role;
use App\Models\Pco;
use App\Models\Plaque;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectsApiTest extends TestCase
{
    use RefreshDatabase;

    private function login(Role $role, ?string $email = null): User
    {
        $user = User::create(['name' => ucfirst($role->value), 'email' => $email ?? "{$role->value}@x.test", 'password' => 'secret123', 'role' => $role]);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_a_project_starts_in_pre_project_with_its_first_history_line(): void
    {
        $author = $this->login(Role::BeManager);
        $plaque = Plaque::create(['name' => 'FAYA']);

        $this->postJson('/api/v1/projects', ['plaque_id' => $plaque->id])
            ->assertCreated()
            ->assertJsonPath('data.phase', 'pre_project')
            ->assertJsonPath('data.phase_label', 'Avant-projet')
            ->assertJsonPath('data.plaque.name', 'FAYA');

        $project = Project::firstOrFail();
        $history = $project->history()->get();
        $this->assertCount(1, $history);
        $this->assertNull($history[0]->from_phase);
        $this->assertSame(ProjectPhase::PreProject, $history[0]->to_phase);
        $this->assertSame($author->id, $history[0]->changed_by);
        $this->assertNotNull($project->phase_changed_at);
    }

    public function test_only_one_project_per_plaque_and_the_plaque_must_exist(): void
    {
        $this->login(Role::Admin);
        $plaque = Plaque::create(['name' => 'FAYA']);
        Project::startFor($plaque, User::first());

        $this->postJson('/api/v1/projects', ['plaque_id' => $plaque->id])
            ->assertStatus(422)->assertJsonPath('errors.plaque_id.0', 'Cette plaque a déjà un projet.');
        $this->postJson('/api/v1/projects', [])->assertStatus(422)->assertJsonValidationErrors('plaque_id');
        $this->postJson('/api/v1/projects', ['plaque_id' => '019a0000-0000-7000-8000-000000000000'])->assertStatus(422);
        $this->assertSame(1, Project::count());
    }

    public function test_only_admin_dt_and_be_manager_open_projects(): void
    {
        $plaque = Plaque::create(['name' => 'FAYA']);

        foreach ([Role::Dg, Role::Dt, Role::Supervisor, Role::Coordinator, Role::Technician] as $role) {
            $this->login($role);
            $this->postJson('/api/v1/projects', ['plaque_id' => $plaque->id])->assertForbidden();
        }
        $this->assertSame(0, Project::count());

        $this->login(Role::BeManager);
        $this->postJson('/api/v1/projects', ['plaque_id' => $plaque->id])->assertCreated();
    }

    public function test_visibility_follows_the_plaque_perimeter(): void
    {
        $supervisorA = User::create(['name' => 'SA', 'email' => 'sa@x.test', 'password' => 'secret123', 'role' => Role::Supervisor]);
        $supervisorB = User::create(['name' => 'SB', 'email' => 'sb@x.test', 'password' => 'secret123', 'role' => Role::Supervisor]);
        $faya = Plaque::create(['name' => 'FAYA', 'supervisor_id' => $supervisorA->id]);
        $arcade = Plaque::create(['name' => 'ARCADE', 'supervisor_id' => $supervisorB->id]);
        Project::startFor($faya, $supervisorA);
        Project::startFor($arcade, $supervisorA);

        $names = fn () => collect($this->getJson('/api/v1/projects')->assertOk()->json('data'))->pluck('plaque.name')->sort()->values()->all();

        Sanctum::actingAs($supervisorA);
        $this->assertSame(['FAYA'], $names());

        foreach ([Role::Admin, Role::Dg, Role::Dt, Role::BeManager] as $role) {
            $this->login($role);
            $this->assertSame(['ARCADE', 'FAYA'], $names(), $role->value);
        }
    }

    public function test_coordinator_and_technician_do_not_see_projects(): void
    {
        foreach ([Role::Coordinator, Role::Technician] as $role) {
            $this->login($role);
            $this->getJson('/api/v1/projects')->assertForbidden();
        }
    }

    public function test_filters_by_phase_and_plaque_name_and_is_paginated(): void
    {
        $admin = $this->login(Role::Admin);
        $faya = Plaque::create(['name' => 'FAYA']);
        $arcade = Plaque::create(['name' => 'ARCADE']);
        Project::startFor($faya, $admin);
        Project::startFor($arcade, $admin)->forceFill(['phase' => ProjectPhase::Deployment])->save();

        $this->assertCount(1, $this->getJson('/api/v1/projects?phase=deployment')->json('data'));
        $this->assertSame('ARCADE', $this->getJson('/api/v1/projects?phase=deployment')->json('data.0.plaque.name'));
        $this->assertSame('FAYA', $this->getJson('/api/v1/projects?q=fay')->json('data.0.plaque.name'));
        $this->assertSame(2, $this->getJson('/api/v1/projects?per_page=1')->json('meta.total'));
        $this->assertCount(2, $this->getJson('/api/v1/projects?phase=inconnue')->json('data'));
    }

    public function test_the_detail_returns_the_history_with_authors_and_labels(): void
    {
        $author = $this->login(Role::BeManager, 'chef@x.test');
        $project = Project::startFor(Plaque::create(['name' => 'FAYA']), $author);
        $project->history()->create([
            'from_phase' => ProjectPhase::PreProject,
            'to_phase' => ProjectPhase::BeManagerReview,
            'decision' => 'approved',
            'reason' => 'Dossier complet',
            'changed_by' => $author->id,
        ]);

        $response = $this->getJson("/api/v1/projects/{$project->id}")->assertOk();

        $response->assertJsonPath('data.plaque.name', 'FAYA')->assertJsonCount(2, 'data.history');
        $first = $response->json('data.history.0');
        $this->assertNull($first['from_phase']);
        $this->assertSame('Avant-projet', $first['to_phase_label']);
        $this->assertSame($author->name, $first['author']['name']);
        $second = $response->json('data.history.1');
        $this->assertSame('Validation du chef du BE', $second['to_phase_label']);
        $this->assertSame('approved', $second['decision']);
        $this->assertSame('Dossier complet', $second['reason']);
    }

    public function test_the_detail_respects_the_plaque_perimeter(): void
    {
        $owner = User::create(['name' => 'SA', 'email' => 'sa@x.test', 'password' => 'secret123', 'role' => Role::Supervisor]);
        $other = User::create(['name' => 'SB', 'email' => 'sb@x.test', 'password' => 'secret123', 'role' => Role::Supervisor]);
        $project = Project::startFor(Plaque::create(['name' => 'FAYA', 'supervisor_id' => $owner->id]), $owner);

        Sanctum::actingAs($owner);
        $this->getJson("/api/v1/projects/{$project->id}")->assertOk();

        Sanctum::actingAs($other);
        $this->getJson("/api/v1/projects/{$project->id}")->assertForbidden();

        $this->login(Role::Coordinator);
        $this->getJson("/api/v1/projects/{$project->id}")->assertForbidden();
        $this->getJson('/api/v1/projects/019a0000-0000-7000-8000-000000000000')->assertNotFound();
    }

    public function test_the_project_sheet_gives_the_characteristics_of_its_network(): void
    {
        $this->login(Role::BeManager);
        $this->postJson('/api/v1/projects', [
            'plaque_name' => 'ABOBO', 'olt_name' => 'OLT-1',
            'zones' => [['code' => 'ABB01', 'pco_count' => 16, 'sro_code' => 'ABB456'], ['code' => 'ABB02']],
        ])->assertCreated();

        $this->getJson('/api/v1/projects/'.Project::firstOrFail()->id)
            ->assertOk()
            ->assertJsonPath('data.network.olt.name', 'OLT-1')
            ->assertJsonPath('data.network.zones.0.sro', 'ABB456')
            ->assertJsonPath('data.network.zones.0.splitters', 2)
            ->assertJsonPath('data.network.zones.0.pcos', 16)
            ->assertJsonPath('data.network.zones.0.progress', ['to_pull' => 16, 'pulled' => 0, 'spliced' => 0, 'activated' => 0, 'measured' => 0])
            ->assertJsonPath('data.network.zones.1.splitters', 0)
            ->assertJsonPath('data.network.zones.1.sro', null);
    }

    public function test_the_sheet_compares_the_plan_with_the_progress_per_zone(): void
    {
        $this->login(Role::BeManager);
        $this->postJson('/api/v1/projects', [
            'plaque_name' => 'ABOBO',
            'zones' => [['code' => 'ABB01', 'pco_count' => 4, 'sro_code' => 'ABB456']],
        ])->assertCreated();

        $pcos = Pco::orderBy('code')->get();
        $pcos[0]->changeStatus(PcoStatus::Pulled);
        $pcos[1]->changeStatus(PcoStatus::Spliced);
        $pcos[2]->changeStatus(PcoStatus::Activated);
        $pcos[2]->forceFill(['measured_at' => now(), 'measured_latitude' => 5.36, 'measured_longitude' => -4.0])->save();

        $this->getJson('/api/v1/projects/'.Project::firstOrFail()->id)
            ->assertOk()
            ->assertJsonPath('data.network.zones.0.pcos', 4)
            ->assertJsonPath('data.network.zones.0.progress', ['to_pull' => 1, 'pulled' => 1, 'spliced' => 1, 'activated' => 1, 'measured' => 1]);
    }
}
