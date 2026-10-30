<?php

namespace App\Models;

use App\Enums\ProjectPhase;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une ligne d'historique : qui a fait passer le projet d'une phase à une autre, quand, avec quelle décision. */
class ProjectPhaseHistory extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'project_phase_history';

    protected $fillable = ['project_id', 'from_phase', 'to_phase', 'decision', 'reason', 'changed_by'];

    protected function casts(): array
    {
        return ['from_phase' => ProjectPhase::class, 'to_phase' => ProjectPhase::class];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
