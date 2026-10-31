<?php

namespace App\Domain\Import;

use App\Enums\OrderStatus;
use App\Enums\StudyResult;
use App\Models\CustomerOrder;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Second temps de l'import des clients : crée ou met à jour les commandes des lignes ok ou à corriger qui n'ont pas été exclues.
 *
 * - Tout ou rien (une transaction) ; un lot ne se valide qu'une fois (verrou sur le lot).
 * - Une ligne refusée n'est jamais importée. Réimport d'un ID client connu : mise à jour, sans écraser une valeur par du vide.
 * - Nouvelle commande « SANS PCO » : étudiée (faisable) ; sinon reçue. Chaque création écrit sa ligne d'historique.
 * - Une fois importé, le lot ne garde que l'ID client et le nom de chaque ligne (pas de doublon de données personnelles).
 */
final class ValidateCustomerImport
{
    /** @return array{created: int, updated: int, excluded: int, rejected: int} */
    public function run(ImportBatch $batch, User $by): array
    {
        return DB::transaction(function () use ($batch, $by) {
            $locked = ImportBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'analysed') {
                throw ValidationException::withMessages(['batch' => 'Ce fichier a déjà été importé.']);
            }

            $result = ['created' => 0, 'updated' => 0, 'excluded' => 0, 'rejected' => 0];

            $locked->rows()->chunkById(200, function ($rows) use ($locked, $by, &$result) {
                foreach ($rows as $row) {
                    $result[$this->importRow($row, $locked, $by)]++;
                }
            });

            $locked->forceFill(['status' => 'validated', 'validated_at' => now(), 'counts' => $locked->counts + ['result' => $result]])->save();

            return $result;
        });
    }

    /** @return 'created'|'updated'|'excluded'|'rejected' */
    private function importRow(ImportRow $row, ImportBatch $batch, User $by): string
    {
        if ($row->status === 'rejected') {
            return 'rejected';
        }
        if ($row->excluded) {
            return 'excluded';
        }

        $data = $row->data;
        $values = Arr::only($data, (new CustomerOrder)->getFillable());
        $order = CustomerOrder::query()->where('customer_ref', $data['customer_ref'])->lockForUpdate()->first();

        if ($order !== null) {
            // Une valeur absente du fichier ne vide jamais une valeur déjà connue.
            $order->update(array_filter($values, fn ($value) => $value !== null));
            $outcome = 'updated';
        } else {
            $installed = ($data['initial_status'] ?? null) === 'installed';
            $studied = ! $installed && ($data['initial_study'] ?? null) === 'done';
            $status = $installed ? OrderStatus::Installed : ($studied ? OrderStatus::Studied : OrderStatus::Received);

            $order = new CustomerOrder($values);
            $order->forceFill(['status' => $status, 'study_result' => $studied ? StudyResult::Feasible : null])->save();
            $order->history()->create(['from_status' => null, 'to_status' => $status, 'observation' => "Import du fichier « {$batch->file_name} »", 'changed_by' => $by->id]);
            $outcome = 'created';
        }

        $row->update(['data' => Arr::only($data, ['customer_ref', 'name'])]);

        return $outcome;
    }
}
