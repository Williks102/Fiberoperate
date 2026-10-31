<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * Étude technique initiale, vocabulaire de l'opérateur à ne pas inverser :
 * « Sans PCO » = étude réalisée (client raccordable) ; « Avec PCO » = étude à réaliser.
 */
enum InitialStudy: string
{
    case Done = 'done';
    case ToDo = 'to_do';

    public function label(): string
    {
        return match ($this) {
            self::Done => 'Étude réalisée (raccordable)',
            self::ToDo => 'Étude à réaliser',
        };
    }

    /** Libellé de l'opérateur d'un fichier, comparé sans espaces parasites ni casse ; null si inconnu (à proposer à la correspondance, jamais ignoré). */
    public static function fromOperatorLabel(?string $label): ?self
    {
        return match (preg_replace('/\s+/', ' ', Str::ascii(mb_strtolower(trim((string) $label))))) {
            'sans pco' => self::Done,
            'avec pco', 'etude non realisee' => self::ToDo, // « Etude Non Réalisée » : libellé des fichiers d'instances
            default => null,
        };
    }

    /** Libellé de l'opérateur pour l'export de la fiche de suivi. */
    public function operatorLabel(): string
    {
        return match ($this) {
            self::Done => 'SANS PCO',
            self::ToDo => 'AVEC PCO',
        };
    }
}
