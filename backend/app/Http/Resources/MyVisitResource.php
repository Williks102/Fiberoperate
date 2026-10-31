<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Une visite planifiée du technicien, avec ce qu'il lui faut chez le client (contact, adresse, GPS déclaré). */
class MyVisitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $order = $this->order;

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'planned_date' => $this->planned_date->toDateString(),
            'observation' => $this->observation,
            'order' => [
                'id' => $order->id,
                'customer_ref' => $order->customer_ref,
                'name' => $order->name,
                'phone_1' => $order->phone_1,
                'phone_2' => $order->phone_2,
                'address_raw' => $order->address_raw,
                'declared_latitude' => $order->declared_latitude,
                'declared_longitude' => $order->declared_longitude,
                'status' => $order->status->value,
                'zone' => $order->zone?->code,
                'pco' => $order->pco?->code,
                'ont_serial' => $order->ont_serial,
                'fixed_line' => $order->fixed_line,
            ],
        ];
    }
}
