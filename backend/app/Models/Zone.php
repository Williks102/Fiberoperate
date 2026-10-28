<?php

namespace App\Models;

use App\Enums\Vendor;
use App\Enums\ZoneStatus;
use App\Support\CodeNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Zone = sous-plaque (zone SRO : « hub » chez Huawei, « SRO » chez Nokia). */
class Zone extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['plaque_id', 'code', 'name', 'vendor', 'status'];

    protected $attributes = [
        'status' => 'planned',
    ];

    protected static function booted(): void
    {
        static::saving(function (Zone $zone) {
            $zone->code_normalized = CodeNormalizer::zone($zone->code);
        });
    }

    protected function casts(): array
    {
        return [
            'vendor' => Vendor::class,
            'status' => ZoneStatus::class,
        ];
    }

    public function plaque(): BelongsTo
    {
        return $this->belongsTo(Plaque::class);
    }

    public function jdvs(): HasMany
    {
        return $this->hasMany(Jdv::class);
    }

    public function pcos(): HasMany
    {
        return $this->hasMany(Pco::class);
    }

    /** « Hub » ou « SRO » selon le fabricant ; null si le fabricant est inconnu. */
    public function accessPointLabel(): ?string
    {
        return $this->vendor?->accessPointLabel();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereIn('plaque_id', Plaque::query()->visibleTo($user)->select('id'));
    }
}
