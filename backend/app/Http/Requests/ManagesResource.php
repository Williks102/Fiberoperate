<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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

    public function authorize(): bool
    {
        $id = $this->route($this->routeKey());

        if ($id === null) {
            return $this->user()->can('create', $this->modelClass());
        }

        $model = $this->modelClass()::query()->findOrFail($id);

        return $this->user()->can('update', $model);
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
