<?php

namespace App\Domain\Import;

use App\Enums\Vendor;
use App\Models\Jdv;
use App\Models\Olt;
use App\Models\Pco;
use App\Models\Plaque;
use App\Models\Zone;
use App\Support\CodeNormalizer;
use App\Support\IvoryCoastBounds;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Import du réseau depuis un fichier plat, une ligne par PCO :
 * OLT → plaque → sous-plaque (zone) → splitter (JDV) → PCO.
 *
 * - Création seulement : un élément déjà connu est réutilisé, jamais modifié ; un PCO déjà présent est compté et laissé tel quel.
 * - Une ligne invalide est refusée et listée (jamais corrigée en silence) ; un GPS suspect donne un PCO sans coordonnées et un avertissement.
 * - Le statut du fichier est gardé tel quel (status_raw) : la correspondance avec les statuts normalisés reste à valider.
 * - La vérification exécute le même traitement que l'import puis annule la transaction : mêmes chiffres, rien d'écrit.
 */
final class NetworkImport
{
    public const MAX_ROWS = 5000;

    public const COLUMNS = ['olt', 'plaque', 'sous_plaque', 'fabricant', 'splitter', 'pco', 'latitude', 'longitude', 'slot', 'port', 'brin', 'statut', 'commentaire'];

    public const REQUIRED = ['plaque', 'sous_plaque', 'splitter', 'pco'];

    /** @var array<string, Olt> */
    private array $olts = [];

    /** @var array<string, Plaque> */
    private array $plaques = [];

    /** @var array<string, Zone> */
    private array $zones = [];

    /** @var array<string, Jdv> */
    private array $jdvs = [];

    /** @var array<string, array<string, true>> codes de PCO déjà en base, par zone */
    private array $existingPcos = [];

    /** @var array<string, int> clé de PCO déjà vue dans le fichier → ligne */
    private array $seen = [];

    /** @var array{olts: int, plaques: int, zones: int, jdvs: int, pcos: int} */
    private array $created = ['olts' => 0, 'plaques' => 0, 'zones' => 0, 'jdvs' => 0, 'pcos' => 0];

    private int $alreadyPresent = 0;

    /** @var list<array{line: int, message: string}> */
    private array $rejected = [];

    /** @var list<array{line: int, message: string}> */
    private array $warnings = [];

    public static function parse(string $content): CsvTable
    {
        return CsvTable::fromContent($content, self::COLUMNS, self::REQUIRED, self::MAX_ROWS);
    }

    /**
     * @return array{committed: bool, rows: int, created: array<string, int>, already_present: int, rejected: list<array{line: int, message: string}>, warnings: list<array{line: int, message: string}>}
     */
    public function run(CsvTable $table, bool $commit): array
    {
        DB::beginTransaction();

        try {
            $this->preload();
            foreach ($table->rows as $row) {
                $this->importRow($row['line'], $row['values']);
            }
            $commit ? DB::commit() : DB::rollBack();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'committed' => $commit,
            'rows' => count($table->rows),
            'created' => $this->created,
            'already_present' => $this->alreadyPresent,
            'rejected' => $this->rejected,
            'warnings' => $this->warnings,
        ];
    }

    private function preload(): void
    {
        foreach (Olt::query()->get() as $olt) {
            $this->olts[mb_strtolower($olt->name)] = $olt;
        }
        foreach (Plaque::query()->get() as $plaque) {
            $this->plaques[mb_strtolower($plaque->name)] = $plaque;
        }
        foreach (Zone::query()->get() as $zone) {
            $this->zones[$zone->plaque_id.'|'.$zone->code_normalized] = $zone;
        }
        foreach (Jdv::query()->get() as $jdv) {
            $this->jdvs[$jdv->zone_id.'|'.$jdv->code_normalized] = $jdv;
        }
    }

