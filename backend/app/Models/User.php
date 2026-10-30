<?php

namespace App\Models;

use App\Enums\MissionType;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasUuids, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'manager_id',
        'is_active',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }

    /** Supérieur hiérarchique (technicien → superviseur ou coordinateur → DT). */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    public function skills(): HasMany
    {
        return $this->hasMany(TechnicianSkill::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members')->withTimestamps();
    }

    /** Plaques couvertes par ce superviseur. */
    public function supervisedPlaques(): HasMany
    {
        return $this->hasMany(Plaque::class, 'supervisor_id');
    }

    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /** Chef du BE ou technicien du BE (habilitation « dessin ») : ils dessinent et ajustent le réseau d'un projet. */
    public function isBeMember(): bool
    {
        return $this->hasRole(Role::BeManager) || ($this->hasRole(Role::Technician) && $this->hasSkill(MissionType::Drawing));
    }

    /** Voit toutes les plaques : le BE (chef, techniciens « dessin » et « relevés ») consulte le réseau de tous les projets. */
    public function seesWholeNetwork(): bool
    {
        return $this->hasRole(Role::BeManager) || ($this->hasRole(Role::Technician) && ($this->hasSkill(MissionType::Drawing) || $this->hasSkill(MissionType::Survey)));
    }

    /** Peut enregistrer la position mesurée d'un PCO : administrateur, chef du BE, techniciens « relevés » et « dessin ». */
    public function canSurvey(): bool
    {
        return $this->hasRole(Role::Admin) || $this->isBeMember() || ($this->hasRole(Role::Technician) && $this->hasSkill(MissionType::Survey));
    }

    /** Un technicien n'est planifiable que sur une mission pour laquelle il est habilité. */
    public function hasSkill(MissionType $mission): bool
    {
        return $this->skills()->where('mission_type', $mission->value)->exists();
    }
}
