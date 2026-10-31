<?php

namespace Tests\Feature;

use App\Domain\Import\CustomerImportAnalyzer;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Enums\StudyResult;
use App\Models\CustomerOrder;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Jdv;
use App\Models\Pco;
use App\Models\Plaque;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerImportValidationTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = "id_client;ft;compte;nom;adresse;contact1;contact2;latitude;longitude;sous_plaque;splitter;pco;sn_ont;ligne_fixe;date_enregistrement;date_envoi;etude_technique;distance;etat_ligne;commentaire\n";

    private User $coordinator;

    private Pco $pco;

    protected function setUp(): void
    {
        parent::setUp();
        $this->coordinator = User::create(['name' => 'c', 'email' => 'c@x.test', 'password' => 'secret123', 'role' => Role::Coordinator]);
        $zone = Zone::create(['plaque_id' => Plaque::create(['name' => 'ABOBO'])->id, 'code' => 'ABB01']);
        $this->pco = Pco::create(['jdv_id' => Jdv::create(['zone_id' => $zone->id, 'code' => 'SPL001'])->id, 'code' => 'ABB01_PCO001']);
        Sanctum::actingAs($this->coordinator);
    }

    private function line(array $values): string
    {
        return implode(';', array_map(fn (string $column) => $values[$column] ?? '', CustomerImportAnalyzer::COLUMNS));
    }

    private function analyse(string ...$lines): string
    {
        $file = UploadedFile::fake()->createWithContent('clients.csv', self::HEADER.implode("\n", $lines)."\n");

        return $this->postJson('/api/v1/customer-imports', ['file' => $file])->assertCreated()->json('data.id');
    }

    public function test_the_validation_creates_orders_with_their_status_and_history_and_skips_refused_and_excluded_rows(): void
    {
        $id = $this->analyse(
            $this->line(['id_client' => 'C1', 'nom' => 'Un', 'contact1' => '707123456', 'pco' => 'ABB01_PCO001', 'etude_technique' => 'SANS PCO', 'ligne_fixe' => '2121234567']),
            $this->line(['id_client' => 'C2', 'nom' => 'Deux', 'etude_technique' => 'AVEC PCO']),
            $this->line(['id_client' => 'C3', 'nom' => 'Trois', 'contact1' => '123']),
            $this->line(['nom' => 'Sans ID']),
            $this->line(['id_client' => 'C5', 'nom' => 'Cinq']),
        );
        $rows = ImportRow::where('import_batch_id', $id)->orderBy('line')->get();
        $this->patchJson("/api/v1/customer-imports/{$id}/rows/{$rows[4]->id}", ['excluded' => true])->assertOk()->assertJsonPath('data.excluded', true);

        $this->postJson("/api/v1/customer-imports/{$id}/validate")->assertOk()->assertJsonPath('data', ['created' => 3, 'updated' => 0, 'excluded' => 1, 'rejected' => 1]);

        $this->assertSame(['C1', 'C2', 'C3'], CustomerOrder::orderBy('customer_ref')->pluck('customer_ref')->all());
        $one = CustomerOrder::where('customer_ref', 'C1')->firstOrFail();
        $this->assertSame(OrderStatus::Studied, $one->status);
        $this->assertSame(StudyResult::Feasible, $one->study_result);
        $this->assertSame('+2250707123456', $one->phone_1);
        $this->assertSame('707123456', $one->phone_1_raw);
        $this->assertSame($this->pco->id, $one->pco_id);
        $this->assertSame('2121234567', $one->fixed_line);
        $this->assertSame(OrderStatus::Received, CustomerOrder::where('customer_ref', 'C2')->firstOrFail()->status);
        $this->assertNull(CustomerOrder::where('customer_ref', 'C3')->firstOrFail()->phone_1, 'un numéro anormal n\'est pas enregistré');

        $history = $one->history()->get();
        $this->assertCount(1, $history);
        $this->assertNull($history[0]->from_status);
        $this->assertSame(OrderStatus::Studied, $history[0]->to_status);
        $this->assertSame($this->coordinator->id, $history[0]->changed_by);
    }

    public function test_a_known_id_is_updated_without_erasing_known_values_or_changing_its_status(): void
    {
        $order = CustomerOrder::create(['customer_ref' => 'E1', 'name' => 'Ancien nom', 'phone_1' => '+2250101010101', 'address_raw' => 'Ancienne adresse', 'ont_serial' => 'SN-ANCIEN']);
        $order->changeStatus(OrderStatus::InStudy, $this->coordinator);

        $id = $this->analyse($this->line(['id_client' => 'E1', 'nom' => 'Nouveau nom', 'ligne_fixe' => '2121234567', 'etude_technique' => 'SANS PCO']));
        $this->postJson("/api/v1/customer-imports/{$id}/validate")->assertOk()->assertJsonPath('data.updated', 1)->assertJsonPath('data.created', 0);

        $fresh = $order->fresh();
        $this->assertSame('Nouveau nom', $fresh->name);
        $this->assertSame('2121234567', $fresh->fixed_line);
        $this->assertSame('+2250101010101', $fresh->phone_1, 'le fichier sans contact ne vide pas le contact connu');
        $this->assertSame('Ancienne adresse', $fresh->address_raw);
        $this->assertSame('SN-ANCIEN', $fresh->ont_serial);
        $this->assertSame(OrderStatus::InStudy, $fresh->status, 'le statut ne bouge pas à la mise à jour');
        $this->assertSame(1, $fresh->history()->count());
        $this->assertSame(1, CustomerOrder::count());
    }

    public function test_a_batch_is_validated_once_and_keeps_no_personal_data_afterwards(): void
    {
        $id = $this->analyse($this->line(['id_client' => 'A1', 'nom' => 'Un', 'contact1' => '0707123456', 'adresse' => 'Rue secrète', 'latitude' => '5.36', 'longitude' => '-4.0083']));

        $this->postJson("/api/v1/customer-imports/{$id}/validate")->assertOk();
        $this->postJson("/api/v1/customer-imports/{$id}/validate")->assertStatus(422)->assertJsonValidationErrors('batch');
        $this->assertSame(1, CustomerOrder::count());

        $this->assertSame('validated', ImportBatch::findOrFail($id)->status);
        $this->assertEquals(['customer_ref' => 'A1', 'name' => 'Un'], ImportRow::where('import_batch_id', $id)->firstOrFail()->data);

        $row = ImportRow::where('import_batch_id', $id)->firstOrFail();
        $this->patchJson("/api/v1/customer-imports/{$id}/rows/{$row->id}", ['excluded' => true])->assertStatus(422)->assertJsonValidationErrors('excluded');
    }

    public function test_a_refused_row_cannot_be_included_and_the_flag_is_validated(): void
    {
        $id = $this->analyse($this->line(['nom' => 'Sans ID']), $this->line(['id_client' => 'A1', 'nom' => 'Un']));
        $rows = ImportRow::where('import_batch_id', $id)->orderBy('line')->get();

        $this->patchJson("/api/v1/customer-imports/{$id}/rows/{$rows[0]->id}", ['excluded' => false])->assertStatus(422)->assertJsonValidationErrors('excluded');
        $this->patchJson("/api/v1/customer-imports/{$id}/rows/{$rows[1]->id}", ['excluded' => 'peut-être'])->assertStatus(422)->assertJsonValidationErrors('excluded');
        $this->patchJson("/api/v1/customer-imports/{$id}/rows/{$rows[1]->id}", ['excluded' => true])->assertOk();
        $this->patchJson("/api/v1/customer-imports/{$id}/rows/{$rows[1]->id}", ['excluded' => false])->assertOk()->assertJsonPath('data.excluded', false);
    }

    public function test_only_the_owner_or_the_administrator_validates(): void
    {
        $id = $this->analyse($this->line(['id_client' => 'A1', 'nom' => 'Un']));
        $row = ImportRow::where('import_batch_id', $id)->firstOrFail();

        foreach ([User::create(['name' => 'c2', 'email' => 'c2@x.test', 'password' => 'secret123', 'role' => Role::Coordinator]), User::create(['name' => 'dg', 'email' => 'dg@x.test', 'password' => 'secret123', 'role' => Role::Dg])] as $other) {
            Sanctum::actingAs($other);
            $this->postJson("/api/v1/customer-imports/{$id}/validate")->assertForbidden();
            $this->patchJson("/api/v1/customer-imports/{$id}/rows/{$row->id}", ['excluded' => true])->assertForbidden();
        }
        $this->assertSame(0, CustomerOrder::count());

        Sanctum::actingAs(User::create(['name' => 'a', 'email' => 'a@x.test', 'password' => 'secret123', 'role' => Role::Admin]));
        $this->postJson("/api/v1/customer-imports/{$id}/validate")->assertOk()->assertJsonPath('data.created', 1);
    }

    public function test_a_reimport_of_the_same_file_updates_instead_of_duplicating(): void
    {
        $line = $this->line(['id_client' => 'A1', 'nom' => 'Un']);

        $this->postJson('/api/v1/customer-imports/'.$this->analyse($line).'/validate')->assertOk()->assertJsonPath('data.created', 1);
        $second = $this->analyse($line);
        $this->getJson("/api/v1/customer-imports/{$second}")->assertJsonPath('data.counts.update', 1);
        $this->postJson("/api/v1/customer-imports/{$second}/validate")->assertOk()->assertJsonPath('data.updated', 1);

        $this->assertSame(1, CustomerOrder::count());
        $this->assertSame(1, CustomerOrder::firstOrFail()->history()->count());
    }
}
