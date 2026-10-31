<?php

namespace Tests\Feature\Api;

use App\Enums\MissionType;
use App\Enums\Role;
use App\Models\Plaque;
use App\Models\Project;
use App\Models\ProjectDocumentVersion;
use App\Models\TechnicianSkill;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class ProjectDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = $this->login(Role::Admin, 'admin@x.test');
        $plaque = Plaque::create(['name' => 'ABOBO']);
        $this->project = Project::startFor($plaque, $admin);
        $this->zone = Zone::create(['plaque_id' => $plaque->id, 'code' => 'ABB01']);
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

    private function upload(array $body, string $name = 'plan.kmz', string $content = "PK\x03\x04plan")
    {
        return $this->post("/api/v1/projects/{$this->project->id}/documents", $body + ['file' => UploadedFile::fake()->createWithContent($name, $content)], ['Accept' => 'application/json']);
    }

    public function test_each_upload_adds_a_version_and_nothing_is_overwritten(): void
    {
        $this->login(Role::BeManager, 'be@x.test');

        $this->upload(['type' => 'kmz', 'comment' => 'Premier jet'])->assertCreated()->assertJsonPath('data.version', 1)->assertJsonPath('data.type_label', 'Plan KMZ');
        $this->upload(['type' => 'kmz'], 'plan-v2.kmz', "PK\x03\x04v2")->assertCreated()->assertJsonPath('data.version', 2);

        $versions = $this->getJson("/api/v1/projects/{$this->project->id}/documents")->assertOk()->json('data.versions');
        $this->assertSame([2, 1], array_column($versions, 'version'));
        $this->assertSame(['plan-v2.kmz', 'plan.kmz'], array_column($versions, 'file_name'));
        $this->assertArrayNotHasKey('content', $versions[0]);
    }

    public function test_versions_are_numbered_per_type_and_per_zone(): void
    {
        $this->login(Role::BeManager, 'be@x.test');
        $other = Zone::create(['plaque_id' => $this->project->plaque_id, 'code' => 'ABB02']);

        $this->upload(['type' => 'zone_plan', 'zone_id' => $this->zone->id], 'a.dwg', 'AC1032plan')->assertCreated()->assertJsonPath('data.version', 1);
        $this->upload(['type' => 'zone_plan', 'zone_id' => $this->zone->id], 'a2.dwg', 'AC1032plan2')->assertCreated()->assertJsonPath('data.version', 2);
        $this->upload(['type' => 'zone_plan', 'zone_id' => $other->id], 'b.dwg', 'AC1032plan')->assertCreated()->assertJsonPath('data.version', 1)->assertJsonPath('data.zone.code', 'ABB02');
        $this->upload(['type' => 'kmz'])->assertCreated()->assertJsonPath('data.version', 1);
    }

    public function test_a_version_cannot_be_modified_or_deleted(): void
    {
        $this->login(Role::Admin, 'admin@x.test');
        $id = $this->upload(['type' => 'kmz'])->json('data.id');
        $version = ProjectDocumentVersion::findOrFail($id);

        $this->expectException(LogicException::class);
        $version->update(['comment' => 'autre']);
    }

    public function test_deleting_a_version_is_refused_too(): void
    {
        $this->login(Role::Admin, 'admin@x.test');
        $version = ProjectDocumentVersion::findOrFail($this->upload(['type' => 'kmz'])->json('data.id'));

        $this->expectException(LogicException::class);
        $version->delete();
    }

    public function test_the_download_returns_the_exact_file(): void
    {
        $this->login(Role::BeManager, 'be@x.test');
        $bytes = "PK\x03\x04\x00\xFF binaire \x01\x02";
        $id = $this->upload(['type' => 'kmz'], 'plan.kmz', $bytes)->json('data.id');

        $response = $this->get("/api/v1/projects/{$this->project->id}/documents/{$id}/download");
        $response->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame($bytes, $response->streamedContent());
        $this->assertStringContainsString('plan.kmz', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_files_are_checked_for_size_extension_and_content(): void
    {
        $this->login(Role::Admin, 'admin@x.test');

        $this->upload(['type' => 'kmz'], 'virus.exe', 'MZ')->assertStatus(422)->assertJsonValidationErrors('file');
        $this->upload(['type' => 'kmz'], 'faux.kmz', 'pas un zip')->assertStatus(422)->assertJsonValidationErrors('file');
        $this->upload(['type' => 'zone_plan', 'zone_id' => $this->zone->id], 'faux.dwg', 'pas un dwg')->assertStatus(422)->assertJsonValidationErrors('file');
        $this->upload(['type' => 'kmz'], 'gros.kmz', 'PK'.str_repeat('x', 2100 * 1024))->assertStatus(422)->assertJsonValidationErrors('file');
        $this->post("/api/v1/projects/{$this->project->id}/documents", ['type' => 'kmz'], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('file');
        $this->assertSame(0, ProjectDocumentVersion::count());
    }

    public function test_the_zone_rules(): void
    {
        $this->login(Role::Admin, 'admin@x.test');
        $foreign = Zone::create(['plaque_id' => Plaque::create(['name' => 'ARCADE'])->id, 'code' => 'ACA003']);

        $this->upload(['type' => 'zone_plan'], 'a.dwg', 'AC1032')->assertStatus(422)->assertJsonValidationErrors('zone_id');
        $this->upload(['type' => 'zone_plan', 'zone_id' => $foreign->id], 'a.dwg', 'AC1032')->assertStatus(422)->assertJsonValidationErrors('zone_id');
        $this->upload(['type' => 'kmz', 'zone_id' => $this->zone->id])->assertStatus(422)->assertJsonValidationErrors('zone_id');
        $this->upload(['type' => 'inconnu'])->assertStatus(422)->assertJsonValidationErrors('type');
    }

    public function test_who_uploads_and_who_reads(): void
    {
        $this->login(Role::BeManager, 'be@x.test');
        $id = $this->upload(['type' => 'kmz'])->json('data.id');
        $this->upload(['type' => 'kmz'], 'p.kmz')->assertCreated();

        foreach ([[Role::Dt, null], [Role::Technician, MissionType::Drawing], [Role::Technician, MissionType::Survey]] as $i => [$role, $skill]) {
            $this->login($role, "r{$i}@x.test", $skill);
            $this->getJson("/api/v1/projects/{$this->project->id}/documents")->assertOk();
            $this->get("/api/v1/projects/{$this->project->id}/documents/{$id}/download")->assertOk();
        }

        foreach ([[Role::Dg, null], [Role::Dt, null], [Role::Supervisor, null], [Role::Coordinator, null], [Role::Technician, MissionType::Survey]] as $i => [$role, $skill]) {
            $this->login($role, "w{$i}@x.test", $skill);
            $this->upload(['type' => 'kmz'])->assertForbidden();
        }

        $this->login(Role::Technician, 'dessin@x.test', MissionType::Drawing);
        $this->upload(['type' => 'kmz'], 'd.kmz')->assertCreated();
        $this->login(Role::Coordinator, 'c@x.test');
        $this->getJson("/api/v1/projects/{$this->project->id}/documents")->assertForbidden();
    }

    public function test_the_dt_only_adds_the_client_proof_and_the_supervisor_of_the_plaque_reads(): void
    {
        $supervisor = User::create(['name' => 'S', 'email' => 'sup@x.test', 'password' => 'secret123', 'role' => Role::Supervisor]);
        $this->project->plaque->update(['supervisor_id' => $supervisor->id]);
        $this->login(Role::BeManager, 'be@x.test');
        $this->upload(['type' => 'kmz'])->assertCreated();

        $this->login(Role::Dt, 'dt@x.test');
        $this->upload(['type' => 'client_proof'], 'accord.pdf', '%PDF-1.4 accord')->assertCreated()->assertJsonPath('data.type_label', "Justificatif d'accord du client");
        $this->upload(['type' => 'kmz'])->assertForbidden();
        $uploadable = array_column($this->getJson("/api/v1/projects/{$this->project->id}/documents")->json('data.uploadable'), 'type');
        $this->assertSame(['client_proof'], $uploadable);

        Sanctum::actingAs($supervisor);
        $this->assertCount(2, $this->getJson("/api/v1/projects/{$this->project->id}/documents")->assertOk()->json('data.versions'));
        $this->upload(['type' => 'client_proof'], 'x.pdf', '%PDF')->assertForbidden();
    }

    public function test_the_be_technicians_now_open_projects(): void
    {
        $this->login(Role::Technician, 'dessin@x.test', MissionType::Drawing);
        $this->getJson('/api/v1/projects')->assertOk();
        $this->getJson("/api/v1/projects/{$this->project->id}")->assertOk();

        $this->login(Role::Technician, 'terrain@x.test', MissionType::Deployment);
        $this->getJson('/api/v1/projects')->assertForbidden();
    }
}
