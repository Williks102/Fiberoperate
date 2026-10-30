<?php

namespace Tests\Feature;

use App\Domain\Import\ImportFileException;
use App\Domain\Import\PriceCatalogImport;
use App\Enums\MissionType;
use App\Enums\Role;
use App\Models\PriceItem;
use App\Models\TechnicianSkill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PriceCatalogImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = "ref;type;rubrique;designation;unite;prix_unitaire\n";

    private function csv(string ...$lines): string
    {
        return self::HEADER.implode("\n", $lines)."\n";
    }

    private function importCsv(string $content, bool $commit = true): array
    {
        return (new PriceCatalogImport)->run(PriceCatalogImport::parse($content), $commit);
    }

    public function test_creates_articles_with_their_kind_unit_and_integer_price(): void
    {
        $report = $this->importCsv($this->csv(
            'MGC01;fourniture;MATERIELS DE GENIE CIVIL;Borne de repérage;u;5000',
            'POT02;Prestation;POTEAUX;Plantation poteau béton;u;35000',
            ';fourniture;DIVERS;Article sans référence ni prix;ml;',
        ));

        $this->assertSame(3, $report['created']);
        $this->assertSame([], $report['rejected']);
        $this->assertSame(['supply', 'service', 'supply'], PriceItem::orderBy('sort_order')->pluck('kind')->all());
        $this->assertSame(35000, PriceItem::where('ref', 'POT02')->value('unit_price'));
        $this->assertNull(PriceItem::where('designation', 'like', 'Article sans%')->value('unit_price'));
    }

    public function test_the_same_reference_under_two_sections_is_two_articles(): void
    {
        $report = $this->importCsv($this->csv(
            'RC01;prestation;Réfection de chaussée;Chaussée bitumée;m²;20000',
            'RC01;prestation;Construction de chambre;L1T;u;7900',
        ));

        $this->assertSame(2, $report['created']);
        $this->assertSame(2, PriceItem::where('ref', 'RC01')->count());
    }

    public function test_a_reimport_updates_prices_leaves_unchanged_lines_and_never_deletes(): void
    {
        $this->importCsv($this->csv('MGC01;fourniture;GC;Borne;u;5000', 'MGC02;fourniture;GC;Grillage;ml;70'));

        $report = $this->importCsv($this->csv('MGC01;fourniture;GC;Borne;u;5500', 'MGC03;fourniture;GC;Filin;ml;70'));

        $this->assertSame(['created' => 1, 'updated' => 1, 'unchanged' => 0], ['created' => $report['created'], 'updated' => $report['updated'], 'unchanged' => $report['unchanged']]);
        $this->assertSame(5500, PriceItem::where('ref', 'MGC01')->value('unit_price'));
        $this->assertSame(70, PriceItem::where('ref', 'MGC02')->value('unit_price'), 'un article absent du fichier reste intact');

        $again = $this->importCsv($this->csv('MGC01;fourniture;GC;Borne;u;5500'));
        $this->assertSame(1, $again['unchanged']);
    }

    public function test_preview_writes_nothing_and_reports_the_same_numbers(): void
    {
        $content = $this->csv('MGC01;fourniture;GC;Borne;u;5000', 'MGC02;fourniture;GC;Grillage;ml;70');

        $preview = $this->importCsv($content, commit: false);
        $this->assertSame(0, PriceItem::count());

        $real = $this->importCsv($content);
        $this->assertSame($preview['created'], $real['created']);
        $this->assertSame(2, PriceItem::count());
    }

    public function test_invalid_lines_are_rejected_and_listed(): void
    {
        $report = $this->importCsv($this->csv(
            'A1;fourniture;GC;Article;u;5 000',
            'A2;autre;GC;Article;u;100',
            'A3;fourniture;;Article;u;100',
            'A4;fourniture;GC;Article;u;12,5',
            'A5;fourniture;GC;Bon article;u;100',
            'A5;fourniture;GC;bon  ARTICLE;u;100',
        ));

        $this->assertSame(1, $report['created']);
        $this->assertSame([2, 3, 4, 5, 7], array_column($report['rejected'], 'line'));
        $this->assertStringContainsString('en double', $report['rejected'][4]['message']);
    }

    public function test_file_level_problems_are_refused(): void
    {
        foreach ([
            '' => 'vide',
            "ref;type\nA;fourniture\n" => 'manquante',
            "ref;type;rubrique;designation;couleur\nA;fourniture;GC;X;rouge\n" => 'inconnue',
        ] as $content => $expected) {
            try {
                PriceCatalogImport::parse((string) $content);
                $this->fail("Fichier accepté à tort : {$expected}");
            } catch (ImportFileException $e) {
                $this->assertStringContainsString($expected, $e->getMessage());
            }
        }
    }

    public function test_the_downloadable_template_is_valid(): void
    {
        $report = $this->importCsv((string) file_get_contents(base_path('../frontend/public/modeles-import/catalogue-prix.csv')));

        $this->assertSame([], $report['rejected']);
        $this->assertGreaterThanOrEqual(3, $report['created']);
    }

    public function test_only_the_administrator_imports_and_the_api_reports_file_errors(): void
    {
        $file = fn (string $content) => UploadedFile::fake()->createWithContent('catalogue.csv', $content);
        $valid = $this->csv('MGC01;fourniture;GC;Borne;u;5000');

        foreach ([Role::Dg, Role::Dt, Role::BeManager, Role::Supervisor, Role::Coordinator, Role::Technician] as $i => $role) {
            Sanctum::actingAs(User::create(['name' => 'u', 'email' => "u{$i}@x.test", 'password' => 'secret123', 'role' => $role]));
            $this->postJson('/api/v1/price-items/import', ['file' => $file($valid)])->assertForbidden();
        }
        $this->assertSame(0, PriceItem::count());

        Sanctum::actingAs(User::create(['name' => 'a', 'email' => 'a@x.test', 'password' => 'secret123', 'role' => Role::Admin]));
        $this->postJson('/api/v1/price-items/import/preview', ['file' => $file($valid)])->assertOk()->assertJsonPath('data.committed', false)->assertJsonPath('data.created', 1);
        $this->assertSame(0, PriceItem::count());
        $this->postJson('/api/v1/price-items/import', ['file' => $file($valid)])->assertOk()->assertJsonPath('data.committed', true);
        $this->assertSame(1, PriceItem::count());
        $this->postJson('/api/v1/price-items/import', ['file' => $file("ref;couleur\nA;B\n")])->assertStatus(422)->assertJsonPath('code', 'IMPORT_FILE_INVALID');
        $this->postJson('/api/v1/price-items/import', [])->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_the_catalogue_is_listed_filtered_and_visible_to_the_right_roles_only(): void
    {
        $this->importCsv($this->csv(
            'MGC01;fourniture;GC;Borne de repérage;u;5000',
            'POT02;prestation;POTEAUX;Plantation poteau béton;u;35000',
            'POT03;prestation;POTEAUX;Dépose poteau;u;9000',
        ));

        Sanctum::actingAs(User::create(['name' => 'be', 'email' => 'be@x.test', 'password' => 'secret123', 'role' => Role::BeManager]));
        $this->assertCount(3, $this->getJson('/api/v1/price-items')->assertOk()->json('data'));
        $this->assertSame(['POT02'], array_column($this->getJson('/api/v1/price-items?q=plantation')->json('data'), 'ref'));
        $this->assertCount(2, $this->getJson('/api/v1/price-items?kind=service')->json('data'));
        $this->assertCount(2, $this->getJson('/api/v1/price-items?section=POTEAUX')->json('data'));
        $this->assertSame([['section' => 'GC', 'kind' => 'supply'], ['section' => 'POTEAUX', 'kind' => 'service']], $this->getJson('/api/v1/price-items/sections')->json('data'));

        $designer = User::create(['name' => 'd', 'email' => 'd@x.test', 'password' => 'secret123', 'role' => Role::Technician]);
        TechnicianSkill::create(['user_id' => $designer->id, 'mission_type' => MissionType::Drawing]);
        Sanctum::actingAs($designer);
        $this->getJson('/api/v1/price-items')->assertOk();

        foreach ([Role::Supervisor, Role::Coordinator, Role::Technician] as $i => $role) {
            Sanctum::actingAs(User::create(['name' => 'u', 'email' => "no{$i}@x.test", 'password' => 'secret123', 'role' => $role]));
            $this->getJson('/api/v1/price-items')->assertForbidden();
        }
    }
}
