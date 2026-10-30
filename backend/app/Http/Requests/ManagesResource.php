<?php

namespace App\Http\Requests;

use App\Models\Zone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Base des requêtes d'écriture : le contrôle d'accès passe par la Policy dans authorize(),
 * pour qu'un utilisateur non autorisé reçoive 403 et jamais le détail de la validation.
 */
abstract class ManagesResource extends FormRequest
{
    /** Classe du modèle géré. */
    abstract protected function modelClass(): string;

    /** Nom du paramètre de route (ex. « plaque »). */
    abstract protected function routeKey(): string;

    /** Plaque visée par une création (null : seul l'administrateur peut écrire). */
    protected function targetPlaqueId(): ?string
    {
        return null;
    }

    public function authorize(): bool
    {
        $id = $this->route($this->routeKey());

        if ($id === null) {
            return $this->user()->can('create', [$this->modelClass(), $this->targetPlaqueId()]);
        }

        $model = $this->modelClass()::query()->findOrFail($id);

        return $this->user()->can('update', $model);
    }

    protected function validUuid(mixed $value): ?string
    {
        return is_string($value) && Str::isUuid($value) ? $value : null;
    }

    protected function plaqueOfZone(mixed $zoneId): ?string
    {
        $zoneId = $this->validUuid($zoneId);

        return $zoneId === null ? null : Zone::query()->whereKey($zoneId)->value('plaque_id');
    }

    /** « required » à la création, « sometimes » en modification partielle. */
    protected function req(): string
    {
        return $this->isMethod('POST') ? 'required' : 'sometimes';
    }

    /** Identifiant de la ressource modifiée (pour les règles d'unicité), null à la création. */
    protected function ignoreId(): ?string
    {
        $id = $this->route($this->routeKey());

        return $id === null ? null : (string) $id;
    }
}
