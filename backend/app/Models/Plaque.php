<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Plaque : grande zone géographique, couverte par un superviseur. */
class Plaque extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['name', 'olt_id', 'supervisor_id'];

    public function olt(): BelongsTo
    {
        return $this->belongsTo(Olt::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    /** Sous-plaques de la plaque. */
    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class);
    }
}
