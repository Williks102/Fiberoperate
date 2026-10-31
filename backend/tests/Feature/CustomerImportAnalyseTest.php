<?php

namespace Tests\Feature;

use App\Domain\Import\CustomerImportAnalyzer;
use App\Domain\Import\ImportFileException;
use App\Enums\Role;
use App\Models\CustomerOrder;
use App\Models\ImportBatch;
use App\Models\Jdv;
use App\Models\Pco;
use App\Models\Plaque;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerImportAnalyseTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = "id_client;ft;compte;nom;adresse;contact1;contact2;latitude;longitude;sous_plaque;splitter;pco;sn_ont;ligne_fixe;date_enregistrement;date_envoi;etude_technique;distance;etat_ligne;commentaire\n";

    private User $coordinator;

    private Zone $zone;

    private Pco $pco;

    protected function setUp(): void
    {
        parent::setUp();
        $this->coordinator = User::create(['name' => 'c', 'email' => 'c@x.test', 'password' => 'secret123', 'role' => Role::Coordinator]);
        $this->zone = Zone::create(['plaque_id' => Plaque::create(['name' => 'ABOBO'])->id, 'code' => 'ABB01']);
        $jdv = Jdv::create(['zone_id' => $this->zone->id, 'code' => 'SPL001']);
        $this->pco = Pco::create(['jdv_id' => $jdv->id, 'code' => 'ABB01_PCO001']);
    }

    /** Une ligne du fichier : les colonnes non précisées restent vides. */
    private function line(array $values): string
    {
        $columns = CustomerImportAnalyzer::COLUMNS;

        return implode(';', array_map(fn (string $column) => $values[$column] ?? '', $columns));
    }

    private function analyse(string ...$lines): ImportBatch
    {
        return (new CustomerImportAnalyzer)->analyse(CustomerImportAnalyzer::parse(self::HEADER.implode("\n", $lines)."\n"), $this->coordinator, 'clients.csv');
    }

    /** @return array{status: string, action: string, data: array, anomalies: array} */
    private function row(ImportBatch $batch, int $index = 0): array
    {
        $row = $batch->rows()->get()[$index];

        return ['status' => $row->status, 'action' => $row->action, 'data' => $row->data, 'anomalies' => $row->anomalies];
    }

    private function messages(array $row): string
    {
        return implode(' | ', array_column($row['anomalies'], 'message'));
    }

    public function test_a_clean_row_is_ok_and_nothing_is_created(): void
    {
        $batch = $this->analyse($this->line([
            'id_client' => 'RAB0000000000001', 'ft' => 'FT1', 'nom' => 'Client Un', 'adresse' => 'Rue 1', 'contact1' => '0707123456', 'latitude' => '5.36', 'longitude' => '-4.0083',
            'sous_plaque' => 'ABB01', 'splitter' => 'SPL001', 'pco' => 'ABB01_PCO001', 'ligne_fixe' => '2121234567', 'etude_technique' => 'SANS PCO ', 'distance' => '150',
        ]));

        $row = $this->row($batch);
        $this->assertSame(['ok', 'create', []], [$row['status'], $row['action'], $row['anomalies']]);
        $this->assertSame($this->pco->id, $row['data']['pco_id']);
        $this->assertSame($this->zone->id, $row['data']['zone_id']);
        $this->assertSame('+2250707123456', $row['data']['phone_1']);
        $this->assertSame('done', $row['data']['initial_study']);
        $this->assertSame('SANS PCO', $row['data']['initial_study_raw'], 'les espaces parasites du fichier sont retirés, le libellé reste celui du fichier');
        $this->assertSame(150, $row['data']['distance_m']);
        $this->assertSame(['ok' => 1, 'to_fix' => 0, 'rejected' => 0, 'create' => 1, 'update' => 0, 'rows' => 1, 'ignored_columns' => []], $batch->counts);
        $this->assertSame(0, CustomerOrder::count());
    }

    public function test_missing_key_or_name_and_duplicates_in_the_file_are_rejected(): void
    {
        $batch = $this->analyse(
            $this->line(['id_client' => 'A1', 'nom' => 'Un']),
            $this->line(['nom' => 'Sans ID']),
            $this->line(['id_client' => 'A3']),
            $this->line(['id_client' => 'A1', 'nom' => 'Doublon']),
        );

        $this->assertSame(['ok', 'rejected', 'rejected', 'rejected'], $batch->rows()->pluck('status')->all());
        $this->assertStringContainsString("L'ID client est vide", $this->messages($this->row($batch, 1)));
        $this->assertStringContainsString('Le nom est vide', $this->messages($this->row($batch, 2)));
        $this->assertStringContainsString('ID client en double : déjà à la ligne 2', $this->messages($this->row($batch, 3)));
        $this->assertSame('none', $this->row($batch, 1)['action']);
    }

    public function test_phones_are_normalized_and_every_correction_or_problem_is_reported(): void
    {
        $batch = $this->analyse(
            $this->line(['id_client' => 'P1', 'nom' => 'Un', 'contact1' => '707123456', 'contact2' => '*0101234567']),
            $this->line(['id_client' => 'P2', 'nom' => 'Deux', 'contact1' => '07123']),
        );

        $first = $this->row($batch, 0);
        $this->assertSame('ok', $first['status']);
        $this->assertSame(['+2250707123456', '+2250101234567'], [$first['data']['phone_1'], $first['data']['phone_2']]);
        $this->assertSame('707123456', $first['data']['phone_1_raw']);
        $this->assertStringContainsString('Zéro initial ajouté', $this->messages($first));

        $second = $this->row($batch, 1);
        $this->assertSame('to_fix', $second['status']);
        $this->assertNull($second['data']['phone_1']);
        $this->assertSame('07123', $second['data']['phone_1_raw']);
        $this->assertStringContainsString('Longueur anormale', $this->messages($second));
    }

    public function test_suspect_gps_is_never_corrected_silently(): void
    {
        $batch = $this->analyse(
            $this->line(['id_client' => 'G1', 'nom' => 'Un', 'latitude' => '-4,0083', 'longitude' => '5,36']),
            $this->line(['id_client' => 'G2', 'nom' => 'Deux', 'latitude' => '48.85', 'longitude' => '2.35']),
            $this->line(['id_client' => 'G3', 'nom' => 'Trois', 'latitude' => '5,36', 'longitude' => '-4,0083']),
            $this->line(['id_client' => 'G4', 'nom' => 'Quatre', 'latitude' => '5.36']),
        );

        $this->assertSame(['to_fix', 'to_fix', 'ok', 'to_fix'], $batch->rows()->pluck('status')->all());
        $this->assertStringContainsString('inversées', $this->messages($this->row($batch, 0)));
        $this->assertNull($this->row($batch, 0)['data']['declared_latitude']);
        $this->assertSame('5.3600000', $this->row($batch, 2)['data']['declared_latitude']);
    }

    public function test_the_network_is_matched_never_created_and_the_splitter_is_checked(): void
    {
        $other = Zone::create(['plaque_id' => Plaque::create(['name' => 'FAYA'])->id, 'code' => 'FAY03']);
        Pco::create(['jdv_id' => Jdv::create(['zone_id' => $other->id, 'code' => 'SPL001'])->id, 'code' => 'FAY03_PCO001']);

        $batch = $this->analyse(
            $this->line(['id_client' => 'N1', 'nom' => 'Un', 'pco' => 'ABB01_PCO001']),
            $this->line(['id_client' => 'N2', 'nom' => 'Deux', 'sous_plaque' => 'ZZZ99', 'pco' => 'ZZZ99_PCO001']),
            $this->line(['id_client' => 'N3', 'nom' => 'Trois', 'sous_plaque' => 'ABB01', 'pco' => 'FAY03_PCO001']),
            $this->line(['id_client' => 'N4', 'nom' => 'Quatre', 'sous_plaque' => 'ABB01', 'splitter' => 'SPL009', 'pco' => 'ABB01_PCO001']),
            $this->line(['id_client' => 'N5', 'nom' => 'Cinq', 'sous_plaque' => 'ABB-01', 'splitter' => 'spl 001', 'pco' => 'ABB01_PCO-001']),
        );

        $this->assertSame(['ok', 'to_fix', 'to_fix', 'to_fix', 'ok'], $batch->rows()->pluck('status')->all());
        $this->assertSame($this->zone->id, $this->row($batch, 0)['data']['zone_id'], 'la sous-plaque se déduit du PCO');
        $this->assertStringContainsString('inconnue du réseau', $this->messages($this->row($batch, 1)));
        $this->assertNull($this->row($batch, 1)['data']['pco_id']);
        $this->assertStringContainsString("n'appartient pas à la sous-plaque", $this->messages($this->row($batch, 2)));
        $this->assertStringContainsString('diffère de celui du PCO', $this->messages($this->row($batch, 3)));
        $this->assertSame($this->pco->id, $this->row($batch, 4)['data']['pco_id'], 'variantes de codes rapprochées');
        $this->assertSame(2, Zone::count());
        $this->assertSame(2, Pco::count());
    }

    public function test_a_known_id_is_an_update_and_ft_numbers_are_checked(): void
    {
        CustomerOrder::create(['customer_ref' => 'E1', 'name' => 'Existant', 'ft_line' => 'FT-EXISTANT']);

        $batch = $this->analyse(
            $this->line(['id_client' => 'E1', 'nom' => 'Existant mis à jour', 'ft' => 'FT-EXISTANT']),
            $this->line(['id_client' => 'E2', 'nom' => 'Autre', 'ft' => 'FT-EXISTANT']),
            $this->line(['id_client' => 'E3', 'nom' => 'Trois', 'ft' => 'FT-NEUF']),
            $this->line(['id_client' => 'E4', 'nom' => 'Quatre', 'ft' => 'FT-NEUF']),
        );

        $this->assertSame(['ok', 'to_fix', 'ok', 'to_fix'], $batch->rows()->pluck('status')->all());
        $this->assertSame('update', $this->row($batch, 0)['action']);
        $this->assertStringContainsString('figure déjà à la ligne 2', $this->messages($this->row($batch, 1)));
        $this->assertStringContainsString('figure déjà à la ligne 4', $this->messages($this->row($batch, 3)));

        $alone = $this->analyse($this->line(['id_client' => 'E9', 'nom' => 'Neuf', 'ft' => 'FT-EXISTANT']));
        $this->assertStringContainsString('déjà porté par l\'ID client E1', $this->messages($this->row($alone)));
        $this->assertSame(['create' => 3, 'update' => 1], ['create' => $batch->counts['create'], 'update' => $batch->counts['update']]);
    }

    public function test_dates_study_labels_and_numbers_are_read_or_reported(): void
    {
        $batch = $this->analyse(
            $this->line(['id_client' => 'D1', 'nom' => 'Un', 'date_enregistrement' => '12/10/2026', 'date_envoi' => '45000', 'etude_technique' => ' avec pco', 'ligne_fixe' => '2121234567.0']),
            $this->line(['id_client' => 'D2', 'nom' => 'Deux', 'date_enregistrement' => '2026-02-30', 'etude_technique' => 'Peut-être', 'distance' => 'loin', 'ligne_fixe' => 'abc']),
        );

        $first = $this->row($batch, 0);
        $this->assertSame('ok', $first['status']);
        $this->assertSame(['2026-10-12', '2023-03-15', 'to_do', '2121234567'], [$first['data']['registered_at'], $first['data']['sent_to_contractor_at'], $first['data']['initial_study'], $first['data']['fixed_line']]);

        $second = $this->row($batch, 1);
        $this->assertSame('to_fix', $second['status']);
        foreach (['Date « 2026-02-30 » illisible', 'Étude technique « Peut-être » inconnue', 'Distance « loin » illisible', 'La ligne fixe « abc »'] as $expected) {
            $this->assertStringContainsString($expected, $this->messages($second));
        }
        $this->assertSame('Peut-être', $second['data']['initial_study_raw']);
        $this->assertNull($second['data']['initial_study']);
    }

    public function test_the_downloadable_template_is_analysed_without_any_rejection(): void
    {
        $content = (string) file_get_contents(base_path('../frontend/public/modeles-import/clients.csv'));

        $batch = (new CustomerImportAnalyzer)->analyse(CustomerImportAnalyzer::parse($content), $this->coordinator, 'clients.csv');

        $this->assertSame(0, $batch->counts['rejected']);
        $this->assertSame(2, $batch->counts['rows']);
    }

    public function test_file_level_problems_are_refused(): void
    {
        foreach (['' => 'vide', "id_client;nom\n" => 'aucune ligne', "nom;adresse\nA;B\n" => 'manquante'] as $content => $expected) {
            try {
                CustomerImportAnalyzer::parse((string) $content);
                $this->fail("Fichier accepté à tort : {$expected}");
            } catch (ImportFileException $e) {
                $this->assertStringContainsString($expected, $e->getMessage());
            }
        }
    }

    public function test_an_unknown_column_is_ignored_and_reported_not_refused(): void
    {
        $table = CustomerImportAnalyzer::parse("id_client;nom;couleur\nA;B;rouge\n");

        $this->assertSame(['couleur'], $table->ignoredColumns);
    }
}
