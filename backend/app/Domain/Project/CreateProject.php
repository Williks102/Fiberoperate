<?php

namespace App\Domain\Project;

use App\Domain\Network\GenerateZoneNetwork;
use App\Models\Olt;
use App\Models\Plaque;
use App\Models\Project;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Support\Facades\DB;

/**
 * Création guidée d'un projet : la plaque naît avec lui (nom, OLT choisi ou créé), puis ses sous-plaques.
 * Pour chaque sous-plaque qui indique un nombre de PCO, le réseau standard est généré (SRO, splitters, PCO).
 * Tout est créé dans une seule transaction.
 */
class CreateProject
{
    public function __construct(private readonly GenerateZoneNetwork $generator) {}

    /**
     * @param  array{plaque_name: string, olt_id?: ?string, olt_name?: ?string, zones?: list<array{code: string, pco_count?: ?int, sro_code?: ?string}>}  $data
     */
    public function run(array $data, User $by): Project
    {
        return DB::transaction(function () use ($data, $by) {
            $oltId = $data['olt_id'] ?? null;
            if ($oltId === null && filled($data['olt_name'] ?? null)) {
                $oltId = Olt::create(['name' => $data['olt_name']])->id;
            }

            $plaque = Plaque::create(['name' => $data['plaque_name'], 'olt_id' => $oltId]);

            foreach ($data['zones'] ?? [] as $row) {
                $zone = Zone::create(['plaque_id' => $plaque->id, 'code' => $row['code']]);

                if (! empty($row['pco_count'])) {
                    $this->generator->run($zone, (int) $row['pco_count'], $row['sro_code'] ?? null, by: $by);
                }
            }

            return Project::startFor($plaque, $by);
        });
    }
}
