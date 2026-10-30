<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Olt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OltApiTest extends TestCase
{
    use RefreshDatabase;

    private function login(Role $role): void
    {
        Sanctum::actingAs(User::create(['name' => 'u', 'email' => "{$role->value}@x.test", 'password' => 'secret123', 'role' => $role]));
    }

    public function test_admin_creates_and_updates_an_olt(): void
    {
        $this->login(Role::Admin);

        $id = $this->postJson('/api/v1/olts', ['name' => 'OLT-NOKIA-1', 'notes' => 'Site Faya'])
            ->assertCreated()->assertJsonPath('data.notes', 'Site Faya')->json('data.id');

        $this->patchJson("/api/v1/olts/{$id}", ['notes' => null])->assertOk()->assertJsonPath('data.notes', null);
        $this->assertSame('OLT-NOKIA-1', Olt::findOrFail($id)->name);
    }

    public function test_name_is_required_and_unique(): void
    {
        $this->login(Role::Admin);
        Olt::create(['name' => 'OLT1']);

        $this->postJson('/api/v1/olts', [])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->postJson('/api/v1/olts', ['name' => 'OLT1'])->assertStatus(422)->assertJsonPath('code', 'DUPLICATE');
    }

    public function test_search_filters_by_name(): void
    {
        $this->login(Role::Dg);
        Olt::create(['name' => 'OLT-FAYA']);
        Olt::create(['name' => 'OLT-ARCADE']);

        $names = collect($this->getJson('/api/v1/olts?q=arc')->assertOk()->json('data'))->pluck('name')->all();

        $this->assertSame(['OLT-ARCADE'], $names);
    }
}
