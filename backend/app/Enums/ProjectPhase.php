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
            // Le client n'a pas de compte : le DT enregistre son accord, le BE en est notifié.
            self::ClientReview => [Role::Dt, Role::Admin],
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

    /** Phase suivante quand le BE fait avancer le projet (les phases de validation passent par une décision). */
    public function nextOnAdvance(): ?self
    {
        return match ($this) {
            self::ImplantationSurvey => self::Deployment,
            self::Deployment => self::AsBuiltSurvey,
            self::AsBuiltSurvey => self::InOperation,
            default => null,
        };
    }

    /** Quantités d'étude du devis : modifiables par le BE tant que le dossier n'est pas envoyé en validation. */
    public function quoteStudyEditable(): bool
    {
        return $this === self::PreProject;
    }

    /** Quantités réalisées du devis : saisies pendant et après le déploiement. */
    public function quoteDoneEditable(): bool
    {
        return in_array($this, [self::Deployment, self::AsBuiltSurvey, self::InOperation], true);
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
