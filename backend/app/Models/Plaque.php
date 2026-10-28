<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    /** Sous-plaques de la plaque. */
    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class);
    }

    /**
     * Plaques visibles par l'utilisateur : le superviseur voit les siennes, le technicien
     * celles de son supérieur (technicien déploiement), les autres rôles du back-office tout.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            Role::Admin, Role::Dg, Role::Dt, Role::Coordinator => $query,
            Role::Supervisor => $query->where('supervisor_id', $user->id),
            Role::Technician => $user->manager_id
                ? $query->where('supervisor_id', $user->manager_id)
                : $query->whereRaw('1 = 0'),
        };
    }
}
