<?php

namespace App\Support;

/** Zone attendue des coordonnées GPS (Côte d'Ivoire) : sert à repérer les valeurs aberrantes ou inversées. */
final class IvoryCoastBounds
{
    private const LATITUDE = [4.3, 10.8];

    private const LONGITUDE = [-8.7, -2.4];

    public static function contains(float $latitude, float $longitude): bool
    {
        return self::within($latitude, self::LATITUDE) && self::within($longitude, self::LONGITUDE);
    }

    /** Latitude et longitude semblent échangées : hors zone, mais dans la zone une fois inversées. */
    public static function looksInverted(float $latitude, float $longitude): bool
    {
        return ! self::contains($latitude, $longitude) && self::contains($longitude, $latitude);
    }

    /** @param array{0: float, 1: float} $range */
    private static function within(float $value, array $range): bool
    {
        return $value >= $range[0] && $value <= $range[1];
    }
}
