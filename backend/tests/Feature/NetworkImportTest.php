<?php

namespace Tests\Feature;

use App\Domain\Import\ImportFileException;
use App\Domain\Import\NetworkImport;
use App\Enums\Role;
use App\Models\Jdv;
use App\Models\Olt;
use App\Models\Pco;
use App\Models\Plaque;
use App\Models\Project;
use App\Models\Sro;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NetworkImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = "olt;plaque;sous_plaque;fabricant;sro;splitter;pco;latitude;longitude;slot;port;brin;statut;commentaire\n";

    private function csv(string ...$lines): string
    {
        return self::HEADER.implode("\n", $lines)."\n";
    }

    private function importCsv(string $content, bool $commit = true): array
    {
        $plaque = Plaque::firstOrCreate(['name' => 'FAYA']);

        return (new NetworkImport($plaque))->run(NetworkImport::parse($content), $commit);
    }

    public function test_creates_the_whole_chain_and_keeps_the_raw_status(): void
    {
        $report = $this->importCsv($this->csv(
            'OLT1;FAYA;FAY03;nokia;ABB456;SPL001;FAY03_PCO001;5.36;-4.0083;1;2;3;En attente BE;note',
            'OLT1;FAYA;FAY03;nokia;;SPL001;FAY03_PCO002;;;;;;;',
            'OLT1;FAYA;FAY03;nokia;;SPL002;FAY03_PCO003;;;;;;;',
        ));

        $this->assertSame(['olts' => 1, 'zones' => 1, 'sros' => 1, 'jdvs' => 2, 'pcos' => 3], $report['created']);
        $this->assertSame([], $report['rejected']);

        $pco = Pco::where('code', 'FAY03_PCO001')->firstOrFail();
        $this->assertNull($pco->status);
        $this->assertSame('En attente BE', $pco->status_raw);
        $this->assertSame('5.3600000', $pco->latitude);
        $this->assertSame('3', $pco->fiber_strand);
        $this->assertSame('SRO', Zone::firstOrFail()->accessPointLabel());
        $this->assertSame('ABB456', Sro::firstOrFail()->code);
        $this->assertSame(Olt::firstOrFail()->id, Plaque::firstOrFail()->olt_id);
    }

    public function test_preview_writes_nothing_and_reports_the_same_numbers_as_the_import(): void
    {
        $content = $this->csv('OLT1;FAYA;FAY03;nokia;;SPL001;FAY03_PCO001;;;;;;;', 'OLT1;FAYA;FAY03;nokia;;SPL001;FAY03_PCO002;;;;;;;');

        $preview = $this->importCsv($content, commit: false);
        $this->assertSame(0, Pco::count());
        $this->assertSame(0, Zone::count());

        $real = $this->importCsv($content);
        $this->assertSame($preview['created'], $real['created']);
        $this->assertSame(2, Pco::count());
    }

    public function test_code_variants_are_matched_and_duplicates_are_reported_not_merged(): void
    {
        $report = $this->importCsv($this->csv(
            'OLT1;FAYA;FAY03;;;SPL001;FAY03_PCO001;;;;;;;',
            'OLT1;FAYA;FAY003;;;SPL001;FAY03_PCO-001;;;;;;;',
            'OLT1;FAYA;FAY03;;;SPL002;FAY03_PCO002;;;;;;;',
        ));

        $this->assertSame(2, $report['created']['pcos']);
        $this->assertSame(1, $report['created']['zones']);
        $this->assertCount(1, $report['rejected']);
        $this->assertSame(3, $report['rejected'][0]['line']);
        $this->assertStringContainsString('ligne 2', $report['rejected'][0]['message']);
    }

    public function test_existing_elements_are_reused_and_existing_pcos_left_untouched(): void
    {
        $this->importCsv($this->csv('OLT1;FAYA;FAY03;nokia;;SPL001;FAY03_PCO001;;;;;;Posé;'));
        $before = Pco::firstOrFail();

        $report = $this->importCsv($this->csv(
            'OLT1;FAYA;FAY03;nokia;;SPL001;FAY03_PCO001;;;;;;Autre statut;',
            'OLT1;FAYA;FAY03;nokia;;SPL001;FAY03_PCO002;;;;;;;',
        ));

        $this->assertSame(1, $report['already_present']);
        $this->assertSame(['olts' => 0, 'zones' => 0, 'sros' => 0, 'jdvs' => 0, 'pcos' => 1], $report['created']);
        $this->assertSame('Posé', $before->fresh()->status_raw);
        $this->assertSame(1, Jdv::count());
    }

    public function test_invalid_rows_are_rejected_without_creating_anything_for_them(): void
    {
        $report = $this->importCsv($this->csv(
            'OLT1;FAYA;FAY03;;;;FAY03_PCO001;;;;;;;',
            'OLT1;;FAY03;;;SPL001;FAY03_PCO002;;;;;;;',
            'OLT1;ARCADE;ACA003;;;SPL001;ACA003_PCO001;;;;;;;',
        ));

        $this->assertCount(2, $report['rejected']);
        $this->assertStringContainsString('splitter', $report['rejected'][0]['message']);
        $this->assertStringContainsString('plaque', $report['rejected'][1]['message']);
        $this->assertSame(1, Pco::count());
        $this->assertSame(1, Plaque::count());
    }

    public function test_suspect_gps_is_never_corrected_silently(): void
    {
        $report = $this->importCsv($this->csv(
            'OLT1;FAYA;FAY03;;;SPL001;FAY03_PCO001;-4.0083;5.36;;;;;',
            'OLT1;FAYA;FAY03;;;SPL001;FAY03_PCO002;48.85;2.35;;;;;',
            'OLT1;FAYA;FAY03;;;SPL001;FAY03_PCO003;5,36;-4,0083;;;;;',
        ));

        $this->assertSame(3, $report['created']['pcos']);
        $this->assertCount(2, $report['warnings']);
        $this->assertStringContainsString('inversées', $report['warnings'][0]['message']);
        $this->assertNull(Pco::where('code', 'FAY03_PCO001')->value('latitude'));
        $this->assertNull(Pco::where('code', 'FAY03_PCO002')->value('latitude'));
        $this->assertSame('5.3600000', Pco::where('code', 'FAY03_PCO003')->value('latitude'));
    }

    public function test_a_mismatching_pco_prefix_and_unknown_vendor_are_warnings(): void
    {
        $report = $this->importCsv($this->csv('OLT1;FAYA;FAY03;samsung;;SPL001;ACA03_PCO001;;;;;;;'));

        $this->assertSame(1, $report['created']['pcos']);
        $this->assertCount(2, $report['warnings']);
        $this->assertNull(Zone::firstOrFail()->vendor);
    }

    public function test_windows_1252_file_and_comma_separator_are_read(): void
    {
        $content = mb_convert_encoding("plaque,sous_plaque,splitter,pco,commentaire\nFAYA,FAY03,SPL001,FAY03_PCO001,Pose terminée\n", 'Windows-1252', 'UTF-8');

        $this->importCsv($content);

        $this->assertSame('Pose terminée', Pco::firstOrFail()->comment);
    }

    public function test_file_level_problems_are_refused(): void
    {
        foreach ([
            '' => 'vide',
            "plaque;pco\nFAYA;X\n" => 'manquante',
            "plaque;sous_plaque;splitter;pco;couleur\nFAYA;FAY03;SPL001;FAY03_PCO001;rouge\n" => 'inconnue',
            self::HEADER => 'aucune ligne',
        ] as $content => $expected) {
            try {
                NetworkImport::parse((string) $content);
                $this->fail("Fichier accepté à tort : {$expected}");
            } catch (ImportFileException $e) {
                $this->assertStringContainsString($expected, $e->getMessage());
            }
        }
    }

    public function test_the_downloadable_template_is_valid(): void
    {
        $report = $this->importCsv((string) file_get_contents(base_path('../frontend/public/modeles-import/reseau.csv')));

        $this->assertSame([], $report['rejected']);
        $this->assertSame([], $report['warnings']);
        $this->assertSame(3, $report['created']['pcos']);
    }

    public function test_only_the_administrator_and_the_be_manager_import_and_the_api_reports_file_errors(): void
    {
        $file = fn (string $content) => UploadedFile::fake()->createWithContent('reseau.csv', $content);
        $admin = User::create(['name' => 'a', 'email' => 'a@x.test', 'password' => 'secret123', 'role' => Role::Admin]);
        $project = Project::startFor(Plaque::create(['name' => 'FAYA']), $admin);
        $url = "/api/v1/projects/{$project->id}/imports/network";
        $valid = $this->csv('OLT1;FAYA;FAY03;nokia;;SPL001;FAY03_PCO001;;;;;;;');

        foreach ([Role::Dg, Role::Dt, Role::Supervisor, Role::Coordinator, Role::Technician] as $i => $role) {
            Sanctum::actingAs(User::create(['name' => 'u', 'email' => "u{$i}@x.test", 'password' => 'secret123', 'role' => $role]));
            $this->postJson($url, ['file' => $file($valid)])->assertForbidden();
        }
        $this->assertSame(0, Pco::count());

        Sanctum::actingAs($admin);

        $this->postJson($url.'/preview', ['file' => $file($valid)])
            ->assertOk()->assertJsonPath('data.committed', false)->assertJsonPath('data.created.pcos', 1);
        $this->assertSame(0, Pco::count());

        $this->postJson($url, ['file' => $file($valid)])
            ->assertOk()->assertJsonPath('data.committed', true);
        $this->assertSame(1, Pco::count());

        $this->postJson($url, ['file' => $file("plaque;couleur\nA;B\n")])
            ->assertStatus(422)->assertJsonPath('code', 'IMPORT_FILE_INVALID');
        $this->postJson($url, [])->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_one_plaque_per_file_the_one_of_the_project(): void
    {
        $report = $this->importCsv($this->csv(
            'OLT1;FAYA;FAY03;;;SPL001;FAY03_PCO001;;;;;;;',
            'OLT1;;FAY03;;;SPL001;FAY03_PCO002;;;;;;;',
            'OLT1;ARCADE;ACA003;;;SPL001;ACA003_PCO001;;;;;;;',
        ));

        $this->assertSame(2, $report['created']['pcos']);
        $this->assertCount(1, $report['rejected']);
        $this->assertSame(4, $report['rejected'][0]['line']);
        $this->assertStringContainsString('une plaque', $report['rejected'][0]['message']);
        $this->assertSame(['FAYA'], Plaque::pluck('name')->all());
    }

    public function test_the_sro_of_a_zone_is_created_once_and_never_overwritten(): void
    {
        $first = $this->importCsv($this->csv(
            'OLT1;FAYA;FAY03;;ABB456;SPL001;FAY03_PCO001;;;;;;;',
            'OLT1;FAYA;FAY03;;ABB999;SPL001;FAY03_PCO002;;;;;;;',
        ));
        $this->assertSame(1, $first['created']['sros']);
        $this->assertSame('ABB456', Sro::firstOrFail()->code);

        Zone::create(['plaque_id' => Plaque::firstOrFail()->id, 'code' => 'FAY04']);
        $second = $this->importCsv($this->csv('OLT1;FAYA;FAY04;;abb-456;SPL001;FAY04_PCO001;;;;;;;'));
        $this->assertSame(0, $second['created']['sros']);
        $this->assertStringContainsString('existe déjà', $second['warnings'][0]['message']);
        $this->assertSame(1, Sro::count());
    }
}
