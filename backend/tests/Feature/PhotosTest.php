<?php

namespace Tests\Feature;

use App\Domain\Photos\Cloudinary;
use App\Domain\Study\PlanStudy;
use App\Enums\MissionType;
use App\Enums\Role;
use App\Models\CustomerOrder;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PhotosTest extends TestCase
{
    use RefreshDatabase;

    private User $tech;

    private CustomerOrder $order;

    protected function setUp(): void
    {
        parent::setUp();
        $coordinator = $this->user(Role::Coordinator, 'Coord');
        $this->tech = $this->user(Role::Technician, 'Koffi', MissionType::Study);
        $this->order = CustomerOrder::create(['customer_ref' => 'RAB1', 'name' => 'Koné Awa']);
        app(PlanStudy::class)($this->order, $this->tech, now(), $coordinator);
        config(['services.cloudinary.url' => 'cloudinary://123456:s3cr3t@demo-cloud', 'services.cloudinary.folder' => 'ftth']);
    }

    private function user(Role $role, string $name, MissionType ...$skills): User
    {
        $user = User::create(['name' => $name, 'email' => strtolower(str_replace(' ', '', $name)).'@x.test', 'password' => 'secret123', 'role' => $role]);
        foreach ($skills as $skill) {
            $user->skills()->create(['mission_type' => $skill->value]);
        }

        return $user;
    }

    private function body(string $uuid, array $extra = []): array
    {
        return $extra + [
            'client_uuid' => $uuid, 'customer_order_id' => $this->order->id, 'public_id' => "ftth/{$this->order->id}/{$uuid}",
            'format' => 'jpg', 'bytes' => 240000, 'width' => 1200, 'height' => 900, 'hash' => str_repeat('ab', 32), 'taken_at' => now()->subMinutes(10)->toIso8601String(),
        ];
    }

    public function test_the_signature_follows_cloudinary_rules_and_never_leaks_the_secret(): void
    {
        Sanctum::actingAs($this->tech);
        $uuid = (string) Str::uuid();

        $response = $this->postJson('/api/v1/uploads/sign', ['customer_order_id' => $this->order->id, 'client_uuid' => $uuid])->assertOk();
        $data = $response->json('data');

        $this->assertSame("ftth/{$this->order->id}/{$uuid}", $data['public_id']);
        $this->assertSame('authenticated', $data['type']);
        $this->assertSame('123456', $data['api_key']);
        $this->assertSame('https://api.cloudinary.com/v1_1/demo-cloud/image/upload', $data['upload_url']);
        // Paramètres triés, « nom=valeur » joints par « & », secret ajouté, SHA-1.
        $expected = sha1("public_id={$data['public_id']}&timestamp={$data['timestamp']}&type=authenticated".'s3cr3t');
        $this->assertSame($expected, $data['signature']);
        $this->assertStringNotContainsString('s3cr3t', $response->getContent());
    }

    public function test_the_signature_is_the_documented_cloudinary_example(): void
    {
        // Exemple de la documentation Cloudinary : « public_id=sample&timestamp=1315060510 » + secret.
        $signer = new Cloudinary('cloudinary://k:abcd@c');

        $this->assertSame(sha1('public_id=sample&timestamp=1315060510abcd'), $signer->sign(['timestamp' => 1315060510, 'public_id' => 'sample']));
    }

    public function test_without_cloudinary_the_server_says_so_in_french(): void
    {
        config(['services.cloudinary.url' => null]);
        Sanctum::actingAs($this->tech);

        $this->postJson('/api/v1/uploads/sign', ['customer_order_id' => $this->order->id, 'client_uuid' => (string) Str::uuid()])
            ->assertStatus(503)->assertJsonPath('code', 'PHOTOS_NOT_CONFIGURED');
    }

    public function test_a_photo_is_registered_once_and_a_replay_returns_the_same_photo(): void
    {
        Sanctum::actingAs($this->tech);
        $uuid = (string) Str::uuid();

        $first = $this->postJson('/api/v1/photos', $this->body($uuid))->assertCreated()->assertJsonPath('data.client_uuid', $uuid);
        $again = $this->postJson('/api/v1/photos', $this->body($uuid))->assertOk();

        $this->assertSame($first->json('data.id'), $again->json('data.id'));
        $this->assertSame(1, Photo::count());
        $this->assertSame($this->tech->id, Photo::first()->user_id);
    }

    public function test_a_photo_must_sit_where_the_server_put_it_and_have_valid_figures(): void
    {
        Sanctum::actingAs($this->tech);
        $uuid = (string) Str::uuid();

        $this->postJson('/api/v1/photos', $this->body($uuid, ['public_id' => 'ftth/ailleurs/'.$uuid]))->assertUnprocessable()->assertJsonValidationErrors('public_id');
        $this->postJson('/api/v1/photos', $this->body($uuid, ['hash' => 'trop-court']))->assertUnprocessable()->assertJsonValidationErrors('hash');
        $this->postJson('/api/v1/photos', $this->body($uuid, ['bytes' => 99_000_000]))->assertUnprocessable()->assertJsonValidationErrors('bytes');
        $this->postJson('/api/v1/photos', $this->body($uuid, ['customer_order_id' => (string) Str::uuid()]))->assertUnprocessable()->assertJsonValidationErrors('customer_order_id');
        $this->assertSame(0, Photo::count());
    }

    public function test_a_time_in_the_future_is_brought_back_to_now(): void
    {
        Sanctum::actingAs($this->tech);
        $this->postJson('/api/v1/photos', $this->body((string) Str::uuid(), ['taken_at' => now()->addDays(3)->toIso8601String()]))->assertCreated();

        $this->assertTrue(Photo::first()->taken_at->lessThanOrEqualTo(now()));
    }

    public function test_someone_elses_photo_uuid_cannot_be_replayed(): void
    {
        $uuid = (string) Str::uuid();
        Sanctum::actingAs($this->tech);
        $this->postJson('/api/v1/photos', $this->body($uuid))->assertCreated();

        Sanctum::actingAs($this->user(Role::Coordinator, 'Autre coordinateur'));
        $this->postJson('/api/v1/photos', $this->body($uuid))->assertStatus(409)->assertJsonPath('code', 'PHOTO_CONFLICT');
    }

    public function test_who_may_add_a_photo_to_an_order(): void
    {
        $uuid = (string) Str::uuid();
        $sign = ['customer_order_id' => $this->order->id, 'client_uuid' => $uuid];

        foreach ([
            $this->user(Role::Technician, 'Autre Tech', MissionType::Study), // pas de visite sur cette commande
            $this->user(Role::Dg, 'DG'), $this->user(Role::Dt, 'DT'), $this->user(Role::Supervisor, 'Superviseur'), $this->user(Role::BeManager, 'BE'),
        ] as $other) {
            Sanctum::actingAs($other);
            $this->postJson('/api/v1/uploads/sign', $sign)->assertForbidden();
            $this->postJson('/api/v1/photos', $this->body($uuid))->assertForbidden();
        }

        foreach ([$this->tech, $this->user(Role::Coordinator, 'Coord 2'), $this->user(Role::Admin, 'Admin')] as $allowed) {
            Sanctum::actingAs($allowed);
            $this->postJson('/api/v1/uploads/sign', $sign)->assertOk();
        }
        $this->assertSame(0, Photo::count());

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/uploads/sign', $sign)->assertUnauthorized();
    }

    public function test_a_technician_keeps_the_right_to_add_photos_after_the_visit_is_done(): void
    {
        Sanctum::actingAs($this->tech);
        $this->postJson("/api/v1/customer-orders/{$this->order->id}/study-result", ['result' => 'customer_absent'])->assertOk(); // visite réalisée

        $this->postJson('/api/v1/photos', $this->body((string) Str::uuid()))->assertCreated();
    }
}
