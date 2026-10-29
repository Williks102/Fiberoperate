<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Crée le premier administrateur d'un environnement sans données de démonstration. */
class CreateAdmin extends Command
{
    protected $signature = 'users:create-admin
        {email? : Adresse e-mail de connexion}
        {name? : Nom complet}
        {--from-env : Lit ADMIN_EMAIL, ADMIN_NAME et ADMIN_PASSWORD ; ne fait rien si un administrateur existe déjà (hébergement sans terminal)}';

    protected $description = 'Crée un compte administrateur (mot de passe saisi de façon masquée)';

    public function handle(): int
    {
        if ($this->option('from-env')) {
            return $this->createFromEnvironment();
        }

        $password = (string) $this->secret('Mot de passe (8 caractères minimum, avec une lettre et un chiffre)');
        $confirmation = (string) $this->secret('Confirmez le mot de passe');

        if ($password !== $confirmation) {
            $this->error('Les deux mots de passe ne sont pas identiques.');

            return self::FAILURE;
        }

        return $this->create((string) $this->argument('email'), (string) $this->argument('name'), $password);
    }

    /**
     * Démarrage d'un hébergement sans terminal (plan gratuit de Render). Idempotent : s'il existe déjà
     * un administrateur, rien n'est créé et aucun mot de passe n'est jamais réinitialisé.
     */
    private function createFromEnvironment(): int
    {
        if (User::query()->where('role', Role::Admin->value)->exists()) {
            $this->info('Un administrateur existe déjà : rien à faire.');

            return self::SUCCESS;
        }

        return $this->create((string) getenv('ADMIN_EMAIL'), (string) getenv('ADMIN_NAME'), (string) getenv('ADMIN_PASSWORD'));
    }

    private function create(string $email, string $name, string $password): int
    {
        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'password' => $password],
            [
                'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')->whereNull('deleted_at')],
                'name' => ['required', 'string', 'max:120'],
                'password' => ['required', Password::min(8)->letters()->numbers()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => Role::Admin,
            'is_active' => true,
        ]);

        $this->info('Administrateur créé : '.$email);

        return self::SUCCESS;
    }
}
