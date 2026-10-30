<?php

namespace App\Domain\Project;

use App\Models\PriceItem;
use App\Models\Project;
use App\Models\ProjectQuoteLine;

/**
 * Ajoute ou met à jour une ligne du devis. À l'ajout, le libellé et le prix du catalogue sont copiés : le devis
 * ne bouge plus quand le catalogue change.
 */
class SaveQuoteLine
{
    public function run(Project $project, PriceItem $item, ?int $studyQuantity, ?int $doneQuantity): ProjectQuoteLine
    {
        $line = ProjectQuoteLine::query()->where('project_id', $project->id)->where('price_item_id', $item->id)->first()
            ?? new ProjectQuoteLine([
                'project_id' => $project->id,
                'price_item_id' => $item->id,
                'kind' => $item->kind,
                'section' => $item->section,
                'ref' => $item->ref,
                'designation' => $item->designation,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
            ]);

        if ($studyQuantity !== null) {
            $line->study_quantity = $studyQuantity;
        }
        if ($doneQuantity !== null) {
            $line->done_quantity = $doneQuantity;
        }
        $line->save();

        return $line;
    }
}
