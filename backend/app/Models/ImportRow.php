<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une ligne analysée : valeurs normalisées, statut et anomalies. */
class ImportRow extends Model
{
    use HasUuids;

    protected $fillable = ['import_batch_id', 'line', 'status', 'action', 'excluded', 'data', 'anomalies'];

    protected function casts(): array
    {
        return ['data' => 'array', 'anomalies' => 'array', 'excluded' => 'boolean', 'line' => 'integer'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }
}
