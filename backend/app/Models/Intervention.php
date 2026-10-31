<?php

namespace App\Models;

use App\Enums\InterventionStatus;
use App\Enums\MissionType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Une intervention planifiée chez un client : le type reprend les habilitations de mission (étude, raccordement client). */
class Intervention extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['client_uuid', 'type', 'customer_order_id', 'technician_id', 'planned_date', 'status', 'observation', 'created_by'];

    protected function casts(): array
    {
        return [
            'type' => MissionType::class,
            'status' => InterventionStatus::class,
            'planned_date' => 'date',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class, 'customer_order_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }
}
