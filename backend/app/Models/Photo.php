<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Une photo de terrain : référence vers Cloudinary (jamais l'image, jamais son URL). */
class Photo extends Model
{
    use HasUuids;

    protected $fillable = ['client_uuid', 'customer_order_id', 'user_id', 'public_id', 'format', 'bytes', 'width', 'height', 'hash', 'taken_at'];

    protected function casts(): array
    {
        return ['taken_at' => 'datetime'];
    }
}
