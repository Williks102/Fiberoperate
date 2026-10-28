<?php

namespace Database\Seeders;

use App\Models\BlockReason;
use Illuminate\Database\Seeder;

/** Motifs de blocage observés dans le fichier de suivi (liste à valider avec le client). */
class BlockReasonSeeder extends Seeder
{
    public function run(): void
    {
        $reasons = [
            'syndic_blocked' => 'Blocage syndic',
            'estate_no_access' => 'Pas d\'accès à la cité',
            'cabling_authorization_nok' => 'Autorisation de câblage NOK',
            'olt_jumper_needed' => 'Jarretière OLT à poser',
        ];

        foreach ($reasons as $code => $label) {
            BlockReason::updateOrCreate(['code' => $code], ['label' => $label, 'is_active' => true]);
        }
    }
}
