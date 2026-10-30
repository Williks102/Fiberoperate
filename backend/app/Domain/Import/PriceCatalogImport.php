<?php

namespace App\Domain\Import;

use App\Models\PriceItem;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Import du catalogue de prix (bordereau de l'opérateur) depuis un fichier CSV, une ligne par article.
 *
 * - Une ligne est identifiée par rubrique + référence + désignation : déjà connue, elle est mise à jour (prix, unité) ;
 *   sinon elle est créée. Un article absent du fichier n'est jamais supprimé.
 * - Tout ou rien : une ligne invalide est refusée et listée. La vérification exécute le même traitement puis annule.
 * - Les devis des projets gardent le prix du moment (copie), une mise à jour du catalogue ne les modifie pas.
 */
final class PriceCatalogImport
{
    public const MAX_ROWS = 2000;

    public const COLUMNS = ['ref', 'type', 'rubrique', 'designation', 'unite', 'prix_unitaire'];

    public const REQUIRED = ['type', 'rubrique', 'designation'];

    private const KINDS = ['supply' => 'supply', 'fourniture' => 'supply', 'service' => 'service', 'prestation' => 'service'];

    public static function parse(string $content): CsvTable
    {
        return CsvTable::fromContent($content, self::COLUMNS, self::REQUIRED, self::MAX_ROWS);
    }

    /**
     * @return array{committed: bool, rows: int, created: int, updated: int, unchanged: int, rejected: list<array{line: int, message: string}>}
     */
    public function run(CsvTable $table, bool $commit): array
    {
        $report = ['committed' => $commit, 'rows' => count($table->rows), 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'rejected' => []];
        $seen = [];

        DB::beginTransaction();

        try {
            foreach ($table->rows as $order => $row) {
                $this->importRow($row['line'], $row['values'], $order + 1, $seen, $report);
            }
            $commit ? DB::commit() : DB::rollBack();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $report;
    }

    /**
     * @param  array<string, string>  $v
     * @param  array<string, int>  $seen
     * @param  array{committed: bool, rows: int, created: int, updated: int, unchanged: int, rejected: list<array{line: int, message: string}>}  $report
     */
    private function importRow(int $line, array $v, int $order, array &$seen, array &$report): void
    {
        $kind = self::KINDS[mb_strtolower($v['type'])] ?? null;
        $price = $v['prix_unitaire'] ?? '';
        $ref = ($v['ref'] ?? '') === '' ? null : $v['ref'];
        $unit = ($v['unite'] ?? '') === '' ? null : $v['unite'];

        $error = match (true) {
            $kind === null => "La colonne « type » doit valoir « fourniture » ou « prestation » (reçu : « {$v['type']} »).",
            $v['rubrique'] === '' || $v['designation'] === '' => 'La rubrique et la désignation sont obligatoires.',
            mb_strlen($v['rubrique']) > 255 || mb_strlen($v['designation']) > 1000 => 'La rubrique ou la désignation est trop longue.',
            ($ref !== null && mb_strlen($ref) > 40) || ($unit !== null && mb_strlen($unit) > 20) => 'La référence (40 caractères) ou l\'unité (20 caractères) est trop longue.',
            $price !== '' && ! preg_match('/^\d{1,12}$/', $price) => "Le prix « {$price} » doit être un nombre entier de FCFA, sans séparateur ni décimale.",
            default => null,
        };

        if ($error !== null) {
            $report['rejected'][] = ['line' => $line, 'message' => $error];

            return;
        }

        $key = PriceItem::keyFor($v['rubrique'], $ref, $v['designation']);
        if (isset($seen[$key])) {
            $report['rejected'][] = ['line' => $line, 'message' => "Article en double : déjà présent à la ligne {$seen[$key]} (même rubrique, référence et désignation)."];

            return;
        }
        $seen[$key] = $line;

        $attributes = ['kind' => $kind, 'unit' => $unit, 'unit_price' => $price === '' ? null : (int) $price, 'sort_order' => $order];
        $existing = PriceItem::query()->where('item_key', $key)->first();

        if ($existing === null) {
            PriceItem::create($attributes + ['section' => $v['rubrique'], 'ref' => $ref, 'designation' => $v['designation']]);
            $report['created']++;
        } elseif ($existing->fill($attributes)->isDirty(['kind', 'unit', 'unit_price'])) {
            $existing->save();
            $report['updated']++;
        } else {
            $report['unchanged']++;
        }
    }
}
