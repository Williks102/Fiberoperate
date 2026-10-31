<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Un fichier analysé en attente de validation (ou déjà validé). */
class ImportBatch extends Model
{
    use HasUuids;

    protected $fillable = ['type', 'file_name', 'status', 'counts', 'created_by'];

    protected function casts(): array
    {
        return ['counts' => 'array', 'validated_at' => 'datetime'];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class)->orderBy('line');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
