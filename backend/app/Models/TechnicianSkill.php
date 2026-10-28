<?php

namespace App\Models;

use App\Enums\MissionType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Habilitation de mission d'un technicien. */
class TechnicianSkill extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'mission_type'];

    protected function casts(): array
    {
        return ['mission_type' => MissionType::class];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
