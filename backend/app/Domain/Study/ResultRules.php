<?php

namespace App\Domain\Study;

use App\Enums\StudyResult;
use Illuminate\Validation\Rule;

/** Règles du résultat d'une étude et d'une installation : une seule source pour l'envoi en ligne et la synchronisation du terrain. */
final class ResultRules
{
    /** @return array<string, list<mixed>> */
    public static function study(): array
    {
        $pole = ['nullable', 'integer', 'min:0', 'max:500'];

        return [
            'result' => ['required', Rule::enum(StudyResult::class)],
            'pco_id' => ['required_if:result,feasible', 'nullable', 'uuid', Rule::exists('pcos', 'id')->whereNull('deleted_at')],
            'concrete_poles_existing' => $pole,
            'concrete_poles_to_install' => $pole,
            'metal_poles_existing' => $pole,
            'metal_poles_to_install' => $pole,
            'distance_m' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'problem' => ['required_if:result,not_feasible', 'nullable', 'string', 'max:1000'],
            'field_comments' => ['nullable', 'string', 'max:2000'],
            'internal_comments' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
        ];
    }

    /** @return array<string, string> */
    public static function studyMessages(): array
    {
        return [
            'result.required' => "Indiquez le résultat de l'étude.",
            'pco_id.required_if' => 'Choisissez le PCO de rattachement : le client est raccordable.',
            'problem.required_if' => 'Indiquez le problème rencontré : le client est non raccordable.',
        ];
    }

    /** @return array<string, list<mixed>> */
    public static function installation(): array
    {
        return [
            'result' => ['required', Rule::in(['installed', 'postponed', 'failed'])],
            'reason_id' => ['required_if:result,postponed,failed', 'nullable', 'uuid', Rule::exists('outcome_reasons', 'id')->where('is_active', true)],
            'observation' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public static function installationMessages(): array
    {
        return [
            'result.required' => "Indiquez le résultat de l'installation.",
            'reason_id.required_if' => "Choisissez le motif du report ou de l'échec.",
        ];
    }
}
