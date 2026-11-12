<?php

namespace Tests\Feature;

use App\Exports\MaterielsExport;
use App\Models\Emplacement;
use App\Models\Materiel;
use App\Models\Site;
use App\Models\User;
use App\Services\MaterielService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * Recherche rapide : cahier des charges §5 et §13, Algorithme §5 et tests §21.
 */
class RechercheTest extends TestCase
{
    use RefreshDatabase;

    private Emplacement $reserveSite1;

    private Emplacement $atelierSite2;

    private User $jean;

    protected function setUp(): void
    {
        parent::setUp();

        $site1 = Site::create(['nom' => 'Site 1']);
        $site2 = Site::create(['nom' => 'Site 2']);
        $this->reserveSite1 = Emplacement::create(['site_id' => $site1->id, 'nom' => 'Réserve informatique']);
        $this->atelierSite2 = Emplacement::create(['site_id' => $site2->id, 'nom' => 'Atelier']);
        $this->jean = User::factory()->create(['prenom' => 'Jean', 'nom' => 'Dupont']);

        $this->materiel('PC-000245', ['numero_serie' => 'ABC123456']);
        $this->materiel('PC-000312', ['condition_stock' => 'occasion', 'statut' => 'attribue', 'utilisateur_id' => $this->jean->id]);
        $this->materiel('PC-000418', ['condition_stock' => 'retour', 'statut' => 'reparation', 'emplacement_id' => $this->atelierSite2->id]);
        $this->materiel('MON-000118', ['type_materiel' => 'Écran', 'marque' => 'HP', 'modele' => 'E243', 'condition_stock' => 'occasion']);
    }

    private function materiel(string $reference, array $valeurs = []): Materiel
    {
        return Materiel::create(array_merge([
            'reference' => $reference, 'type_materiel' => 'Ordinateur portable', 'marque' => 'Dell',
            'modele' => 'Latitude 5420', 'condition_stock' => 'neuf', 'statut' => 'disponible',
            'emplacement_id' => $this->reserveSite1->id,
        ], $valeurs));
    }

    private function references(array $criteres): array
    {
        return app(MaterielService::class)->requeteRecherche($criteres)->pluck('reference')->all();
    }

    public function test_rechercher_par_reference(): void
    {
        $this->assertSame(['PC-000245'], $this->references(['terme' => '000245']));
    }

    public function test_rechercher_par_numero_de_serie(): void
    {
        $this->assertSame(['PC-000245'], $this->references(['terme' => 'ABC123456']));
    }

    public function test_filtrer_par_site(): void
    {
        $this->assertSame(['PC-000418'], $this->references(['site_id' => $this->atelierSite2->site_id]));
    }

    public function test_filtrer_par_neuf(): void
    {
        $this->assertSame(['PC-000245'], $this->references(['condition_stock' => 'neuf']));
    }

    public function test_filtrer_par_reparation(): void
    {
        $this->assertSame(['PC-000418'], $this->references(['statut' => 'reparation']));
    }

    public function test_plusieurs_mots_cherches_dans_des_champs_differents(): void
    {
        // Parcours du cahier des charges §13 : marque et modèle tapés ensemble, puis filtre Disponible + Neuf.
        $this->assertSame(
            ['PC-000245'],
            $this->references(['terme' => 'Dell Latitude 5420', 'statut' => 'disponible', 'condition_stock' => 'neuf'])
        );
    }

    public function test_rechercher_par_nom_d_utilisateur_et_par_emplacement(): void
    {
        $this->assertSame(['PC-000312'], $this->references(['terme' => 'Jean Dupont']));
        $this->assertSame(['PC-000418'], $this->references(['terme' => 'atelier']));
    }

    public function test_filtrer_par_type_emplacement_et_utilisateur(): void
    {
        $this->assertSame(['MON-000118'], $this->references(['type_materiel' => 'Écran']));
        $this->assertSame(['PC-000418'], $this->references(['emplacement_id' => $this->atelierSite2->id]));
        $this->assertSame(['PC-000312'], $this->references(['utilisateur_id' => $this->jean->id]));
    }

    public function test_trier_par_derniere_modification(): void
    {
        Materiel::where('reference', 'MON-000118')->update(['updated_at' => now()->addMinute()]);

        $this->assertSame('MON-000118', $this->references(['tri' => 'recent'])[0]);
        $this->assertSame('MON-000118', $this->references([])[0]); // tri par référence : "MON" avant "PC"
        $this->assertSame('PC-000245', $this->references([])[1]);
    }

    public function test_la_page_affiche_le_nombre_de_resultats_et_l_utilisateur(): void
    {
        $this->actingAs($this->jean)->get('/materiels?terme=PC-000312')
            ->assertOk()
            ->assertSee('1 matériel(s) trouvé(s).')
            ->assertSee('Jean Dupont');
    }

    public function test_le_bouton_d_export_est_cache_pour_le_role_consultation(): void
    {
        $this->actingAs($this->jean)->get('/materiels')->assertOk()->assertDontSee('Exporter Excel');

        $gestionnaire = User::factory()->create(['role' => 'gestionnaire_stock']);
        $this->actingAs($gestionnaire)->get('/materiels')->assertSee('Exporter Excel');
    }

    public function test_l_export_reprend_la_recherche_texte(): void
    {
        Excel::fake();
        $gestionnaire = User::factory()->create(['role' => 'gestionnaire_stock']);

        $this->actingAs($gestionnaire)->get('/export/materiels?terme=HP');

        Excel::assertDownloaded('materiels_'.now()->format('Y-m-d').'.xlsx', function (MaterielsExport $export) {
            return $export->collection()->pluck('reference')->all() === ['MON-000118'];
        });
    }

    public function test_l_accueil_propose_la_recherche(): void
    {
        $this->actingAs($this->jean)->get('/dashboard')
            ->assertOk()
            ->assertSee('action="'.route('materiels.index').'"', false)
            ->assertSee('name="terme"', false);
    }
}
