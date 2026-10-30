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

class ProjectAdvanceNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private User $be;

    private User $dt;

    private User $supervisor;

    private User $designer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->be = $this->user(Role::BeManager, 'be@x.test');
        $this->dt = $this->user(Role::Dt, 'dt@x.test');
        $this->supervisor = $this->user(Role::Supervisor, 'sup@x.test');
        $this->designer = $this->user(Role::Technician, 'dessin@x.test', MissionType::Drawing);
        $this->project = Project::startFor(Plaque::create(['name' => 'ABOBO', 'supervisor_id' => $this->supervisor->id]), $this->be);
    }

    private function user(Role $role, string $email, ?MissionType $skill = null): User
    {
        $user = User::create(['name' => ucfirst($email), 'email' => $email, 'password' => 'secret123', 'role' => $role]);
        if ($skill !== null) {
            TechnicianSkill::create(['user_id' => $user->id, 'mission_type' => $skill]);
        }

        return $user;
    }

    private function act(User $user, string $action, array $body = [])
    {
        Sanctum::actingAs($user);

        return $this->postJson("/api/v1/projects/{$this->project->id}/{$action}", $body);
    }

    private function titles(User $user): array
    {
        return $user->notifications()->pluck('data')->map(fn ($d) => $d['title'])->all();
    }

    public function test_each_step_of_the_circuit_notifies_the_right_people(): void
    {
        $this->act($this->designer, 'submit')->assertOk();
        $this->assertSame(['Avant-projet à valider'], $this->titles($this->be));
        $this->assertSame([], $this->titles($this->dt));

        $this->act($this->be, 'decision', ['decision' => 'approved'])->assertOk();
        $this->assertSame(['Avant-projet à valider'], $this->titles($this->dt));

        $this->act($this->dt, 'decision', ['decision' => 'approved'])->assertOk();
        $this->assertContains('En attente du client', $this->titles($this->be));
        $this->assertSame(['En attente du client'], $this->titles($this->designer));

        $this->act($this->dt, 'decision', ['decision' => 'approved'])->assertOk();
        $this->assertContains('Accord du client enregistré', $this->titles($this->be));
        $this->assertCount(1, $this->titles($this->dt), 'le DT ne reçoit pas sa propre décision');
    }

    public function test_a_refusal_notifies_the_be_team_with_its_reason_and_never_the_author(): void
    {
        $this->act($this->designer, 'submit')->assertOk();
        $this->act($this->be, 'decision', ['decision' => 'rejected', 'reason' => 'Plan incomplet'])->assertOk();

        $this->assertSame(['Avant-projet refusé'], $this->titles($this->designer));
        $this->assertStringContainsString('Plan incomplet', $this->designer->notifications()->first()->data['message']);
        $this->assertSame(['Avant-projet à valider'], $this->titles($this->be), 'le chef du BE ne reçoit pas sa propre décision');
        $this->assertSame("/projets/{$this->project->id}", $this->designer->notifications()->first()->data['link']);
    }

    public function test_the_be_moves_the_project_forward_after_the_client_and_the_supervisor_is_told(): void
    {
        $this->project->forceFill(['phase' => ProjectPhase::ImplantationSurvey])->save();

        $this->act($this->be, 'advance')->assertOk()->assertJsonPath('data.phase', 'deployment');
        $this->assertSame(['Déploiement lancé'], $this->titles($this->supervisor));

        $this->act($this->designer, 'advance')->assertOk()->assertJsonPath('data.phase', 'as_built_survey');
        $this->act($this->be, 'advance')->assertOk()->assertJsonPath('data.phase', 'in_operation');
        $this->act($this->be, 'advance')->assertForbidden();

        $this->assertSame(['implantation_survey', 'deployment', 'as_built_survey', 'in_operation'], $this->project->history()->get()->slice(-3)->map(fn ($h) => $h->to_phase->value)->values()->prepend('implantation_survey')->all());
    }

    public function test_only_the_be_and_the_administrator_advance_and_never_from_a_validation_phase(): void
    {
        $this->act($this->be, 'advance')->assertForbidden();

        $this->project->forceFill(['phase' => ProjectPhase::ImplantationSurvey])->save();
        foreach ([Role::Dg, Role::Dt, Role::Supervisor, Role::Coordinator, Role::Technician] as $i => $role) {
            $this->act($this->user($role, "no{$i}@x.test"), 'advance')->assertForbidden();
        }
        $this->act($this->user(Role::Technician, 'releve@x.test', MissionType::Survey), 'advance')->assertForbidden();
        $this->assertSame(ProjectPhase::ImplantationSurvey, $this->project->fresh()->phase);
    }

    public function test_notifications_belong_to_their_owner_and_can_be_read(): void
    {
        $this->act($this->designer, 'submit')->assertOk();

        Sanctum::actingAs($this->be);
        $list = $this->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('meta.unread_count', 1)->assertJsonPath('data.0.read', false);
        $id = $list->json('data.0.id');

        Sanctum::actingAs($this->dt);
        $this->postJson("/api/v1/notifications/{$id}/read")->assertNotFound();
        $this->assertSame(0, $this->getJson('/api/v1/notifications')->json('meta.unread_count'));

        Sanctum::actingAs($this->be);
        $this->postJson("/api/v1/notifications/{$id}/read")->assertOk();
        $this->getJson('/api/v1/notifications')->assertJsonPath('meta.unread_count', 0)->assertJsonPath('data.0.read', true);
    }

    public function test_read_all_marks_every_notification_of_the_user(): void
    {
        $this->act($this->designer, 'submit')->assertOk();
        $this->act($this->be, 'decision', ['decision' => 'rejected', 'reason' => 'x'])->assertOk();
        $this->act($this->designer, 'submit')->assertOk();

        Sanctum::actingAs($this->be);
        $this->assertSame(2, $this->getJson('/api/v1/notifications')->json('meta.unread_count'));
        $this->postJson('/api/v1/notifications/read-all')->assertOk();
        $this->assertSame(0, $this->getJson('/api/v1/notifications')->json('meta.unread_count'));
    }
}
