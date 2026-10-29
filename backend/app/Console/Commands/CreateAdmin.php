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
    protected $signature = 'users:create-admin {email : Adresse e-mail de connexion} {name : Nom complet}';

    protected $description = 'Crée un compte administrateur (mot de passe saisi de façon masquée)';

    public function handle(): int
    {
        $password = (string) $this->secret('Mot de passe (8 caractères minimum, avec une lettre et un chiffre)');
        $confirmation = (string) $this->secret('Confirmez le mot de passe');

        if ($password !== $confirmation) {
            $this->error('Les deux mots de passe ne sont pas identiques.');

            return self::FAILURE;
        }

        $validator = Validator::make(
            ['email' => $this->argument('email'), 'name' => $this->argument('name'), 'password' => $password],
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
            'name' => $this->argument('name'),
            'email' => $this->argument('email'),
            'password' => $password,
            'role' => Role::Admin,
            'is_active' => true,
        ]);

        $this->info('Administrateur créé : '.$this->argument('email'));

        return self::SUCCESS;
    }
}
