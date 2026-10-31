<?php

namespace App\Http\Requests;

use App\Models\CustomerOrder;
use Illuminate\Foundation\Http\FormRequest;

/** Demande de signature pour envoyer une photo : la commande doit être une de celles du technicien. */
class SignUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = CustomerOrder::query()->find($this->input('customer_order_id'));

        return $order === null || $this->user()->can('addPhoto', $order); // commande inconnue : 422 de validation, pas 403
    }

    public function rules(): array
    {
        return [
            'customer_order_id' => ['required', 'uuid', 'exists:customer_orders,id'],
            'client_uuid' => ['required', 'uuid'],
        ];
    }
}
