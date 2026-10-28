<?php

namespace App\Models;

use App\Enums\PcoStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ligne d'historique : qui a changé le statut d'un PCO, quand, depuis quel appareil. */
class PcoStatusHistory extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'pco_status_history';

    protected $fillable = [
        'pco_id',
        'from_status',
        'to_status',
        'observation',
        'changed_by',
        'device',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => PcoStatus::class,
            'to_status' => PcoStatus::class,
        ];
    }

    public function pco(): BelongsTo
    {
        return $this->belongsTo(Pco::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
