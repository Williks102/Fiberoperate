<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Motif de report ou d'échec d'une installation (référentiel). */
class OutcomeReason extends Model
{
    use HasUuids;

    protected $fillable = ['code', 'label', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
