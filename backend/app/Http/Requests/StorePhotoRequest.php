<?php

namespace App\Http\Requests;

use App\Models\CustomerOrder;
use Illuminate\Foundation\Http\FormRequest;

/** Enregistrement d'une photo déjà envoyée à Cloudinary : identifiant, taille, empreinte et heure de la prise de vue. */
class StorePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = CustomerOrder::query()->find($this->input('customer_order_id'));

        return $order === null || $this->user()->can('addPhoto', $order);
    }

    public function rules(): array
    {
        return [
            'client_uuid' => ['required', 'uuid'],
            'customer_order_id' => ['required', 'uuid', 'exists:customer_orders,id'],
            'public_id' => ['required', 'string', 'max:255'],
            'format' => ['nullable', 'string', 'max:10'],
            'bytes' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'width' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'height' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'hash' => ['nullable', 'string', 'size:64', 'regex:/^[0-9a-f]+$/i'],
            'taken_at' => ['required', 'date'],
        ];
    }
}
