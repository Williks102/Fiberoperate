<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Jdv;
use App\Models\Olt;
use App\Models\Pco;
use App\Models\Plaque;
use App\Models\Team;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Matrice rôle × périmètre : superviseur = sa plaque, coordinateur = ses équipes. */
class AccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisorA;

    private User $supervisorB;

    private Plaque $plaqueA;

    private Plaque $plaqueB;

    private Pco $pcoA;

    private Pco $pcoB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supervisorA = $this->user(Role::Supervisor, 'sa@x.test');
        $this->supervisorB = $this->user(Role::Supervisor, 'sb@x.test');
        $this->plaqueA = Plaque::create(['name' => 'FAYA', 'supervisor_id' => $this->supervisorA->id]);
        $this->plaqueB = Plaque::create(['name' => 'ARCADE', 'supervisor_id' => $this->supervisorB->id]);
        $this->pcoA = $this->pco($this->plaqueA, 'FAY03', 'FAY03_PCO001');
        $this->pcoB = $this->pco($this->plaqueB, 'ACA003', 'ACA003_PCO001');
    }

    private function user(Role $role, string $email, array $extra = []): User
    {
        return User::create($extra + ['name' => $email, 'email' => $email, 'password' => 'secret123', 'role' => $role]);
    }

    private function pco(Plaque $plaque, string $zoneCode, string $pcoCode): Pco
    {
        $zone = Zone::create(['plaque_id' => $plaque->id, 'code' => $zoneCode]);
        $jdv = Jdv::create(['zone_id' => $zone->id, 'code' => 'SPL001']);

        return Pco::create(['jdv_id' => $jdv->id, 'code' => $pcoCode]);
    }

    private function ids(string $uri): array
    {
        return collect($this->getJson($uri)->assertOk()->json('data'))->pluck('id')->sort()->values()->all();
    }

    public function test_supervisor_sees_only_their_plaque_and_its_content(): void
    {
        Sanctum::actingAs($this->supervisorA);

        $this->assertSame([$this->plaqueA->id], $this->ids('/api/v1/plaques'));
        $this->assertSame([$this->pcoA->id], $this->ids('/api/v1/pcos'));
        $this->assertCount(1, $this->ids('/api/v1/zones'));
        $this->assertCount(1, $this->ids('/api/v1/jdvs'));

        $this->getJson('/api/v1/plaques/'.$this->plaqueB->id)->assertForbidden();
        $this->getJson('/api/v1/pcos/'.$this->pcoB->id)->assertForbidden();
        $this->getJson('/api/v1/pcos/'.$this->pcoA->id)->assertOk();
    }

    public function test_global_roles_see_every_plaque(): void
    {
        foreach ([Role::Admin, Role::Dg, Role::Dt, Role::Coordinator, Role::BeManager] as $i => $role) {
            Sanctum::actingAs($this->user($role, "g{$i}@x.test"));
            $this->assertCount(2, $this->ids('/api/v1/plaques'), $role->value);
            $this->assertCount(2, $this->ids('/api/v1/pcos'), $role->value);
        }
    }

    public function test_deployment_technician_sees_their_supervisors_plaque(): void
    {
        $tech = $this->user(Role::Technician, 'td@x.test', ['manager_id' => $this->supervisorA->id]);
        $orphan = $this->user(Role::Technician, 'to@x.test');

        Sanctum::actingAs($tech);
        $this->assertSame([$this->plaqueA->id], $this->ids('/api/v1/plaques'));

        Sanctum::actingAs($orphan);
        $this->assertSame([], $this->ids('/api/v1/plaques'));
    }

    public function test_olts_are_hidden_from_technicians(): void
    {
        Olt::create(['name' => 'OLT1']);

        Sanctum::actingAs($this->user(Role::Technician, 't@x.test'));
        $this->getJson('/api/v1/olts')->assertForbidden();

        Sanctum::actingAs($this->supervisorA);
        $this->getJson('/api/v1/olts')->assertOk();
    }

    public function test_only_admin_can_write_the_network(): void
    {
        $payload = ['name' => 'NOUVELLE'];

        foreach ([Role::Dg, Role::Supervisor, Role::Coordinator, Role::Technician] as $i => $role) {
            Sanctum::actingAs($this->user($role, "w{$i}@x.test"));
            $this->patchJson('/api/v1/plaques/'.$this->plaqueA->id, $payload)->assertForbidden();
            $this->deleteJson('/api/v1/plaques/'.$this->plaqueA->id)->assertForbidden();
        }

        Sanctum::actingAs($this->user(Role::Admin, 'adm@x.test'));
        $this->patchJson('/api/v1/plaques/'.$this->plaqueA->id, ['name' => 'FAYA 2'])->assertOk();
        $this->deleteJson('/api/v1/plaques/'.$this->plaqueA->id)->assertNoContent();
        $this->assertSoftDeleted($this->plaqueA);
    }

    public function test_coordinator_sees_only_their_teams(): void
    {
        $coordA = $this->user(Role::Coordinator, 'ca@x.test');
        $coordB = $this->user(Role::Coordinator, 'cb@x.test');
        $teamA = Team::create(['name' => 'Équipe A', 'coordinator_id' => $coordA->id]);
        Team::create(['name' => 'Équipe B', 'coordinator_id' => $coordB->id]);

        Sanctum::actingAs($coordA);
        $this->assertSame([$teamA->id], $this->ids('/api/v1/teams'));
        $this->getJson('/api/v1/teams/'.$teamA->id)->assertOk();
        $this->postJson('/api/v1/teams', ['name' => 'X'])->assertForbidden();

        Sanctum::actingAs($this->user(Role::Dg, 'dg@x.test'));
        $this->assertCount(2, $this->ids('/api/v1/teams'));

        Sanctum::actingAs($this->user(Role::Technician, 'tt@x.test'));
        $this->getJson('/api/v1/teams')->assertForbidden();
    }

    public function test_forbidden_user_never_sees_validation_details(): void
    {
        Sanctum::actingAs($this->supervisorA);

        $this->postJson('/api/v1/pcos', [])->assertForbidden();
    }

    public function test_pcos_are_paginated_and_filterable(): void
    {
        Sanctum::actingAs($this->user(Role::Admin, 'adm@x.test'));
        $zone = $this->pcoA->zone;
        $jdv = $this->pcoA->jdv;
        foreach (range(2, 30) as $n) {
            Pco::create(['jdv_id' => $jdv->id, 'code' => sprintf('FAY03_PCO%03d', $n)]);
        }

        $page = $this->getJson('/api/v1/pcos?per_page=10')->assertOk();
        $this->assertCount(10, $page->json('data'));
        $this->assertSame(31, $page->json('meta.total'));

        $this->assertCount(1, $this->getJson('/api/v1/pcos?q=FAY03_PCO-030')->json('data'));
        $this->assertCount(30, $this->getJson('/api/v1/pcos?zone_id='.$zone->id.'&per_page=100')->json('data'));
    }
}
