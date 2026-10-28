<?php

namespace App\Http\Requests;

use App\Enums\MissionType;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends ManagesResource
{
    protected function modelClass(): string
    {
        return User::class;
    }

    protected function routeKey(): string
    {
        return 'user';
    }

    public function rules(): array
    {
        return [
            'name' => [$this->req(), 'string', 'max:120'],
            'email' => [
                $this->req(), 'email:rfc', 'max:190',
                Rule::unique('users', 'email')->whereNull('deleted_at')->ignore($this->ignoreId()),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => [$this->req(), Password::min(8)->letters()->numbers()],
            'role' => [$this->req(), Rule::enum(Role::class)],
            'manager_id' => ['nullable', 'uuid', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'is_active' => ['sometimes', 'boolean'],
            'skills' => ['sometimes', 'array'],
            'skills.*' => ['distinct', Rule::enum(MissionType::class)],
        ];
    }
}
