<?php

namespace App\Models;

use App\Enums\ProjectKind;
use App\Enums\ProjectPhase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/** Projet de déploiement : une plaque, suivie de l'avant-projet à l'exploitation. */
class Project extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['plaque_id'];

    protected $attributes = ['phase' => 'pre_project'];

    protected function casts(): array
    {
        return ['phase' => ProjectPhase::class, 'kind' => ProjectKind::class, 'phase_changed_at' => 'datetime'];
    }

    public function plaque(): BelongsTo
    {
        return $this->belongsTo(Plaque::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(ProjectPhaseHistory::class)->orderBy('created_at');
    }

    /** Ouvre le projet d'une plaque en avant-projet et écrit la première ligne d'historique (même transaction). */
    public static function startFor(Plaque $plaque, User $by): self
    {
        return DB::transaction(function () use ($plaque, $by) {
            // Un projet sur une plaque déjà déployée (un projet précédent existe) est une désaturation.
            $kind = self::query()->where('plaque_id', $plaque->id)->exists() ? ProjectKind::Desaturation : ProjectKind::Deployment;
            $project = self::create(['plaque_id' => $plaque->id]);
            $project->forceFill(['kind' => $kind]);
            $project->forceFill(['phase_changed_at' => now()])->save();
            $project->history()->create(['from_phase' => null, 'to_phase' => ProjectPhase::PreProject, 'changed_by' => $by->id]);

            return $project;
        });
    }

    /** Les projets des plaques visibles par l'utilisateur. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereIn('plaque_id', Plaque::query()->visibleTo($user)->select('id'));
    }
}
