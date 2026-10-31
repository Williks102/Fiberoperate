<?php

namespace Tests\Feature;

use App\Domain\Study\PlanInstallation;
use App\Domain\Study\PlanStudy;
use App\Enums\MissionType;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Enums\StudyResult;
use App\Models\CustomerOrder;
use App\Models\FieldOperation;
use App\Models\Jdv;
use App\Models\OutcomeReason;
use App\Models\Pco;
use App\Models\Plaque;
use App\Models\Study;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FieldSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $tech;

    private User $coordinator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->coordinator = $this->user(Role::Coordinator, 'Coord');
        $this->tech = $this->user(Role::Technician, 'Koffi', MissionType::Study, MissionType::CustomerConnection);
    }

    private function user(Role $role, string $name, MissionType ...$skills): User
    {
        $user = User::create(['name' => $name, 'email' => strtolower(str_replace(' ', '', $name)).'@x.test', 'password' => 'secret123', 'role' => $role]);
        foreach ($skills as $skill) {
            $user->skills()->create(['mission_type' => $skill->value]);
        }

        return $user;
    }

    private function pco(): Pco
    {
        $zone = Zone::firstOrCreate(['code' => 'FAY03'], ['plaque_id' => Plaque::firstOrCreate(['name' => 'FAYA'])->id]);

        return Pco::create(['zone_id' => $zone->id, 'jdv_id' => Jdv::firstOrCreate(['zone_id' => $zone->id, 'code' => 'SPL001'])->id, 'code' => 'FAY03_PCO001', 'number' => 1]);
    }

    private function inStudy(string $ref = 'RAB1', ?User $tech = null): CustomerOrder
    {
        $order = CustomerOrder::create(['customer_ref' => $ref, 'name' => 'Koné Awa']);
        app(PlanStudy::class)($order, $tech ?? $this->tech, now(), $this->coordinator);

        return $order->fresh();
    }

    private function scheduled(string $ref = 'RAB9'): CustomerOrder
    {
        $order = CustomerOrder::create(['customer_ref' => $ref, 'name' => 'Yao']);
        $order->changeStatus(OrderStatus::Studied, result: StudyResult::Feasible);
        app(PlanInstallation::class)($order, $this->tech, now(), $this->coordinator, "SN{$ref}", '27'.str_pad((string) crc32($ref), 8, '0'));

        return $order->fresh();
    }

    private function op(string $type, CustomerOrder $order, array $payload, array $extra = []): array
    {
        // Toute saisie du terrain déclare au moins une photo (obligatoire) ; un test qui veut tester l'absence la fournit à null.
        $payload += ['photo_uuids' => [(string) Str::uuid()]];

        return $extra + ['client_uuid' => (string) Str::uuid(), 'type' => $type, 'customer_order_id' => $order->id, 'occurred_at' => now()->subHours(3)->toIso8601String(), 'device' => 'Téléphone Koffi', 'payload' => array_filter($payload, fn ($v) => $v !== null)];
    }

    private function push(array ...$operations)
    {
        return $this->postJson('/api/v1/sync/push', ['operations' => $operations]);
    }

    public function test_a_study_result_entered_offline_is_applied_with_its_field_time_and_device(): void
    {
        $order = $this->inStudy();
        $pco = $this->pco();
        $at = now()->subHours(5)->startOfSecond();
        Sanctum::actingAs($this->tech);

        $this->push($this->op('study.result', $order, ['result' => 'feasible', 'pco_id' => $pco->id, 'concrete_poles_existing' => 2, 'latitude' => 5.3, 'longitude' => -4.0], ['occurred_at' => $at->toIso8601String()]))
            ->assertOk()->assertJsonPath('data.0.status', 'applied');

        $order->refresh();
        $this->assertSame(OrderStatus::Studied, $order->status);
        $this->assertSame(100, $order->distance_m);
        $line = $order->history()->where('to_status', 'studied')->firstOrFail();
        $this->assertSame('Téléphone Koffi', $line->device);
        $this->assertTrue($line->created_at->equalTo($at), "l'historique garde l'heure du terrain");
        $this->assertSame(1, Study::count());
        $this->assertSame('applied', FieldOperation::first()->status);
    }

    public function test_an_installation_result_is_applied(): void
    {
        $order = $this->scheduled();
        $reason = OutcomeReason::where('code', 'customer_absent')->firstOrFail();
        Sanctum::actingAs($this->tech);

        $this->push($this->op('installation.result', $order, ['result' => 'postponed', 'reason_id' => $reason->id, 'observation' => 'Repasser samedi']))
            ->assertOk()->assertJsonPath('data.0.status', 'applied');

        $this->assertSame(OrderStatus::Postponed, $order->fresh()->status);
    }

    public function test_the_same_operation_sent_twice_is_applied_once(): void
    {
        $order = $this->inStudy();
        Sanctum::actingAs($this->tech);
        $op = $this->op('study.result', $order, ['result' => 'customer_absent']);

        $this->push($op)->assertJsonPath('data.0.status', 'applied');
        $this->push($op)->assertOk()->assertJsonPath('data.0.status', 'duplicate')->assertJsonPath('data.0.original_status', 'applied');

        $this->assertSame(1, Study::count());
        $this->assertSame(1, FieldOperation::count());
        $this->assertCount(2, $order->history, "une seule ligne d'historique pour la mise en étude et une pour le résultat");
    }

    public function test_a_visit_taken_over_by_another_technician_is_a_conflict_and_the_entry_is_kept(): void
    {
        $order = $this->inStudy();
        $other = $this->user(Role::Technician, 'Autre', MissionType::Study);
        // Le coordinateur reprogramme l'étude chez un autre technicien pendant que le premier est hors ligne.
        $order->interventions()->first()->update(['technician_id' => $other->id]);
        Sanctum::actingAs($this->tech);
        $op = $this->op('study.result', $order, ['result' => 'customer_absent']);

        $this->push($op)->assertOk()->assertJsonPath('data.0.status', 'conflict')->assertJsonPath('data.0.error_code', 'NOT_ASSIGNED');

        $this->assertSame(OrderStatus::InStudy, $order->fresh()->status);
        $this->assertSame(0, Study::count());
        $kept = FieldOperation::first();
        $this->assertSame('conflict', $kept->status);
        $this->assertSame('customer_absent', $kept->payload['result'], 'la saisie du technicien est gardée, jamais perdue');

        $this->push($op)->assertJsonPath('data.0.status', 'duplicate')->assertJsonPath('data.0.original_status', 'conflict');
    }

    public function test_a_result_already_entered_by_the_coordinator_is_a_conflict(): void
    {
        $order = $this->inStudy();
        $this->actingAs($this->coordinator);
        Sanctum::actingAs($this->coordinator);
        $this->postJson("/api/v1/customer-orders/{$order->id}/study-result", ['result' => 'customer_absent'])->assertOk();

        Sanctum::actingAs($this->coordinator);
        $this->push($this->op('study.result', $order, ['result' => 'customer_absent']))->assertJsonPath('data.0.status', 'conflict')->assertJsonPath('data.0.error_code', 'VISIT_CHANGED');

        $this->assertSame(1, Study::count());
    }

    public function test_an_unusable_entry_is_rejected_and_kept_without_touching_the_order(): void
    {
        $order = $this->inStudy();
        Sanctum::actingAs($this->tech);

        $this->push($this->op('study.result', $order, ['result' => 'feasible']))
            ->assertOk()->assertJsonPath('data.0.status', 'rejected')->assertJsonPath('data.0.error_code', 'VALIDATION');
        $this->push($this->op('study.result', $order, ['result' => 'feasible', 'pco_id' => $this->pco()->id, 'latitude' => -4.0, 'longitude' => 5.3]))
            ->assertJsonPath('data.0.status', 'rejected');

        $this->assertSame(OrderStatus::InStudy, $order->fresh()->status);
        $this->assertSame(2, FieldOperation::where('status', 'rejected')->count());
    }

    public function test_one_bad_operation_does_not_stop_the_others_and_results_follow_the_order_received(): void
    {
        $a = $this->inStudy('A');
        $b = $this->inStudy('B');
        $c = $this->scheduled('C');
        Sanctum::actingAs($this->tech);

        $late = $this->op('study.result', $a, ['result' => 'customer_absent'], ['occurred_at' => now()->subHour()->toIso8601String()]);
        $bad = $this->op('study.result', $b, ['result' => 'not_feasible']); // problème manquant
        $early = $this->op('installation.result', $c, ['result' => 'installed'], ['occurred_at' => now()->subHours(6)->toIso8601String()]);

        $response = $this->push($late, $bad, $early)->assertOk();

        $this->assertSame([$late['client_uuid'], $bad['client_uuid'], $early['client_uuid']], collect($response->json('data'))->pluck('client_uuid')->all());
        $this->assertSame(['applied', 'rejected', 'applied'], collect($response->json('data'))->pluck('status')->all());
        $this->assertSame(OrderStatus::Installed, $c->fresh()->status);
    }

    public function test_a_malformed_operation_is_rejected_without_being_stored_and_the_batch_is_limited(): void
    {
        Sanctum::actingAs($this->tech);

        $this->push(['client_uuid' => 'pas-un-uuid', 'type' => 'inconnu'])->assertOk()
            ->assertJsonPath('data.0.status', 'rejected')->assertJsonPath('data.0.error_code', 'INVALID_OPERATION');
        $this->assertSame(0, FieldOperation::count());

        $this->postJson('/api/v1/sync/push', ['operations' => []])->assertUnprocessable();
        $this->postJson('/api/v1/sync/push', ['operations' => array_fill(0, 101, ['x' => 1])])->assertUnprocessable();
    }

    public function test_a_future_time_is_brought_back_to_now_and_an_unknown_client_is_a_conflict(): void
    {
        $order = $this->inStudy();
        Sanctum::actingAs($this->tech);

        $this->push($this->op('study.result', $order, ['result' => 'customer_absent'], ['occurred_at' => now()->addDays(2)->toIso8601String()]))->assertJsonPath('data.0.status', 'applied');
        $this->assertTrue(FieldOperation::first()->occurred_at->lessThanOrEqualTo(now()));

        $ghost = $this->op('study.result', $order, ['result' => 'customer_absent'], ['customer_order_id' => (string) Str::uuid()]);
        $this->push($ghost)->assertJsonPath('data.0.error_code', 'ORDER_NOT_FOUND');
    }

    public function test_another_user_cannot_replay_someone_elses_operation_and_anonymous_is_refused(): void
    {
        $order = $this->inStudy();
        Sanctum::actingAs($this->tech);
        $op = $this->op('study.result', $order, ['result' => 'customer_absent']);
        $this->push($op)->assertJsonPath('data.0.status', 'applied');

        Sanctum::actingAs($this->user(Role::Technician, 'Intrus', MissionType::Study));
        $this->push($op)->assertJsonPath('data.0.status', 'rejected');

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/sync/push', ['operations' => [$op]])->assertUnauthorized();
    }

    public function test_a_field_result_without_a_photo_is_rejected_and_kept_for_both_studies_and_installations(): void
    {
        $study = $this->inStudy('PH1');
        $install = $this->scheduled('PH2');
        Sanctum::actingAs($this->tech);

        $this->push(
            $this->op('study.result', $study, ['result' => 'customer_absent', 'photo_uuids' => null]),
            $this->op('installation.result', $install, ['result' => 'installed', 'photo_uuids' => []]),
        )->assertOk()
            ->assertJsonPath('data.0.status', 'rejected')->assertJsonPath('data.0.error_code', 'PHOTO_REQUIRED')
            ->assertJsonPath('data.1.status', 'rejected')->assertJsonPath('data.1.error_code', 'PHOTO_REQUIRED');

        $this->assertSame(OrderStatus::InStudy, $study->fresh()->status);
        $this->assertSame(OrderStatus::AppointmentSet, $install->fresh()->status);
        $this->assertSame(2, FieldOperation::where('status', 'rejected')->count(), 'la saisie est gardée, jamais perdue');
    }

    public function test_photos_must_be_uuids_and_at_most_six(): void
    {
        $order = $this->inStudy('PH3');
        Sanctum::actingAs($this->tech);

        $this->push($this->op('study.result', $order, ['result' => 'customer_absent', 'photo_uuids' => ['pas-un-uuid']]))->assertJsonPath('data.0.error_code', 'PHOTO_REQUIRED');
        $this->push($this->op('study.result', $order, ['result' => 'customer_absent', 'photo_uuids' => array_map(fn () => (string) Str::uuid(), range(1, 7))]))->assertJsonPath('data.0.error_code', 'PHOTO_REQUIRED');
        $this->push($this->op('study.result', $order, ['result' => 'customer_absent', 'photo_uuids' => array_map(fn () => (string) Str::uuid(), range(1, 6))]))->assertJsonPath('data.0.status', 'applied');
    }
}
