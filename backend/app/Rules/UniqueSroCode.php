<?php

namespace App\Rules;

use App\Models\Sro;
use App\Support\CodeNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Le code d'une SRO est unique quelle que soit son écriture (« abb-456 » = « ABB456 »). */
class UniqueSroCode implements ValidationRule
{
    public function __construct(private readonly ?string $ignoreId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $taken = Sro::query()->where('code_normalized', CodeNormalizer::generic((string) $value))
            ->when($this->ignoreId, fn ($q) => $q->whereKeyNot($this->ignoreId))->exists();

        if ($taken) {
            $fail('Ce code de SRO existe déjà.');
        }
    }
}
