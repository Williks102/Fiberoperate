<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Une opération envoyée par le terrain, avec son issue (appliquée, en conflit ou rejetée). */
class FieldOperation extends Model
{
    use HasUuids;

    protected $fillable = ['client_uuid', 'user_id', 'type', 'customer_order_id', 'payload', 'device', 'occurred_at', 'status', 'error_code', 'message', 'processed_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'occurred_at' => 'datetime', 'processed_at' => 'datetime'];
    }
}
