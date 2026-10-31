<?php

namespace App\Domain\Import;

use App\Enums\InitialStudy;
use App\Models\CustomerOrder;
use App\Models\ImportBatch;
use App\Models\Jdv;
use App\Models\Pco;
use App\Models\User;
use App\Models\Zone;
use App\Support\CodeNormalizer;
use App\Support\IvoryCoastBounds;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Analyse d'un fichier de clients (premier temps de l'import) : aucune commande n'est créée ni modifiée.
 *
 * Chaque ligne reçoit un statut et ses anomalies :
 * - refusée : ID client ou nom absent, ID client en double dans le fichier ;
 * - à corriger : téléphone, GPS, date, sous-plaque, splitter ou PCO inexploitables ou incohérents (la valeur douteuse n'est jamais devinée) ;
 * - ok : le reste (une correction sûre, comme le zéro initial d'un téléphone, est signalée en information).
 * Le splitter du fichier (colonne « JDV/HUB » du client) est contrôlé contre le réseau ; réimport d'un ID client connu = mise à jour.
 */
final class CustomerImportAnalyzer
{
    public const MAX_ROWS = 5000;

    public const COLUMNS = [
        'id_client', 'ft', 'compte', 'nom', 'adresse', 'contact1', 'contact2', 'latitude', 'longitude', 'sous_plaque', 'splitter', 'pco',
        'sn_ont', 'ligne_fixe', 'date_enregistrement', 'date_envoi', 'etude_technique', 'distance', 'etat_ligne', 'commentaire',
    ];

    /** Colonnes lues en plus du modèle quand le fichier vient de l'opérateur (fichier de suivi des instances). */
    public const OPERATOR_COLUMNS = ['ville', 'commune', 'avancement', 'problemes', 'besoin_poteaux_beton', 'besoin_poteaux_metalliques'];

    public const REQUIRED = ['id_client', 'nom'];

    /** En-têtes du fichier de l'opérateur (normalisés) → colonnes du modèle. */
    public const ALIASES = [
        'numero_abonnement' => 'id_client',
        'nom_client' => 'nom',
        'localisation' => 'adresse',
        'latitude_client' => 'latitude',
        'longitude_client' => 'longitude',
        'zone_pco_sro_olt' => 'sous_plaque',
        'jdv_hub' => 'splitter',
        'distance_pco_client' => 'distance',
        'besoin_en_poteau_betons' => 'besoin_poteaux_beton',
        'besoin_en_poteau_metalliques' => 'besoin_poteaux_metalliques',
        'etat_de_ligne' => 'etat_ligne',
    ];

    /** En-têtes à texte libre, reconnus par leur début. */
    public const PREFIX_ALIASES = [
        'date_d_enregistrement' => 'date_enregistrement',
        'date_d_envoi' => 'date_envoi',
        'avancement' => 'avancement',
        'problemes' => 'problemes',
        'commentaires' => 'commentaire',
    ];

    /** @var Collection<string, Collection<int, Zone>> */
    private Collection $zones;

    /** @var Collection<string, Collection<int, Pco>> */
    private Collection $pcos;

    /** @var Collection<string, string> ID client déjà en base, par ID client */
    private Collection $existing;

    /** @var Collection<string, string> ID client de la commande qui porte déjà ce numéro de ligne FT */
    private Collection $existingFt;

    public static function parse(string $content): CsvTable
    {
        return CsvTable::fromContent($content, [...self::COLUMNS, ...self::OPERATOR_COLUMNS], self::REQUIRED, self::MAX_ROWS, self::ALIASES, self::PREFIX_ALIASES, ignoreUnknown: true);
    }

    public function analyse(CsvTable $table, User $by, string $fileName): ImportBatch
    {
        $this->preload();
        $seenRefs = [];
        $seenFt = [];
        $seenNameAddress = [];
        $rows = [];
        $counts = ['ok' => 0, 'to_fix' => 0, 'rejected' => 0, 'create' => 0, 'update' => 0];

        foreach ($table->rows as $row) {
            [$data, $anomalies] = $this->analyseRow($row['values'], $row['line'], $seenRefs, $seenFt, $seenNameAddress);

            $levels = array_column($anomalies, 'level');
            $status = in_array('error', $levels, true) ? 'rejected' : (in_array('warning', $levels, true) ? 'to_fix' : 'ok');
            $action = $status === 'rejected' ? 'none' : ($this->existing->has($data['customer_ref']) ? 'update' : 'create');

            $counts[$status]++;
            if ($action !== 'none') {
                $counts[$action]++;
            }
            $rows[] = ['line' => $row['line'], 'status' => $status, 'action' => $action, 'data' => $data, 'anomalies' => $anomalies];
        }

        return DB::transaction(function () use ($by, $fileName, $counts, $rows, $table) {
            $batch = ImportBatch::create([
                'type' => 'customers', 'file_name' => $fileName, 'created_by' => $by->id,
                'counts' => $counts + ['rows' => count($rows), 'ignored_columns' => $table->ignoredColumns],
            ]);
            foreach (array_chunk($rows, 200) as $chunk) {
                $batch->rows()->createMany($chunk);
            }

            return $batch;
        });
    }

    private function preload(): void
    {
        $this->zones = Zone::query()->with('plaque:id,name')->get()->groupBy('code_normalized');
        $this->pcos = Pco::query()->with('jdv:id,code,code_normalized')->get()->groupBy('code_normalized');
        $this->existing = CustomerOrder::query()->pluck('id', 'customer_ref');
        $this->existingFt = CustomerOrder::query()->whereNotNull('ft_line')->pluck('customer_ref', 'ft_line');
    }

    /**
     * @param  array<string, string>  $v
     * @param  array<string, int>  $seenRefs
     * @param  array<string, int>  $seenFt
     * @param  array<string, int>  $seenNameAddress
     * @return array{0: array<string, mixed>, 1: list<array{level: string, message: string}>}
     */
    private function analyseRow(array $v, int $line, array &$seenRefs, array &$seenFt, array &$seenNameAddress): array
    {
        $anomalies = [];
        $add = function (string $level, string $message) use (&$anomalies) {
            $anomalies[] = ['level' => $level, 'message' => $message];
        };
        $get = fn (string $key) => $v[$key] ?? '';

        $ref = $get('id_client');
        $name = $get('nom');
        if ($ref === '') {
            $add('error', "L'ID client est vide.");
        } elseif (mb_strlen($ref) > 40) {
            $add('error', "L'ID client dépasse 40 caractères.");
        } elseif (isset($seenRefs[$ref])) {
            $add('error', "ID client en double : déjà à la ligne {$seenRefs[$ref]} (non fusionné).");
        } else {
            $seenRefs[$ref] = $line;
        }
        if ($name === '') {
            $add('error', 'Le nom est vide.');
        }

        $data = [
            'customer_ref' => $ref, 'name' => $name, 'account_number' => $get('compte') ?: null, 'address_raw' => $get('adresse') ?: null,
            'city' => $get('ville') ?: null, 'commune' => $get('commune') ?: null,
        ];

        $ft = $get('ft');
        $data['ft_line'] = $ft ?: null;
        if ($ft !== '') {
            if (isset($seenFt[$ft])) {
                $add('warning', "Le numéro FT « {$ft} » figure déjà à la ligne {$seenFt[$ft]}.");
            } elseif ($this->existingFt->has($ft) && $this->existingFt[$ft] !== $ref) {
                $add('warning', "Le numéro FT « {$ft} » est déjà porté par l'ID client {$this->existingFt[$ft]}.");
            }
            $seenFt[$ft] ??= $line;
        }

        $nameAddress = mb_strtolower($name.'|'.$get('adresse'));
        if ($name !== '' && $get('adresse') !== '') {
            if (isset($seenNameAddress[$nameAddress])) {
                $add('warning', "Même nom et même adresse qu'à la ligne {$seenNameAddress[$nameAddress]} : doublon probable.");
            }
            $seenNameAddress[$nameAddress] ??= $line;
        }

        foreach ([1, 2] as $i) {
            $raw = $get("contact{$i}");
            $phone = PhoneNumber::normalize($raw);
            $data["phone_{$i}"] = $phone['normalized'];
            $data["phone_{$i}_raw"] = $raw ?: null;
            if ($raw !== '' && $phone['normalized'] === null) {
                $add('warning', "Contact {$i} : ".($phone['note'] ?? 'numéro illisible').' Numéro non enregistré.');
            } elseif ($phone['note'] !== null) {
                $add('info', "Contact {$i} : {$phone['note']}");
            }
        }
        if ($data['phone_1'] !== null && $data['phone_1'] === $data['phone_2']) {
            $add('info', 'Les deux contacts sont identiques.');
        }

        [$data['declared_latitude'], $data['declared_longitude']] = $this->coordinates($get('latitude'), $get('longitude'), $add);

        $unlinked = $this->network($data, $get('sous_plaque'), $get('splitter'), $get('pco'), $add);

        $data['ont_serial'] = $get('sn_ont') ?: null;
        $fixed = preg_replace('/^(\d+)[.,]0+$/', '$1', $get('ligne_fixe')) ?? '';
        $data['fixed_line'] = $fixed ?: null;
        if ($fixed !== '' && ! preg_match('/^\d{8,12}$/', $fixed)) {
            $add('warning', "La ligne fixe « {$fixed} » n'est pas un numéro de 8 à 12 chiffres.");
        }

        foreach (['date_enregistrement' => 'registered_at', 'date_envoi' => 'sent_to_contractor_at'] as $column => $field) {
            $data[$field] = $this->date($get($column), $column, $add);
        }

        $study = $get('etude_technique');
        $data['initial_study_raw'] = $study ?: null;
        $data['initial_study'] = InitialStudy::fromOperatorLabel($study)?->value;
        if ($study !== '' && $data['initial_study'] === null) {
            $add('warning', "Étude technique « {$study} » inconnue (SANS PCO ou AVEC PCO) : libellé conservé, état à associer.");
        }

        $distance = $get('distance');
        $data['distance_m'] = null;
        if ($distance !== '') {
            if (preg_match('/^(\d{1,6})([.,]0+)?$/', $distance, $m)) {
                $data['distance_m'] = (int) $m[1];
            } else {
                $add('warning', "Distance « {$distance} » illisible (nombre entier de mètres attendu).");
            }
        }

        $data['operator_line_status'] = $get('etat_ligne') ?: null;
        $data['observation'] = $this->observation($v, $unlinked);

        // Un client déjà raccordé (avancement « raccordé ») est importé comme installé, pas comme à raccorder.
        if (Str::ascii(mb_strtolower($get('avancement'))) === 'raccorde') {
            $data['initial_status'] = 'installed';
            $add('info', 'Avancement « raccordé » : le client sera importé comme déjà installé.');
        }

        return [$data, $anomalies];
    }

    /** @return array{0: ?string, 1: ?string} */
    private function coordinates(string $lat, string $lon, callable $add): array
    {
        if ($lat === '' && $lon === '') {
            return [null, null];
        }
        // Les deux valeurs dans une seule cellule (« 5.845397;-5.475435 ») : lues, et signalées.
        if ($lon === '' && preg_match('/^(-?\d+(?:[.,]\d+)?)\s*[;\s]\s*(-?\d+(?:[.,]\d+)?)$/', $lat, $m)) {
            [$lat, $lon] = [$m[1], $m[2]];
            $add('info', 'Latitude et longitude lues dans une seule cellule.');
        }
        $latitude = is_numeric($x = str_replace(',', '.', $lat)) ? (float) $x : null;
        $longitude = is_numeric($y = str_replace(',', '.', $lon)) ? (float) $y : null;

        if ($latitude === null || $longitude === null) {
            $add('warning', 'Coordonnées GPS incomplètes ou illisibles : non enregistrées.');

            return [null, null];
        }
        if (! IvoryCoastBounds::contains($latitude, $longitude)) {
            $add('warning', IvoryCoastBounds::looksInverted($latitude, $longitude)
                ? 'Latitude et longitude semblent inversées : non enregistrées (à corriger dans le fichier).'
                : "Coordonnées hors de la Côte d'Ivoire : non enregistrées.");

            return [null, null];
        }

        return [number_format($latitude, 7, '.', ''), number_format($longitude, 7, '.', '')];
    }

    /**
     * Rattache la ligne au réseau (sous-plaque, PCO) et contrôle le splitter du fichier ; rien n'est créé.
     *
     * @param  array<string, mixed>  $data
     * @return list<string> codes du fichier qui n'ont pu être rattachés au réseau (gardés dans l'observation)
     */
    private function network(array &$data, string $zoneCode, string $splitter, string $pcoCode, callable $add): array
    {
        $unlinked = [];
        $data['zone_id'] = null;
        $data['pco_id'] = null;

        $zone = null;
        if ($zoneCode !== '') {
            $found = $this->zones->get(CodeNormalizer::zone($zoneCode), collect());
            if ($found->count() === 1) {
                $zone = $found->first();
            } else {
                $add('warning', $found->isEmpty()
                    ? "Sous-plaque « {$zoneCode} » inconnue du réseau (jamais créée par l'import)."
                    : "Sous-plaque « {$zoneCode} » présente dans plusieurs plaques : précisez-la.");
            }
        }

        $pco = null;
        if ($pcoCode !== '') {
            $found = $this->pcos->get(CodeNormalizer::pco($pcoCode), collect());
            $inZone = $zone === null ? $found : $found->where('zone_id', $zone->id);

            if ($inZone->count() === 1) {
                $pco = $inZone->first();
            } elseif ($found->isEmpty()) {
                $add('warning', "PCO « {$pcoCode} » inconnu du réseau (jamais créé par l'import).");
            } elseif ($inZone->isEmpty()) {
                $add('warning', "Le PCO « {$pcoCode} » n'appartient pas à la sous-plaque « {$zoneCode} ».");
            } else {
                $add('warning', "PCO « {$pcoCode} » ambigu : précisez la sous-plaque.");
            }
        }

        $data['pco_id'] = $pco?->id;
        $data['zone_id'] = $zone?->id ?? $pco?->zone_id;
        if ($zoneCode !== '' && $zone === null) {
            $unlinked[] = "Sous-plaque (fichier) : {$zoneCode}";
        }
        if ($pcoCode !== '' && $pco === null) {
            $unlinked[] = "PCO (fichier) : {$pcoCode}";
        }

        if ($splitter !== '' && $data['zone_id'] !== null) {
            $wanted = CodeNormalizer::zone($splitter); // « HUB 01 » et « HUB001 » désignent le même splitter
            $actual = $pco?->jdv?->code_normalized;
            if ($pco !== null && $actual !== null && $actual !== $wanted) {
                $add('warning', "Le splitter du fichier (« {$splitter} ») diffère de celui du PCO (« {$pco->jdv->code} »).");
            } elseif ($pco === null && ! Jdv::query()->where('zone_id', $data['zone_id'])->where('code_normalized', $wanted)->exists()) {
                $add('warning', "Splitter « {$splitter} » inconnu dans cette sous-plaque.");
            }
        }

        return $unlinked;
    }

    /**
     * Observation de la commande : le commentaire du fichier, puis les colonnes de suivi de l'opérateur qui n'ont pas de champ
     * propre (avancement, problèmes, besoin en poteaux) et les codes réseau qu'on n'a pas pu rattacher. Rien n'est perdu.
     *
     * @param  array<string, string>  $v
     * @param  list<string>  $unlinked
     */
    private function observation(array $v, array $unlinked): ?string
    {
        $parts = array_filter([
            $v['commentaire'] ?? '',
            ($v['avancement'] ?? '') !== '' ? "Avancement : {$v['avancement']}" : '',
            ($v['problemes'] ?? '') !== '' ? "Problèmes : {$v['problemes']}" : '',
            ($v['besoin_poteaux_beton'] ?? '') !== '' ? "Besoin poteaux béton : {$v['besoin_poteaux_beton']}" : '',
            ($v['besoin_poteaux_metalliques'] ?? '') !== '' ? "Besoin poteaux métalliques : {$v['besoin_poteaux_metalliques']}" : '',
            ...$unlinked,
        ], fn ($part) => $part !== '');

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /** Date ISO, jj/mm/aaaa ou numéro de série Excel ; une date illisible est signalée, jamais devinée. */
    private function date(string $raw, string $column, callable $add): ?string
    {
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^\d{5}(\.\d+)?$/', $raw)) {
            return CarbonImmutable::create(1899, 12, 30)->addDays((int) $raw)->toDateString();
        }
        if (preg_match('/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})$/', $raw, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return $raw;
        }

        $add('warning', "Date « {$raw} » illisible ({$column}) : non enregistrée.");

        return null;
    }
}
