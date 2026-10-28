<?php

namespace App\Models;

use App\Enums\PcoStatus;
use App\Support\CodeNormalizer;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** PCO = PBO = « boîtier ». */
class Pco extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'zone_id',
        'jdv_id',
        'code',
        'number',
        'status',
        'status_raw',
        'is_blocked',
        'block_reason_id',
        'latitude',
        'longitude',
        'olt_slot',
        'olt_port',
        'fiber_strand',
        'capacity',
        'comment',
        'observation',
    ];

    protected static function booted(): void
    {
        static::saving(function (Pco $pco) {
            $pco->code_normalized = CodeNormalizer::pco($pco->code);

            if ($pco->number === null && preg_match('/_PCO(\d+)$/', $pco->code_normalized, $m) === 1) {
                $pco->number = (int) $m[1];
            }

            // La zone se déduit du JDV ; si les deux sont fournis, ils doivent concorder.
            if ($pco->jdv_id && $pco->isDirty(['jdv_id', 'zone_id'])) {
                $jdvZoneId = Jdv::query()->whereKey($pco->jdv_id)->value('zone_id');

                if ($pco->zone_id === null) {
                    $pco->zone_id = $jdvZoneId;
                } elseif ($jdvZoneId !== null && $jdvZoneId !== $pco->zone_id) {
                    throw new InvalidArgumentException('Le JDV du PCO n\'appartient pas à la zone indiquée.');
                }
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => PcoStatus::class,
            'is_blocked' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'number' => 'integer',
            'capacity' => 'integer',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function jdv(): BelongsTo
    {
        return $this->belongsTo(Jdv::class);
    }

    public function blockReason(): BelongsTo
    {
        return $this->belongsTo(BlockReason::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(PcoStatusHistory::class)->orderBy('created_at');
    }

    /**
     * Change le statut et écrit la ligne d'historique dans la même transaction.
     * Les transitions sont libres pour l'instant (règles à définir avec les statuts normalisés).
     */
    public function changeStatus(
        ?PcoStatus $to,
        ?User $by = null,
        ?string $observation = null,
        ?string $device = null,
    ): PcoStatusHistory {
        return DB::transaction(function () use ($to, $by, $observation, $device) {
            $from = $this->status;

            $this->status = $to;
            $this->save();

            return $this->history()->create([
                'from_status' => $from,
                'to_status' => $to,
                'observation' => $observation,
                'changed_by' => $by?->getKey(),
                'device' => $device,
            ]);
        });
    }
}
