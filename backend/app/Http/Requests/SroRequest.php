<?php

namespace App\Http\Requests;

use App\Models\Sro;
use App\Support\CodeNormalizer;
use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SroRequest extends ManagesResource
{
    use ChecksCoordinates;

    protected function modelClass(): string
    {
        return Sro::class;
    }

    protected function routeKey(): string
    {
        return 'sro';
    }

    public function rules(): array
    {
        $id = $this->ignoreId();

        return [
            // Une SRO par sous-plaque, et elle n'en change pas.
            'zone_id' => $id === null
                ? ['required', 'uuid', Rule::exists('zones', 'id')->whereNull('deleted_at'), Rule::unique('sros', 'zone_id')->whereNull('deleted_at')]
                : ['prohibited'],
            'code' => [$this->req(), 'string', 'max:40', function (string $attribute, mixed $value, Closure $fail) use ($id) {
                $taken = Sro::query()->where('code_normalized', CodeNormalizer::generic((string) $value))
                    ->when($id, fn ($q) => $q->whereKeyNot($id))->exists();

                if ($taken) {
                    $fail('Ce code de SRO existe déjà.');
                }
            }],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'olt_slot' => ['nullable', 'string', 'max:255'],
            'olt_port' => ['nullable', 'string', 'max:255'],
            'fiber_strand' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'zone_id.unique' => 'Cette sous-plaque a déjà une SRO.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->checkCoordinates($validator));
    }
}