    /** @param array<string, string> $v */
    private function importRow(int $line, array $v): void
    {
        $get = fn (string $key) => $v[$key] ?? '';

        foreach (self::REQUIRED as $column) {
            if ($get($column) === '') {
                $this->reject($line, "La colonne « {$column} » est vide.");

                return;
            }
        }

        foreach (['olt' => 120, 'plaque' => 120, 'sous_plaque' => 40, 'splitter' => 40, 'pco' => 60, 'slot' => 255, 'port' => 255, 'brin' => 255, 'statut' => 255, 'commentaire' => 2000] as $column => $max) {
            if (mb_strlen($get($column)) > $max) {
                $this->reject($line, "La colonne « {$column} » dépasse {$max} caractères.");

                return;
            }
        }

        $zoneNorm = CodeNormalizer::zone($get('sous_plaque'));
        $pcoNorm = CodeNormalizer::pco($get('pco'));
        $plaqueKey = mb_strtolower($get('plaque'));
        $fileKey = $plaqueKey.'|'.$zoneNorm.'|'.$pcoNorm;

        if (isset($this->seen[$fileKey])) {
            $this->reject($line, 'PCO en double : « '.$get('pco').' » figure déjà à la ligne '.$this->seen[$fileKey].' (non fusionné).');

            return;
        }

        $vendor = null;
        if ($get('fabricant') !== '') {
            $vendor = Vendor::tryFrom(mb_strtolower($get('fabricant')));
            if ($vendor === null) {
                $this->warn($line, 'Fabricant « '.$get('fabricant').' » inconnu (huawei ou nokia) : laissé vide.');
            }
        }

        [$latitude, $longitude] = $this->coordinates($line, $get('latitude'), $get('longitude'));

        if (! str_starts_with($pcoNorm, $zoneNorm.'_PCO')) {
            $this->warn($line, 'Le code du PCO « '.$get('pco').' » ne correspond pas à la sous-plaque « '.$get('sous_plaque').' ».');
        }

        $plaque = $this->plaques[$plaqueKey] ?? null;
        $zone = $plaque ? ($this->zones[$plaque->id.'|'.$zoneNorm] ?? null) : null;

        if ($zone && $this->pcoExists($zone, $pcoNorm)) {
            $this->alreadyPresent++;
            $this->seen[$fileKey] = $line;

            return;
        }

        $olt = $get('olt') !== '' ? $this->olt($get('olt')) : null;
        $plaque ??= $this->createPlaque($get('plaque'));
        $this->attachOlt($line, $plaque, $olt);

        $zone ??= $this->createZone($plaque, $get('sous_plaque'), $zoneNorm, $vendor);
        if ($vendor && $zone->vendor !== null && $zone->vendor !== $vendor) {
            $this->warn($line, 'Le fabricant du fichier diffère de celui de la sous-plaque « '.$zone->code.' » : non modifié.');
        }

        $jdvNorm = CodeNormalizer::generic($get('splitter'));
        $jdv = $this->jdvs[$zone->id.'|'.$jdvNorm] ?? null;
        if (! $jdv) {
            $jdv = Jdv::create(['zone_id' => $zone->id, 'code' => $get('splitter')]);
            $this->jdvs[$zone->id.'|'.$jdvNorm] = $jdv;
            $this->created['jdvs']++;
        }

        Pco::create([
            'jdv_id' => $jdv->id,
            'code' => $get('pco'),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'olt_slot' => $get('slot') ?: null,
            'olt_port' => $get('port') ?: null,
            'fiber_strand' => $get('brin') ?: null,
            'status_raw' => $get('statut') ?: null,
            'comment' => $get('commentaire') ?: null,
        ]);
        $this->created['pcos']++;
        $this->seen[$fileKey] = $line;
    }

    private function pcoExists(Zone $zone, string $pcoNorm): bool
    {
        $this->existingPcos[$zone->id] ??= Pco::query()->where('zone_id', $zone->id)->pluck('code_normalized')->flip()->map(fn () => true)->all();

        return isset($this->existingPcos[$zone->id][$pcoNorm]);
    }

    private function olt(string $name): Olt
    {
        return $this->olts[mb_strtolower($name)] ??= tap(Olt::create(['name' => $name]), fn () => $this->created['olts']++);
    }

    private function createPlaque(string $name): Plaque
    {
        $plaque = Plaque::create(['name' => $name]);
        $this->plaques[mb_strtolower($name)] = $plaque;
        $this->created['plaques']++;

        return $plaque;
    }

    private function attachOlt(int $line, Plaque $plaque, ?Olt $olt): void
    {
        if ($olt === null) {
            return;
        }
        if ($plaque->olt_id === null) {
            $plaque->update(['olt_id' => $olt->id]);
        } elseif ($plaque->olt_id !== $olt->id) {
            $this->warn($line, "L'OLT « {$olt->name} » diffère de celui déjà rattaché à la plaque « {$plaque->name} » : non modifié.");
        }
    }

    private function createZone(Plaque $plaque, string $code, string $norm, ?Vendor $vendor): Zone
    {
        $zone = Zone::create(['plaque_id' => $plaque->id, 'code' => $code, 'vendor' => $vendor]);
        $this->zones[$plaque->id.'|'.$norm] = $zone;
        $this->created['zones']++;

        return $zone;
    }

    /** @return array{0: ?string, 1: ?string} */
    private function coordinates(int $line, string $lat, string $lon): array
    {
        if ($lat === '' && $lon === '') {
            return [null, null];
        }

        $latitude = $this->toFloat($lat);
        $longitude = $this->toFloat($lon);

        if ($latitude === null || $longitude === null) {
            $this->warn($line, 'Coordonnées GPS incomplètes ou illisibles : PCO importé sans coordonnées.');

            return [null, null];
        }

        if (! IvoryCoastBounds::contains($latitude, $longitude)) {
            $this->warn($line, IvoryCoastBounds::looksInverted($latitude, $longitude)
                ? 'Latitude et longitude semblent inversées : PCO importé sans coordonnées (à corriger dans le fichier).'
                : 'Coordonnées hors de la Côte d\'Ivoire : PCO importé sans coordonnées.');

            return [null, null];
        }

        return [number_format($latitude, 7, '.', ''), number_format($longitude, 7, '.', '')];
    }

    private function toFloat(string $value): ?float
    {
        $value = str_replace(',', '.', trim($value));

        return $value !== '' && is_numeric($value) ? (float) $value : null;
    }

    private function reject(int $line, string $message): void
    {
        $this->rejected[] = ['line' => $line, 'message' => $message];
    }

    private function warn(int $line, string $message): void
    {
        $this->warnings[] = ['line' => $line, 'message' => $message];
    }
}
