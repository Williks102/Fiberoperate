<?php

namespace Tests\Feature;

use App\Domain\Import\CustomerImportAnalyzer;
use App\Enums\Role;
use App\Models\CustomerOrder;
use App\Models\ImportBatch;
use App\Models\Jdv;
use App\Models\Pco;
use App\Models\Plaque;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerImportApiTest extends TestCase
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

    public function test_only_the_coordinator_and_the_administrator_analyse_and_each_sees_their_own_batches(): void
    {
        $file = fn (string $content) => UploadedFile::fake()->createWithContent('clients.csv', $content);
        $valid = self::HEADER.$this->line(['id_client' => 'A1', 'nom' => 'Un', 'contact1' => '0707123456'])."\n";

        foreach ([Role::Dg, Role::Dt, Role::Supervisor, Role::BeManager, Role::Technician] as $i => $role) {
            Sanctum::actingAs(User::create(['name' => 'u', 'email' => "u{$i}@x.test", 'password' => 'secret123', 'role' => $role]));
            $this->postJson('/api/v1/customer-imports', ['file' => $file($valid)])->assertForbidden();
        }
        $this->assertSame(0, ImportBatch::count());

        Sanctum::actingAs($this->coordinator);
        $id = $this->postJson('/api/v1/customer-imports', ['file' => $file($valid)])->assertCreated()->assertJsonPath('data.counts.ok', 1)->json('data.id');
        $this->postJson('/api/v1/customer-imports', ['file' => $file("id_client;couleur\nA;B\n")])->assertStatus(422)->assertJsonPath('code', 'IMPORT_FILE_INVALID');
        $this->postJson('/api/v1/customer-imports', [])->assertStatus(422)->assertJsonValidationErrors('file');

        $this->getJson("/api/v1/customer-imports/{$id}")->assertOk()->assertJsonPath('data.rows.0.customer_ref', 'A1')->assertJsonPath('data.rows.0.status', 'ok');

        Sanctum::actingAs(User::create(['name' => 'c2', 'email' => 'c2@x.test', 'password' => 'secret123', 'role' => Role::Coordinator]));
        $this->getJson("/api/v1/customer-imports/{$id}")->assertForbidden();

        Sanctum::actingAs(User::create(['name' => 'a', 'email' => 'a@x.test', 'password' => 'secret123', 'role' => Role::Admin]));
        $this->getJson("/api/v1/customer-imports/{$id}")->assertOk();
        $this->assertSame(0, CustomerOrder::count());
    }

    public function test_the_review_lists_rows_filtered_by_status_and_page(): void
    {
        Sanctum::actingAs($this->coordinator);
        $lines = collect(range(1, 30))->map(fn ($i) => $this->line(['id_client' => "R{$i}", 'nom' => "Client {$i}", 'contact1' => $i % 10 === 0 ? '123' : '0707123456']))->all();
        $file = UploadedFile::fake()->createWithContent('clients.csv', self::HEADER.implode("\n", $lines)."\n");

        $id = $this->postJson('/api/v1/customer-imports', ['file' => $file])->assertCreated()->assertJsonPath('data.counts.to_fix', 3)->json('data.id');

        $this->getJson("/api/v1/customer-imports/{$id}?per_page=10")->assertOk()->assertJsonCount(10, 'data.rows')->assertJsonPath('meta.last_page', 3);
        $this->getJson("/api/v1/customer-imports/{$id}?status=to_fix")->assertJsonCount(3, 'data.rows')->assertJsonPath('data.rows.0.anomalies.0.level', 'warning');
    }
}
