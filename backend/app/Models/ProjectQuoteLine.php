<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Ligne du devis d'un projet : un article du catalogue, copié, avec ses quantités d'étude et réalisée. */
class ProjectQuoteLine extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['project_id', 'price_item_id', 'kind', 'section', 'ref', 'designation', 'unit', 'unit_price', 'study_quantity', 'done_quantity'];

    protected function casts(): array
    {
        return ['unit_price' => 'integer', 'study_quantity' => 'integer', 'done_quantity' => 'integer'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Montant prévu (quantité d'étude × prix), en FCFA ; null si le prix n'est pas renseigné. */
    public function studyTotal(): ?int
    {
        return $this->unit_price === null ? null : $this->study_quantity * $this->unit_price;
    }

    /** Montant réalisé ; null tant que rien n'est réalisé de saisi ou si le prix manque. */
    public function doneTotal(): ?int
    {
        return $this->unit_price === null || $this->done_quantity === null ? null : $this->done_quantity * $this->unit_price;
    }
}
