<?php

namespace App\Models;

use App\Models\Concerns\ResolvesPlaque;
use App\Support\CodeNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** JDV = splitter (SPL001…). Regroupe plusieurs PCO, sans nombre imposé. */
class Jdv extends Model
{
    use HasUuids, ResolvesPlaque, SoftDeletes;

    protected $table = 'jdvs';

    protected $fillable = ['zone_id', 'code'];

    protected static function booted(): void
    {
        static::saving(function (Jdv $jdv) {
            $jdv->code_normalized = CodeNormalizer::generic($jdv->code);
        });
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function pcos(): HasMany
    {
        return $this->hasMany(Pco::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereIn('zone_id', Zone::query()->visibleTo($user)->select('id'));
    }
}
