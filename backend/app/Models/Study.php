<?php

namespace App\Models;

use App\Enums\StudyResult;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Résultat de l'étude de terrain d'une commande (une par intervention d'étude). */
class Study extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'client_uuid', 'intervention_id', 'customer_order_id', 'result', 'pco_id',
        'concrete_poles_existing', 'concrete_poles_to_install', 'metal_poles_existing', 'metal_poles_to_install',
        'distance_m', 'problem', 'field_comments', 'internal_comments', 'field_latitude', 'field_longitude', 'recorded_by',
    ];

    protected function casts(): array
    {
        return ['result' => StudyResult::class, 'field_latitude' => 'decimal:7', 'field_longitude' => 'decimal:7'];
    }

    public function pco(): BelongsTo
    {
        return $this->belongsTo(Pco::class);
    }

    /** Nombre de poteaux du trajet pour les types comptés par défaut, existants et à installer. */
    public function countedPoles(): int
    {
        $types = config('study.distance_pole_types');

        return (in_array('concrete', $types, true) ? $this->concrete_poles_existing + $this->concrete_poles_to_install : 0)
            + (in_array('metal', $types, true) ? $this->metal_poles_existing + $this->metal_poles_to_install : 0);
    }
}
