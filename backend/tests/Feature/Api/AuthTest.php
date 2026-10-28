<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attrs = []): User
    {
        return User::create($attrs + ['name' => 'T', 'email' => 't@x.test', 'password' => 'secret123', 'role' => Role::Coordinator]);
    }

    public function test_login_returns_token_and_user(): void
    {
        $this->user();

        $response = $this->postJson('/api/v1/auth/login', ['email' => 't@x.test', 'password' => 'secret123'])->assertOk();

        $token = $response->json('token');
        $this->assertNotEmpty($token);
        $response->assertJsonPath('user.role', 'coordinator');

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.email', 't@x.test');
    }

    public function test_wrong_password_is_rejected_with_french_message(): void
    {
        $this->user();

        $this->postJson('/api/v1/auth/login', ['email' => 't@x.test', 'password' => 'mauvais'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Identifiants incorrects.');
    }

    public function test_disabled_account_cannot_login(): void
    {
        $this->user(['is_active' => false]);

        $this->postJson('/api/v1/auth/login', ['email' => 't@x.test', 'password' => 'secret123'])
            ->assertForbidden()
            ->assertJsonPath('code', 'ACCOUNT_DISABLED');
    }

    public function test_disabled_account_with_existing_token_is_refused_and_tokens_revoked(): void
    {
        $user = $this->user();
        $token = $user->createToken('web')->plainTextToken;
        $user->update(['is_active' => false]);

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertForbidden()->assertJsonPath('code', 'ACCOUNT_DISABLED');
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_login_is_throttled_after_five_attempts(): void
    {
        $this->user();

        foreach (range(1, 5) as $_) {
            $this->postJson('/api/v1/auth/login', ['email' => 't@x.test', 'password' => 'x'])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', ['email' => 't@x.test', 'password' => 'secret123'])->assertStatus(429);
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = $this->user();
        $token = $user->createToken('web')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_anonymous_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/plaques')->assertUnauthorized();
    }
}
