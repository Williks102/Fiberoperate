<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Une ligne d'historique de statut d'une commande : en ajout seul. */
class OrderStatusHistory extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'order_status_history';

    protected $fillable = ['customer_order_id', 'from_status', 'to_status', 'observation', 'changed_by', 'device'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException("L'historique d'une commande ne se modifie pas."));
        static::deleting(fn () => throw new LogicException("L'historique d'une commande ne se supprime pas."));
    }

    protected function casts(): array
    {
        return ['from_status' => OrderStatus::class, 'to_status' => OrderStatus::class];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class, 'customer_order_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
