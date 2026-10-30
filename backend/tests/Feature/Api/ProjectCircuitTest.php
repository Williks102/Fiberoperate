<?php

namespace Tests\Feature\Api;

use App\Enums\MissionType;
use App\Enums\ProjectPhase;
use App\Enums\Role;
use App\Models\Plaque;
use App\Models\Project;
use App\Models\TechnicianSkill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectCircuitTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = Project::startFor(Plaque::create(['name' => 'ABOBO']), $this->login(Role::Admin, 'admin@x.test'));
    }

    private function login(Role $role, string $email, ?MissionType $skill = null): User
    {
        $user = User::firstOrCreate(['email' => $email], ['name' => ucfirst($email), 'password' => 'secret123', 'role' => $role]);
        if ($skill !== null) {
            TechnicianSkill::firstOrCreate(['user_id' => $user->id, 'mission_type' => $skill]);
        }
        Sanctum::actingAs($user);

        return $user;
    }

    private function submit()
    {
        return $this->postJson("/api/v1/projects/{$this->project->id}/submit");
    }

    private function decide(string $decision, ?string $reason = null)
    {
        return $this->postJson("/api/v1/projects/{$this->project->id}/decision", ['decision' => $decision, 'reason' => $reason]);
    }

    public function test_the_plan_goes_through_the_be_manager_the_dt_and_the_client_in_order(): void
    {
        $this->login(Role::Technician, 'dessin@x.test', MissionType::Drawing);
        $this->submit()->assertOk()->assertJsonPath('data.phase', 'be_manager_review');

        $this->login(Role::BeManager, 'be@x.test');
        $this->decide('approved')->assertOk()->assertJsonPath('data.phase', 'dt_review');

        $this->login(Role::Dt, 'dt@x.test');
        $this->decide('approved')->assertOk()->assertJsonPath('data.phase', 'client_review');

        // Le client n'a pas de compte : seul le DT enregistre son accord (le BE en est notifié).
        foreach ([Role::Dg, Role::BeManager] as $role) {
            $this->login($role, "{$role->value}@x.test");
            $this->decide('approved')->assertForbidden();
        }

        $this->login(Role::Dt, 'dt@x.test');
        $this->decide('approved')->assertOk()->assertJsonPath('data.phase', 'implantation_survey');

        $history = $this->project->history()->get();
        $this->assertSame(['pre_project', 'be_manager_review', 'dt_review', 'client_review', 'implantation_survey'], $history->map(fn ($h) => $h->to_phase->value)->all());
        $this->assertSame([null, null, 'approved', 'approved', 'approved'], $history->map->decision->all());
    }

    public function test_a_refusal_sends_the_project_back_to_the_pre_project_with_a_mandatory_reason(): void
    {
        $this->login(Role::BeManager, 'be@x.test');
        $this->submit()->assertOk();

        $this->decide('rejected')->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->assertSame(ProjectPhase::BeManagerReview, $this->project->fresh()->phase);

        $this->decide('rejected', 'Plan incomplet pour ABB02')->assertOk()->assertJsonPath('data.phase', 'pre_project');
        $last = $this->project->history()->get()->last();
        $this->assertSame('rejected', $last->decision);
        $this->assertSame('Plan incomplet pour ABB02', $last->reason);
        $this->assertSame(ProjectPhase::BeManagerReview, $last->from_phase);

        $this->submit()->assertOk();
    }

    public function test_each_decision_belongs_to_its_role(): void
    {
        $this->login(Role::BeManager, 'be@x.test');
        $this->submit()->assertOk();

        foreach ([Role::Dt, Role::Dg, Role::Supervisor, Role::Coordinator, Role::Technician] as $i => $role) {
            $this->login($role, "no{$i}@x.test");
            $this->decide('approved')->assertForbidden();
        }
        $this->login(Role::Technician, 'releve@x.test', MissionType::Survey);
        $this->decide('approved')->assertForbidden();
        $this->assertSame(ProjectPhase::BeManagerReview, $this->project->fresh()->phase);

        $this->login(Role::BeManager, 'be@x.test');
        $this->decide('approved')->assertOk();
        $this->login(Role::BeManager, 'be@x.test');
        $this->decide('approved')->assertForbidden();
    }

    public function test_only_the_be_and_the_administrator_submit_and_only_from_the_pre_project(): void
    {
        foreach ([Role::Dg, Role::Dt, Role::Supervisor, Role::Coordinator, Role::Technician] as $i => $role) {
            $this->login($role, "no{$i}@x.test");
            $this->submit()->assertForbidden();
        }
        $this->login(Role::Technician, 'releve@x.test', MissionType::Survey);
        $this->submit()->assertForbidden();
        $this->assertSame(ProjectPhase::PreProject, $this->project->fresh()->phase);

        $this->login(Role::Admin, 'admin@x.test');
        $this->submit()->assertOk();
        $this->submit()->assertForbidden();
    }

    public function test_a_decision_outside_a_validation_phase_or_sent_twice_is_refused_cleanly(): void
    {
        $this->login(Role::Admin, 'admin@x.test');
        $this->decide('approved')->assertForbidden();

        $this->submit()->assertOk();
        $this->decide('approved')->assertOk();
        $this->decide('approved')->assertOk()->assertJsonPath('data.phase', 'client_review');
        $this->decide('approved')->assertOk()->assertJsonPath('data.phase', 'implantation_survey');
        $this->decide('approved')->assertForbidden();
        $this->assertSame(5, $this->project->history()->count());
    }

    public function test_the_sheet_tells_which_actions_the_user_may_take(): void
    {
        $this->login(Role::BeManager, 'be@x.test');
        $this->getJson("/api/v1/projects/{$this->project->id}")->assertJsonPath('data.actions', ['submit' => true, 'decide' => false, 'advance' => false, 'advance_to' => null]);

        $this->submit()->assertOk();
        $this->getJson("/api/v1/projects/{$this->project->id}")->assertJsonPath('data.actions', ['submit' => false, 'decide' => true, 'advance' => false, 'advance_to' => null]);

        $this->login(Role::Dt, 'dt@x.test');
        $this->getJson("/api/v1/projects/{$this->project->id}")->assertJsonPath('data.actions', ['submit' => false, 'decide' => false, 'advance' => false, 'advance_to' => null]);
    }
}
