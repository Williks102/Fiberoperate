<?php

namespace App\Models;

use App\Enums\InitialStudy;
use App\Enums\OrderStatus;
use App\Enums\StudyResult;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/** Commande d'un client (un abonnement, clé : ID client) : de la réception à l'activation de la ligne. */
class CustomerOrder extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'client_uuid', 'customer_ref', 'account_number', 'ft_line', 'name',
        'phone_1', 'phone_1_raw', 'phone_2', 'phone_2_raw',
        'address_raw', 'city', 'commune', 'district', 'street', 'address_detail',
        'declared_latitude', 'declared_longitude', 'field_latitude', 'field_longitude',
        'zone_id', 'pco_id', 'distance_m', 'ont_serial', 'fixed_line',
        'initial_study_raw', 'initial_study', 'registered_at', 'sent_to_contractor_at',
        'operator_line_status', 'operator_line_status_at', 'observation',
    ];

    protected $attributes = ['status' => 'received'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'study_result' => StudyResult::class,
            'initial_study' => InitialStudy::class,
            'declared_latitude' => 'decimal:7',
            'declared_longitude' => 'decimal:7',
            'field_latitude' => 'decimal:7',
            'field_longitude' => 'decimal:7',
            'distance_m' => 'integer',
            'registered_at' => 'date',
            'sent_to_contractor_at' => 'date',
            'operator_line_status_at' => 'date',
            'operator_synced_at' => 'datetime',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function pco(): BelongsTo
    {
        return $this->belongsTo(Pco::class);
    }

    public function interventions(): HasMany
    {
        return $this->hasMany(Intervention::class)->orderByDesc('planned_date');
    }

    public function studies(): HasMany
    {
        return $this->hasMany(Study::class)->orderByDesc('created_at');
    }

    public function history(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
    }

    /** Étape du raccordement : instances, raccordables ou raccordes (même découpage que le filtre `stage` de l'API). */
    public function stage(): string
    {
        return match ($this->status) {
            OrderStatus::Received, OrderStatus::InStudy => 'instances',
            OrderStatus::Studied => $this->study_result === StudyResult::Feasible ? 'raccordables' : 'instances',
            OrderStatus::AppointmentSet, OrderStatus::Postponed, OrderStatus::Failed => 'raccordables',
            OrderStatus::Installed, OrderStatus::Activated => 'raccordes',
        };
    }

    /** Change le statut et écrit la ligne d'historique dans la même transaction (le statut n'est pas modifiable en masse). */
    public function changeStatus(OrderStatus $to, ?User $by = null, ?string $observation = null, ?string $device = null, ?StudyResult $result = null, ?CarbonInterface $at = null): OrderStatusHistory
    {
        return DB::transaction(function () use ($to, $by, $observation, $device, $result, $at) {
            $from = $this->status;

            $this->forceFill(['status' => $to, 'study_result' => $result ?? $this->study_result])->save();

            $line = $this->history()->make([
                'from_status' => $from,
                'to_status' => $to,
                'observation' => $observation,
                'changed_by' => $by?->getKey(),
                'device' => $device,
            ]);
            // Une saisie faite hors ligne garde l'heure à laquelle elle a eu lieu sur le terrain, pas celle de sa synchronisation.
            if ($at !== null) {
                $line->created_at = $at;
            }
            $line->save();

            return $line;
        });
    }
}
