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

    /** @return list<Role> Rôles qui rendent la décision de cette phase de validation (aucun si ce n'est pas une phase de décision). */
    public function deciders(): array
    {
        return match ($this) {
            self::BeManagerReview => [Role::BeManager, Role::Admin],
            self::DtReview => [Role::Dt, Role::Admin],
            // Le client n'a pas de compte : le DT ou le chef du BE enregistre sa décision.
            self::ClientReview => [Role::Dt, Role::BeManager, Role::Admin],
            default => [],
        };
    }

    /** Phase atteinte quand la décision est « validé » (un refus renvoie toujours en avant-projet). */
    public function nextOnApproval(): ?self
    {
        return match ($this) {
            self::BeManagerReview => self::DtReview,
            self::DtReview => self::ClientReview,
            self::ClientReview => self::ImplantationSurvey,
            default => null,
        };
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
