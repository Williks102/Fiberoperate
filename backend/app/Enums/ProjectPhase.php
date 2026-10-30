<?php

namespace App\Enums;

/** Phases d'un projet de déploiement, dans l'ordre du parcours (docs/workflow-projet-deploiement.md). */
enum ProjectPhase: string
{
    case PreProject = 'pre_project';
    case BeManagerReview = 'be_manager_review';
    case DtReview = 'dt_review';
    case ClientReview = 'client_review';
    case ImplantationSurvey = 'implantation_survey';
    case Deployment = 'deployment';
    case AsBuiltSurvey = 'as_built_survey';
    case InOperation = 'in_operation';

    /** Le chef du BE et ses techniciens ajustent le réseau tant que le plan n'est pas soumis aux validations. */
    public function beMayEditNetwork(): bool
    {
        return $this === self::PreProject;
    }

    public function label(): string
    {
        return match ($this) {
            self::PreProject => 'Avant-projet',
            self::BeManagerReview => 'Validation du chef du BE',
            self::DtReview => 'Validation du DT',
            self::ClientReview => 'Validation du client',
            self::ImplantationSurvey => "Survey d'implantation",
            self::Deployment => 'Déploiement',
            self::AsBuiltSurvey => 'Relevé final',
            self::InOperation => 'Exploitation',
        };
    }
}
