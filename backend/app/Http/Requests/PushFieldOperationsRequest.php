<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Lot d'opérations du terrain : le détail de chaque opération est contrôlé une à une (un lot n'échoue jamais en bloc). */
class PushFieldOperationsRequest extends FormRequest
{
    public const MAX_OPERATIONS = 100;

    public function authorize(): bool
    {
        return true; // chaque opération est autorisée par les policies de la commande visée
    }

    public function rules(): array
    {
        return ['operations' => ['required', 'array', 'min:1', 'max:'.self::MAX_OPERATIONS], 'operations.*' => ['array']];
    }
}
