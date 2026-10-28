<?php

namespace App\Enums;

/**
 * Fabricant de l'équipement. Il détermine le nom de la sous-plaque :
 * « hub » chez Huawei, « SRO » chez Nokia.
 */
enum Vendor: string
{
    case Huawei = 'huawei';
    case Nokia = 'nokia';

    public function label(): string
    {
        return match ($this) {
            self::Huawei => 'Huawei',
            self::Nokia => 'Nokia',
        };
    }

    public function accessPointLabel(): string
    {
        return match ($this) {
            self::Huawei => 'Hub',
            self::Nokia => 'SRO',
        };
    }
}
