<?php

namespace App\Models;

use App\Support\CodeNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** SRO : une par sous-plaque, part de l'OLT et alimente les splitters de sa sous-plaque. */
class Sro extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['zone_id', 'code', 'latitude', 'longitude', 'olt_slot', 'olt_port', 'fiber_strand'];

    protected static function booted(): void
    {
        static::saving(function (Sro $sro) {
            $sro->code_normalized = CodeNormalizer::generic($sro->code);
        });
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereIn('zone_id', Zone::query()->visibleTo($user)->select('id'));
    }
}
