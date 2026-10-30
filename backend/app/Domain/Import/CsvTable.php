<?php

namespace App\Domain\Import;

use Illuminate\Support\Str;

/**
 * Lecture d'un fichier CSV d'import : séparateur point-virgule, virgule ou tabulation,
 * UTF-8 (avec ou sans BOM) ou Windows-1252 (le « CSV » d'Excel).
 */
final class CsvTable
{
    /**
     * @param  list<array{line: int, values: array<string, string>}>  $rows
     */
    private function __construct(public readonly array $rows) {}

    /**
     * @param  list<string>  $allowed  colonnes acceptées (noms canoniques)
     * @param  list<string>  $required  colonnes obligatoires
     */
    public static function fromContent(string $content, array $allowed, array $required, int $maxRows): self
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        if (trim($content) === '') {
            throw new ImportFileException('Le fichier est vide.');
        }

        $firstLine = strtok($content, "\r\n") ?: '';
        $delimiter = collect([';', ',', "\t"])->sortByDesc(fn ($d) => substr_count($firstLine, $d))->first();

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $headers = null;
        $rows = [];
        $line = 0;

        while (($record = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
            $line++;

            if ($record === [null] || count(array_filter($record, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            if ($headers === null) {
                $headers = self::readHeaders($record, $allowed, $required);

                continue;
            }

            if (count($rows) >= $maxRows) {
                throw new ImportFileException("Le fichier contient plus de {$maxRows} lignes : découpez-le en plusieurs fichiers.");
            }

            $values = [];
            foreach ($headers as $i => $name) {
                $values[$name] = trim((string) ($record[$i] ?? ''));
            }
            $rows[] = ['line' => $line, 'values' => $values];
        }
        fclose($stream);

        if ($rows === []) {
            throw new ImportFileException('Le fichier ne contient aucune ligne de données sous les en-têtes.');
        }

        return new self($rows);
    }

    /** @return array<int, string> */
    private static function readHeaders(array $record, array $allowed, array $required): array
    {
        $headers = [];
        foreach ($record as $i => $raw) {
            $name = trim(Str::of(Str::ascii((string) $raw))->lower()->replaceMatches('/[^a-z0-9]+/', '_')->toString(), '_');
            if ($name === '') {
                continue;
            }
            if (! in_array($name, $allowed, true)) {
                throw new ImportFileException('Colonne inconnue : « '.trim((string) $raw).' ». Utilisez les en-têtes du modèle sans les modifier.');
            }
            $headers[$i] = $name;
        }

        $missing = array_diff($required, $headers);
        if ($missing !== []) {
            throw new ImportFileException('Colonne manquante : '.implode(', ', array_map(fn ($m) => "« {$m} »", $missing)).'. Utilisez les en-têtes du modèle.');
        }

        return $headers;
    }
}
