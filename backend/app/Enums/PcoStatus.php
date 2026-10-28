<?php

namespace App\Enums;

/**
 * Statuts normalisés d'un PCO. Pas d'étape de recette (décision du client).
 *
 * « Raccordé » = soudure du PCO terminée (et non client branché).
 * La correspondance avec les libellés libres du fichier de suivi
 * (« En attente BE », « Travaux en cours », « Tirage NOK »…) reste à valider :
 * en attendant, le libellé d'origine est conservé dans pcos.status_raw et le
 * statut normalisé peut rester nul. « Bloqué » est un indicateur (is_blocked),
 * pas un statut.
 */
enum PcoStatus: string
{
    case ToPull = 'to_pull';
    case Pulled = 'pulled';
    case Spliced = 'spliced';
    case Activated = 'activated';

    public function label(): string
    {
        return match ($this) {
            self::ToPull => 'À tirer',
            self::Pulled => 'Tiré',
            self::Spliced => 'Raccordé (soudure terminée)',
            self::Activated => 'Activé',
        };
    }
}
