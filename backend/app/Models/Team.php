<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Équipe de terrain : un chef d'équipe, des membres, un coordinateur. */
class Team extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['name', 'leader_id', 'coordinator_id'];

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members')->withTimestamps();
    }

    /** Le coordinateur ne voit que ses équipes ; admin, DG et DT toutes. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            Role::Admin, Role::Dg, Role::Dt => $query,
            Role::Coordinator => $query->where('coordinator_id', $user->id),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
