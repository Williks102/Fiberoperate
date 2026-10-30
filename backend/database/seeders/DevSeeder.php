<?php

namespace Database\Seeders;

use App\Enums\MissionType;
use App\Enums\Role;
use App\Enums\Vendor;
use App\Models\Jdv;
use App\Models\Olt;
use App\Models\Pco;
use App\Models\Plaque;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Seeder;

/** Données de démonstration, uniquement en local. Nombres de JDV/PCO volontairement variables. */
class DevSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $make = fn (string $name, string $email, Role $role, ?User $manager = null) => User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'role' => $role, 'password' => 'password', 'manager_id' => $manager?->id, 'is_active' => true],
        );

        $dt = $make('Directeur technique', 'dt@example.test', Role::Dt);
        $make('Admin', 'admin@example.test', Role::Admin);
        $make('DG', 'dg@example.test', Role::Dg);
        $beManager = $make('Chef du BE', 'chef.be@example.test', Role::BeManager, $dt);
        $drafter = $make('Dessinateur BE', 'dessinateur@example.test', Role::Technician, $beManager);
        $drafter->skills()->firstOrCreate(['mission_type' => MissionType::Drawing->value]);
        $surveyor = $make('Technicien réseau BE', 'technicien.be@example.test', Role::Technician, $beManager);
        foreach ([MissionType::Survey, MissionType::Study] as $mission) {
            $surveyor->skills()->firstOrCreate(['mission_type' => $mission->value]);
        }
        $supervisor = $make('Superviseur Faya', 'superviseur@example.test', Role::Supervisor, $dt);
        $coordinator = $make('Coordinateur', 'coordinateur@example.test', Role::Coordinator, $dt);

        $deployTech = $make('Technicien déploiement', 'tech.deploiement@example.test', Role::Technician, $supervisor);
        $deployTech->skills()->firstOrCreate(['mission_type' => MissionType::Deployment->value]);

        $fieldTech = $make('Technicien raccordement', 'tech.raccordement@example.test', Role::Technician, $coordinator);
        foreach ([MissionType::CustomerConnection, MissionType::Study] as $mission) {
            $fieldTech->skills()->firstOrCreate(['mission_type' => $mission->value]);
        }

        $olt = Olt::firstOrCreate(['name' => 'OLT-DEMO']);
        $plaque = Plaque::firstOrCreate(['name' => 'FAYA'], ['olt_id' => $olt->id, 'supervisor_id' => $supervisor->id]);

        $zone = Zone::firstOrCreate(
            ['plaque_id' => $plaque->id, 'code' => 'FAY03'],
            ['name' => 'FAYA 03', 'vendor' => Vendor::Nokia],
        );

        foreach ([3, 5] as $i => $pcoCount) {
            $jdv = Jdv::firstOrCreate(['zone_id' => $zone->id, 'code' => sprintf('SPL%03d', $i + 1)]);
            for ($n = 1; $n <= $pcoCount; $n++) {
                $number = ($i * 10) + $n;
                Pco::firstOrCreate(
                    ['zone_id' => $zone->id, 'code' => sprintf('FAY03_PCO%03d', $number)],
                    ['jdv_id' => $jdv->id],
                );
            }
        }
    }
}
