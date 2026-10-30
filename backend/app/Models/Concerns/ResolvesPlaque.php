<?php

namespace App\Models\Concerns;

use App\Models\Zone;

/** Modèles rattachés à une sous-plaque (zone) : leur plaque se déduit de la zone. */
trait ResolvesPlaque
{
    public function plaqueId(): ?string
    {
        return Zone::query()->whereKey($this->zone_id)->value('plaque_id');
    }
}
