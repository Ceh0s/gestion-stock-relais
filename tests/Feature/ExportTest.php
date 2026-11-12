<?php

namespace Tests\Feature;

use App\Exports\MaterielsExport;
use App\Models\Emplacement;
use App\Models\JournalExport;
use App\Models\Materiel;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * Export Excel : Algorithme §12 (nom du fichier, colonnes, journal).
 */
class ExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'administrateur', 'prenom' => 'Alice', 'nom' => 'Martin']);
        $this->site = Site::create(['nom' => 'Site 1']);
        $emplacement = Emplacement::create(['site_id' => $this->site->id, 'nom' => 'Réserve']);
        $jean = User::factory()->create(['email' => 'jean.dupont@exemple.fr']);

        Materiel::create([
            'reference' => 'PC-1', 'type_materiel' => 'PC', 'marque' => 'Dell', 'modele' => 'X', 'condition_stock' => 'neuf',
            'statut' => 'attribue', 'emplacement_id' => $emplacement->id, 'utilisateur_id' => $jean->id,
        ]);
        Materiel::create([
            'reference' => 'PC-2', 'type_materiel' => 'PC', 'marque' => 'HP', 'modele' => 'Y', 'condition_stock' => 'occasion',
            'statut' => 'reparation', 'emplacement_id' => $emplacement->id,
        ]);
    }

    public function test_le_fichier_est_nomme_avec_le_site_et_la_date(): void
    {
        Excel::fake();

        $this->actingAs($this->admin)->get("/export/materiels?site_id={$this->site->id}");

        Excel::assertDownloaded('inventaire_site_1_'.now()->format('Y-m-d').'.xlsx');
    }

    public function test_la_colonne_utilisateur_contient_l_email_relu_par_l_import(): void
    {
        $lignes = (new MaterielsExport)->collection();

        $this->assertSame('jean.dupont@exemple.fr', $lignes->firstWhere('reference', 'PC-1')['utilisateur']);
    }

    public function test_chaque_export_est_enregistre_dans_le_journal(): void
    {
        Excel::fake();

        $this->actingAs($this->admin)->get('/export/materiels?statut=reparation&terme=HP');

        $journal = JournalExport::sole();
        $this->assertSame($this->admin->id, $journal->utilisateur_id);
        $this->assertSame('inventaire_'.now()->format('Y-m-d').'.xlsx', $journal->nom_fichier);
        $this->assertSame(['terme' => 'HP', 'statut' => 'reparation'], $journal->filtres);
        $this->assertSame(1, $journal->nombre_lignes);
        $this->assertSame('Recherche : « HP » · Statut : En réparation', $journal->resumeFiltres());
    }

    public function test_le_journal_des_exports_est_visible_par_l_administrateur_seulement(): void
    {
        JournalExport::create(['utilisateur_id' => $this->admin->id, 'nom_fichier' => 'inventaire_2026-11-12.xlsx', 'nombre_lignes' => 2]);

        $this->actingAs($this->admin)->get('/imports')
            ->assertOk()
            ->assertSee('Derniers exports')
            ->assertSee('inventaire_2026-11-12.xlsx')
            ->assertSee('Tout l&#039;inventaire', false);

        $gestionnaire = User::factory()->create(['role' => 'gestionnaire_stock']);
        $this->actingAs($gestionnaire)->get('/imports')->assertOk()->assertDontSee('Derniers exports');
    }
}
