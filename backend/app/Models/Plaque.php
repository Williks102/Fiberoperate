<?php

namespace App\Models;

use App\Enums\MissionType;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Plaque : grande zone géographique, couverte par un superviseur. */
class Plaque extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['name', 'olt_id', 'supervisor_id'];

    public function olt(): BelongsTo
    {
        return $this->belongsTo(Olt::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function project(): HasOne
    {
        return $this->hasOne(Project::class);
    }

    /** Sous-plaques de la plaque. */
    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class);
    }

    /**
     * Plaques visibles par l'utilisateur : le superviseur voit les siennes, le technicien
     * celles de son supérieur (technicien déploiement) ou toutes s'il est du BE (habilitation « dessin »), les autres rôles du back-office tout
     * (dont le chef du BE, qui dimensionne le réseau).
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            Role::Admin, Role::Dg, Role::Dt, Role::Coordinator, Role::BeManager => $query,
            Role::Supervisor => $query->where('supervisor_id', $user->id),
            Role::Technician => $user->hasSkill(MissionType::Drawing) ? $query : ($user->manager_id
                ? $query->where('supervisor_id', $user->manager_id)
                : $query->whereRaw('1 = 0')),
        };
    }
}
