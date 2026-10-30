<?php

namespace Tests\Feature\Api;

use App\Enums\MissionType;
use App\Enums\ProjectPhase;
use App\Enums\Role;
use App\Models\Plaque;
use App\Models\PriceItem;
use App\Models\Project;
use App\Models\ProjectQuoteLine;
use App\Models\TechnicianSkill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectQuoteTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private PriceItem $pole;

    private PriceItem $cable;

    private PriceItem $unpriced;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = Project::startFor(Plaque::create(['name' => 'ABOBO']), $this->login(Role::Admin, 'admin@x.test'));
        $this->pole = PriceItem::create(['kind' => 'service', 'section' => 'POTEAUX', 'ref' => 'POT02', 'designation' => 'Plantation poteau béton', 'unit' => 'u', 'unit_price' => 35000]);
        $this->cable = PriceItem::create(['kind' => 'supply', 'section' => 'CABLES', 'ref' => 'CFOAE03', 'designation' => 'Câble aérien 24FO', 'unit' => 'ml', 'unit_price' => 720]);
        $this->unpriced = PriceItem::create(['kind' => 'supply', 'section' => 'DIVERS', 'ref' => null, 'designation' => 'Article sans prix', 'unit' => 'u', 'unit_price' => null]);
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

    private function save(array $body)
    {
        return $this->putJson("/api/v1/projects/{$this->project->id}/quote/lines", $body);
    }

    private function quote()
    {
        return $this->getJson("/api/v1/projects/{$this->project->id}/quote");
    }

    private function phase(ProjectPhase $phase): void
    {
        $this->project->forceFill(['phase' => $phase])->save();
    }

    public function test_lines_copy_the_catalogue_price_and_totals_are_computed(): void
    {
        $this->login(Role::BeManager, 'be@x.test');

        $this->save(['price_item_id' => $this->pole->id, 'study_quantity' => 64])->assertSuccessful()
            ->assertJsonPath('data.unit_price', 35000)->assertJsonPath('data.study_total', 2240000)->assertJsonPath('data.done_total', null);
        $this->save(['price_item_id' => $this->cable->id, 'study_quantity' => 1100])->assertSuccessful();
        $this->save(['price_item_id' => $this->unpriced->id, 'study_quantity' => 3])->assertSuccessful()->assertJsonPath('data.study_total', null);

        $data = $this->quote()->assertOk()->json('data');
        $this->assertCount(3, $data['lines']);
        $this->assertSame(['study' => 2240000 + 792000, 'done' => 0, 'gap' => 0, 'missing_price' => 1], $data['totals']);
        $this->assertSame(['study' => true, 'done' => false], $data['can']);
    }

    public function test_a_catalogue_price_change_never_moves_an_existing_quote(): void
    {
        $this->login(Role::BeManager, 'be@x.test');
        $this->save(['price_item_id' => $this->pole->id, 'study_quantity' => 10])->assertSuccessful();

        $this->pole->update(['unit_price' => 50000]);

        $this->assertSame(350000, $this->quote()->json('data.lines.0.study_total'));
        $this->save(['price_item_id' => $this->pole->id, 'study_quantity' => 20])->assertSuccessful()->assertJsonPath('data.study_total', 700000);
        $this->assertSame(1, $this->project->fresh()->id === null ? 0 : ProjectQuoteLine::count());
    }

    public function test_the_study_is_frozen_once_sent_for_validation_and_the_done_quantities_open_at_the_deployment(): void
    {
        $this->login(Role::BeManager, 'be@x.test');
        $this->save(['price_item_id' => $this->pole->id, 'study_quantity' => 10])->assertSuccessful();

        $this->phase(ProjectPhase::BeManagerReview);
        $this->save(['price_item_id' => $this->pole->id, 'study_quantity' => 11])->assertStatus(422)->assertJsonValidationErrors('study_quantity');
        $this->save(['price_item_id' => $this->pole->id, 'done_quantity' => 9])->assertStatus(422)->assertJsonValidationErrors('done_quantity');

        $this->phase(ProjectPhase::Deployment);
        $this->save(['price_item_id' => $this->pole->id, 'done_quantity' => 12])->assertSuccessful()
            ->assertJsonPath('data.done_total', 420000)->assertJsonPath('data.gap_total', 70000);
        $this->save(['price_item_id' => $this->pole->id, 'study_quantity' => 11])->assertStatus(422);

        $this->assertSame(['study' => 350000, 'done' => 420000, 'gap' => 70000, 'missing_price' => 0], $this->quote()->json('data.totals'));
        $this->assertSame(['study' => false, 'done' => true], $this->quote()->json('data.can'));
    }

    public function test_a_line_is_removed_during_the_pre_project_only(): void
    {
        $this->login(Role::BeManager, 'be@x.test');
        $id = $this->save(['price_item_id' => $this->pole->id, 'study_quantity' => 10])->json('data.id');

        $this->phase(ProjectPhase::DtReview);
        $this->deleteJson("/api/v1/projects/{$this->project->id}/quote/lines/{$id}")->assertForbidden();

        $this->phase(ProjectPhase::PreProject);
        $this->deleteJson("/api/v1/projects/{$this->project->id}/quote/lines/{$id}")->assertNoContent();
        $this->assertCount(0, $this->quote()->json('data.lines'));
    }

    public function test_quantities_are_validated(): void
    {
        $this->login(Role::Admin, 'admin@x.test');

        $this->save([])->assertStatus(422)->assertJsonValidationErrors('price_item_id');
        $this->save(['price_item_id' => $this->pole->id])->assertStatus(422)->assertJsonValidationErrors('study_quantity');
        $this->save(['price_item_id' => $this->pole->id, 'study_quantity' => -1])->assertStatus(422)->assertJsonValidationErrors('study_quantity');
        $this->save(['price_item_id' => $this->pole->id, 'study_quantity' => 1.5])->assertStatus(422)->assertJsonValidationErrors('study_quantity');
        $this->save(['price_item_id' => 'x', 'study_quantity' => 1])->assertStatus(422)->assertJsonValidationErrors('price_item_id');
    }

    public function test_who_sees_and_who_writes_the_quote(): void
    {
        $this->login(Role::BeManager, 'be@x.test');
        $this->save(['price_item_id' => $this->pole->id, 'study_quantity' => 10])->assertSuccessful();

        foreach ([Role::Admin, Role::Dg, Role::Dt, Role::BeManager] as $i => $role) {
            $this->login($role, "r{$i}@x.test");
            $this->quote()->assertOk();
        }
        $this->login(Role::Technician, 'dessin@x.test', MissionType::Drawing);
        $this->quote()->assertOk();
        $this->save(['price_item_id' => $this->cable->id, 'study_quantity' => 5])->assertSuccessful();

        foreach ([[Role::Supervisor, null], [Role::Coordinator, null], [Role::Technician, null], [Role::Technician, MissionType::Survey]] as $i => [$role, $skill]) {
            $this->login($role, "no{$i}@x.test", $skill);
            $this->quote()->assertForbidden();
            $this->save(['price_item_id' => $this->cable->id, 'study_quantity' => 5])->assertForbidden();
        }
        foreach ([Role::Dg, Role::Dt] as $i => $role) {
            $this->login($role, "ro{$i}@x.test");
            $this->save(['price_item_id' => $this->cable->id, 'study_quantity' => 5])->assertForbidden();
        }
    }
}
