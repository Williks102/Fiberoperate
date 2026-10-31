<?php

return [
    // Distance proposée au technicien : nombre de poteaux du trajet × l'écart entre deux poteaux (modifiable à la saisie).
    'pole_spacing_m' => (int) env('STUDY_POLE_SPACING_M', 50),

    // Types de poteaux comptés par défaut : concrete (béton), metal (métalliques). À confirmer avec l'opérateur (cahier §9).
    'distance_pole_types' => array_values(array_filter(explode(',', (string) env('STUDY_DISTANCE_POLE_TYPES', 'concrete,metal')))),
];
