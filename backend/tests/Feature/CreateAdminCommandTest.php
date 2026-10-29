<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_an_active_admin_with_hashed_password(): void
    {
        $this->artisan('users:create-admin', ['email' => 'admin@ftth.test', 'name' => 'Admin'])
            ->expectsQuestion('Mot de passe (8 caractères minimum, avec une lettre et un chiffre)', 'motdepasse1')
            ->expectsQuestion('Confirmez le mot de passe', 'motdepasse1')
            ->expectsOutput('Administrateur créé : admin@ftth.test')
            ->assertSuccessful();

        $admin = User::where('email', 'admin@ftth.test')->firstOrFail();
        $this->assertSame(Role::Admin, $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertTrue(Hash::check('motdepasse1', $admin->password));
    }

    public function test_created_admin_can_log_in(): void
    {
        $this->artisan('users:create-admin', ['email' => 'admin@ftth.test', 'name' => 'Admin'])
            ->expectsQuestion('Mot de passe (8 caractères minimum, avec une lettre et un chiffre)', 'motdepasse1')
            ->expectsQuestion('Confirmez le mot de passe', 'motdepasse1')
            ->assertSuccessful();

        $this->postJson('/api/v1/auth/login', ['email' => 'admin@ftth.test', 'password' => 'motdepasse1'])
            ->assertOk()->assertJsonPath('user.role', 'admin');
    }

    public function test_refuses_mismatched_or_weak_passwords_and_duplicates(): void
    {
        $this->artisan('users:create-admin', ['email' => 'a@ftth.test', 'name' => 'A'])
            ->expectsQuestion('Mot de passe (8 caractères minimum, avec une lettre et un chiffre)', 'motdepasse1')
            ->expectsQuestion('Confirmez le mot de passe', 'autrechose1')
            ->assertFailed();

        $this->artisan('users:create-admin', ['email' => 'a@ftth.test', 'name' => 'A'])
            ->expectsQuestion('Mot de passe (8 caractères minimum, avec une lettre et un chiffre)', 'abc')
            ->expectsQuestion('Confirmez le mot de passe', 'abc')
            ->assertFailed();

        User::create(['name' => 'X', 'email' => 'x@ftth.test', 'password' => 'secret123', 'role' => Role::Coordinator]);
        $this->artisan('users:create-admin', ['email' => 'x@ftth.test', 'name' => 'X'])
            ->expectsQuestion('Mot de passe (8 caractères minimum, avec une lettre et un chiffre)', 'motdepasse1')
            ->expectsQuestion('Confirmez le mot de passe', 'motdepasse1')
            ->assertFailed();

        $this->assertSame(1, User::count());
    }

    public function test_from_env_creates_the_first_admin_once_and_never_overwrites(): void
    {
        putenv('ADMIN_EMAIL=demo@ftth.test');
        putenv('ADMIN_NAME=Démo');
        putenv('ADMIN_PASSWORD=motdepasse1');

        try {
            $this->artisan('users:create-admin', ['--from-env' => true])->assertSuccessful();
            $admin = User::where('email', 'demo@ftth.test')->firstOrFail();
            $this->assertSame(Role::Admin, $admin->role);
            $hash = $admin->password;

            // Redémarrage du conteneur : aucun doublon, mot de passe inchangé même si la variable change.
            putenv('ADMIN_PASSWORD=autrechose2');
            $this->artisan('users:create-admin', ['--from-env' => true])->assertSuccessful();
            $this->assertSame(1, User::count());
            $this->assertSame($hash, $admin->fresh()->password);
        } finally {
            putenv('ADMIN_EMAIL');
            putenv('ADMIN_NAME');
            putenv('ADMIN_PASSWORD');
        }
    }

    public function test_from_env_fails_on_weak_password_without_creating_anything(): void
    {
        putenv('ADMIN_EMAIL=demo@ftth.test');
        putenv('ADMIN_NAME=Démo');
        putenv('ADMIN_PASSWORD=abc');

        try {
            $this->artisan('users:create-admin', ['--from-env' => true])->assertFailed();
            $this->assertSame(0, User::count());
        } finally {
            putenv('ADMIN_EMAIL');
            putenv('ADMIN_NAME');
            putenv('ADMIN_PASSWORD');
        }
    }
}
