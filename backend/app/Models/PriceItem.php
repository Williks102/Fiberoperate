<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Ligne du catalogue de prix de l'opérateur. */
class PriceItem extends Model
{
    use HasUuids, SoftDeletes;

    public const KINDS = ['supply' => 'Fourniture', 'service' => 'Prestation'];

    protected $fillable = ['kind', 'section', 'ref', 'designation', 'unit', 'unit_price', 'sort_order', 'is_active'];

    protected static function booted(): void
    {
        static::saving(function (PriceItem $item) {
            $item->item_key = self::keyFor($item->section, $item->ref, $item->designation);
        });
    }

    protected function casts(): array
    {
        return ['unit_price' => 'integer', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /** Identité d'une ligne : rubrique, référence et désignation, sans tenir compte de la casse ni des espaces en trop. */
    public static function keyFor(?string $section, ?string $ref, ?string $designation): string
    {
        $normalize = fn (?string $value) => mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $value)));

        return $normalize($section).'|'.$normalize($ref).'|'.$normalize($designation);
    }
}
