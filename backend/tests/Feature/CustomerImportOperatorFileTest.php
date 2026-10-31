<?php

namespace Tests\Feature;

use App\Domain\Import\CustomerImportAnalyzer;
use App\Domain\Import\ValidateCustomerImport;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\CustomerOrder;
use App\Models\Jdv;
use App\Models\Pco;
use App\Models\Plaque;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Fichier des instances tel que l'envoie l'opérateur (en-têtes, virgules, décimales en virgule). Noms et numéros fictifs. */
class CustomerImportOperatorFileTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'Ville,Commune,Numéro Abonnement,Nom Client,Localisation,Contact1,Contact2,Latitude client,Longitude client,"ZONE PCO(SRO,OLT..)",JDV/HUB,PCO,SN ONT,LIGNE FIXE,Date d\'enregistrement d\'instances,Durée d\'attente (en jour),date d\'envoi des instanaces aux prestataires,ETUDE TECHNIQUE,Besoin en poteau betons,Besoin en poteau metalliques,réalisation survey poteau beton ( OK ou NOK),réalisation Implantation Poteau beton ( OK ou NOK),Date de rendez-vous de raccordement,Equipe raccordement,Distance PCO-Client,Entreprise FTTH,"Avancement ( raccordé , BLOQUE , en cours de raccordement , ……)",Problèmes,"Commentaires ( manque poteaux betons, metalliques, cable FO, …..)",PROGRAMME DE DEPLOIEMENT,DIGITAUX / ENTREPRISE,Latitude et logitude Commerciaux,Latitude et logitude Commerciaux,OLT_PON,Colonne 1,Colonne 2'."\n";

    private User $coordinator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->coordinator = User::create(['name' => 'c', 'email' => 'c@x.test', 'password' => 'secret123', 'role' => Role::Coordinator]);
    }

    private function analyse(string ...$lines)
    {
        return (new CustomerImportAnalyzer)->analyse(CustomerImportAnalyzer::parse(self::HEADER.implode("\n", $lines)."\n"), $this->coordinator, 'exemple.csv');
    }

    private function row($batch, int $index = 0): array
    {
        return $batch->rows()->orderBy('line')->get()[$index]->toArray();
    }

    public function test_the_operator_headers_are_recognised_and_the_other_columns_reported_as_ignored(): void
    {
        $batch = $this->analyse('DIVO,DIVO,RAB0000000000001,CLIENT ESSAI,DIVO DIALOGUE MOSQUEE,758612385,758612385,"5,82198","-5,36339",DDI01,HUB 01,DDI01_PCO026,,,,,1/4/2026,Sans PCO,,1,,,,EQUIPE A,250,OC IVOIRE,En cours de raccordement,,,,,,,,');

        $data = $this->row($batch)['data'];
        $this->assertSame('RAB0000000000001', $data['customer_ref']);
        $this->assertSame('CLIENT ESSAI', $data['name']);
        $this->assertSame('DIVO DIALOGUE MOSQUEE', $data['address_raw']);
        $this->assertSame('DIVO', $data['city']);
        $this->assertSame('DIVO', $data['commune']);
        $this->assertSame('+2250758612385', $data['phone_1'], 'le zéro initial manquant est rétabli');
        $this->assertSame('5.8219800', $data['declared_latitude']);
        $this->assertSame('-5.3633900', $data['declared_longitude']);
        $this->assertSame('2026-04-01', $data['sent_to_contractor_at']);
        $this->assertSame('done', $data['initial_study']);
        $this->assertSame(250, $data['distance_m']);

        $ignored = $batch->counts['ignored_columns'];
        $this->assertContains('Entreprise FTTH', $ignored);
        $this->assertContains('Equipe raccordement', $ignored);
        $this->assertNotContains('Nom Client', $ignored);
        $this->assertNotContains('Numéro Abonnement', $ignored);
    }

    public function test_the_operator_progress_columns_are_kept_in_the_observation_with_the_unlinked_network_codes(): void
    {
        $batch = $this->analyse('DIVO,DIVO,RAB0000000000002,CLIENT B,ADRESSE,,,,,DBA 01,HUB 01,DBA01_PCO30,,,,,1/4/2026,Sans PCO,,3,,,,,350,OC IVOIRE,En cours de raccordement,Accès difficile,manque poteaux,,,,,,,');

        $row = $this->row($batch);
        $this->assertSame('to_fix', $row['status'], 'réseau pas encore créé : à vérifier, pas refusé');
        $this->assertSame(
            'manque poteaux · Avancement : En cours de raccordement · Problèmes : Accès difficile · Besoin poteaux métalliques : 3 · Sous-plaque (fichier) : DBA 01 · PCO (fichier) : DBA01_PCO30',
            $row['data']['observation'],
        );
        $this->assertNull($row['data']['pco_id']);
    }

    public function test_a_pco_written_without_zero_padding_is_matched_to_the_network(): void
    {
        $zone = Zone::create(['plaque_id' => Plaque::create(['name' => 'DIVO'])->id, 'code' => 'DBA01']);
        $jdv = Jdv::create(['zone_id' => $zone->id, 'code' => 'HUB001']);
        $pco = Pco::create(['jdv_id' => $jdv->id, 'zone_id' => $zone->id, 'code' => 'DBA01_PCO030']);

        $row = $this->row($this->analyse('DIVO,DIVO,RAB0000000000003,CLIENT C,ADRESSE,,,,,DBA 01,HUB 01,DBA01_PCO30,,,,,1/4/2026,Sans PCO,,,,,,,200,OC IVOIRE,En cours de raccordement,,,,,,,,'));

        $this->assertSame($pco->id, $row['data']['pco_id']);
        $this->assertSame($zone->id, $row['data']['zone_id']);
        $this->assertSame('ok', $row['status']);
        $this->assertStringNotContainsString('(fichier)', (string) $row['data']['observation']);
    }

    public function test_study_not_done_is_an_instance_and_latitude_longitude_in_one_cell_are_split(): void
    {
        $row = $this->row($this->analyse('DIVO,DIVO,RAB0000000000004,CLIENT D,DIVO DIALOGUE,101756966,172265591,5.845397;-5.475435,,,,,,,,,14/4/2026,Etude Non Réalisée,,,,,,EQUIPE B,,OC IVOIRE,Etude en cours,,,,,,,,'));

        $this->assertSame('to_do', $row['data']['initial_study']);
        $this->assertSame('Etude Non Réalisée', $row['data']['initial_study_raw']);
        $this->assertSame('5.8453970', $row['data']['declared_latitude']);
        $this->assertSame('-5.4754350', $row['data']['declared_longitude']);
        $this->assertContains('info', array_column($row['anomalies'], 'level'));
    }

    public function test_an_already_connected_client_is_imported_as_installed_and_a_studied_one_as_connectable(): void
    {
        $batch = $this->analyse(
            'DIVO,DIVO,RAB0000000000005,DEJA RACCORDE,ADRESSE,,,,,,,,,,,,1/4/2026,Sans PCO,,,,,,,,OC IVOIRE,raccordé,,,,,,,,',
            'DIVO,DIVO,RAB0000000000006,A RACCORDER,ADRESSE,,,,,,,,,,,,1/4/2026,Sans PCO,,,,,,,,OC IVOIRE,En cours de raccordement,,,,,,,,',
            'DIVO,DIVO,RAB0000000000007,A ETUDIER,ADRESSE,,,,,,,,,,,,1/4/2026,Etude Non Réalisée,,,,,,,,OC IVOIRE,Etude en cours,,,,,,,,',
        );

        app(ValidateCustomerImport::class)->run($batch, $this->coordinator);

        $status = fn (string $ref) => CustomerOrder::where('customer_ref', $ref)->first();
        $this->assertSame(OrderStatus::Installed, $status('RAB0000000000005')->status);
        $this->assertSame('instances', $status('RAB0000000000007')->stage());
        $this->assertSame('raccordables', $status('RAB0000000000006')->stage());
        $this->assertSame('DIVO', $status('RAB0000000000006')->city);
        $this->assertSame('raccordes', $status('RAB0000000000005')->stage());
    }
}
