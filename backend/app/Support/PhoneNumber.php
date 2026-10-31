<?php

namespace App\Support;

/**
 * Numéros ivoiriens : « +225 » suivi de 10 chiffres. Les fichiers contiennent des variantes (préfixe « * »,
 * 13 chiffres avec 225, 12 chiffres, 9 chiffres quand le zéro initial a disparu parce que le numéro était stocké comme nombre).
 * Rien n'est corrigé en silence : le résultat dit ce qui a été fait.
 */
final class PhoneNumber
{
    /**
     * @return array{normalized: ?string, note: ?string} `normalized` vaut null quand le numéro est inexploitable ; `note` explique une correction ou une anomalie
     */
    public static function normalize(string|int|float|null $raw): array
    {
        $text = is_float($raw) && floor($raw) === $raw ? (string) (int) $raw : trim((string) $raw);
        // Un nombre exporté par Excel peut arriver sous la forme « 707123456.0 ».
        $text = preg_replace('/^(\d+)[.,]0+$/', '$1', $text) ?? $text;
        $digits = preg_replace('/\D+/', '', ltrim($text, '*+ ')) ?? '';

        if ($digits === '') {
            return ['normalized' => null, 'note' => null];
        }

        $note = null;
        if (str_starts_with($digits, '00225')) {
            $digits = substr($digits, 5);
        } elseif (str_starts_with($digits, '225') && strlen($digits) >= 12) {
            $digits = substr($digits, 3);
        }

        if (strlen($digits) === 9) {
            $digits = '0'.$digits;
            $note = 'Zéro initial ajouté (numéro à 9 chiffres).';
        }

        if (strlen($digits) !== 10) {
            return ['normalized' => null, 'note' => 'Longueur anormale ('.strlen($digits).' chiffres) : numéro à vérifier.'];
        }

        return ['normalized' => '+225'.$digits, 'note' => $note ?? (str_starts_with($text, '*') ? 'Préfixe « * » retiré.' : null)];
    }
}
