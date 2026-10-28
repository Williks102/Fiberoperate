<?php

namespace App\Support;

/**
 * Normalise les codes du réseau pour les comparer malgré les variantes de
 * saisie observées dans les fichiers de suivi : tirets, espaces, zéros.
 *
 *   zone('FAY03')               → 'FAY003'
 *   zone('AGC 01')              → 'AGC001'
 *   pco('ACA03_PCO-001')        → 'ACA003_PCO001'
 *   pco('ACA003_PCO-001')       → 'ACA003_PCO001'
 */
final class CodeNormalizer
{
    /** Majuscules, sans espaces ni tirets : « spl 001 » → « SPL001 ». */
    public static function generic(?string $code): string
    {
        $code = mb_strtoupper(trim((string) $code));

        return preg_replace('/[\s\-]+/u', '', $code) ?? '';
    }

    /** Code de sous-plaque : préfixe en lettres + numéro sur 3 chiffres. */
    public static function zone(?string $code): string
    {
        $clean = self::generic($code);

        if (preg_match('/^([0-9]*[A-Z]+)0*(\d+)$/', $clean, $m) !== 1) {
            return $clean;
        }

        return $m[1].str_pad($m[2], 3, '0', STR_PAD_LEFT);
    }

    /** Code de PCO : sous-plaque normalisée + « _PCO » + numéro sur 3 chiffres. */
    public static function pco(?string $code): string
    {
        $raw = mb_strtoupper(trim((string) $code));

        if (preg_match('/^(.*?)[\s_\-]*PCO[\s_\-]*0*(\d+)$/u', $raw, $m) !== 1) {
            return self::generic($raw);
        }

        return self::zone($m[1]).'_PCO'.str_pad($m[2], 3, '0', STR_PAD_LEFT);
    }
}
