<?php

namespace App\Domain\Network;

use App\Enums\PcoStatus;
use App\Models\Jdv;
use App\Models\Pco;
use App\Models\Sro;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Génère le réseau standard d'une sous-plaque : sa SRO, ses splitters (SPL001…) et ses PCO ({CODE}_PCO001…).
 *
 * Les PCO sont numérotés en continu dans la sous-plaque, `$splitterSize` par splitter (8 par défaut, le dernier
 * splitter peut en recevoir moins). Tout est créé dans une seule transaction, et la génération est refusée si la
 * sous-plaque contient déjà des splitters ou des PCO : on n'écrase jamais un travail existant.
 */
class GenerateZoneNetwork
{
    public const DEFAULT_SPLITTER_SIZE = 8;

    public const PCO_PORTS = 16;

    /** @return array{sro_created: bool, splitters: int, pcos: int} */
    public function run(Zone $zone, int $pcoCount, ?string $sroCode, int $splitterSize = self::DEFAULT_SPLITTER_SIZE, ?User $by = null): array
    {
        return DB::transaction(function () use ($zone, $pcoCount, $sroCode, $splitterSize, $by) {
            // Verrou sur la sous-plaque : deux générations simultanées ne peuvent pas se doubler.
            Zone::query()->whereKey($zone->id)->lockForUpdate()->first();

            if (Jdv::query()->where('zone_id', $zone->id)->exists() || Pco::query()->where('zone_id', $zone->id)->exists()) {
                throw ValidationException::withMessages(['zone' => 'Cette sous-plaque contient déjà des splitters ou des PCO : la génération est refusée pour ne rien écraser.']);
            }

            $sroCreated = false;
            if (! Sro::query()->where('zone_id', $zone->id)->exists()) {
                Sro::create(['zone_id' => $zone->id, 'code' => $sroCode]);
                $sroCreated = true;
            }

            $splitters = (int) ceil($pcoCount / $splitterSize);
            $width = max(3, strlen((string) $pcoCount));

            for ($s = 1; $s <= $splitters; $s++) {
                $jdv = Jdv::create(['zone_id' => $zone->id, 'code' => 'SPL'.str_pad((string) $s, 3, '0', STR_PAD_LEFT)]);

                $first = ($s - 1) * $splitterSize + 1;
                for ($n = $first; $n <= min($pcoCount, $first + $splitterSize - 1); $n++) {
                    $pco = Pco::create([
                        'jdv_id' => $jdv->id,
                        'code' => $zone->code.'_PCO'.str_pad((string) $n, $width, '0', STR_PAD_LEFT),
                        'status' => PcoStatus::ToPull,
                        'capacity' => self::PCO_PORTS,
                    ]);
                    $pco->history()->create(['from_status' => null, 'to_status' => PcoStatus::ToPull, 'observation' => 'Généré avec la sous-plaque', 'changed_by' => $by?->getKey()]);
                }
            }

            return ['sro_created' => $sroCreated, 'splitters' => $splitters, 'pcos' => $pcoCount];
        });
    }
}
