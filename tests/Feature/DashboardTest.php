<?php

namespace Tests\Feature;

use App\Models\Emplacement;
use App\Models\Import;
use App\Models\Materiel;
use App\Models\Mouvement;
use App\Models\Parametre;
use App\Models\Site;
use App\Models\User;
use App\Services\MaterielService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tableau de bord : Algorithme §14 et cahier des charges §6, réglage du seuil de stock faible.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Emplacement $reserve;

    private Emplacement $atelier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'administrateur']);
        $site1 = Site::create(['nom' => 'Site 1']);
        $site2 = Site::create(['nom' => 'Site 2']);
        $this->reserve = Emplacement::create(['site_id' => $site1->id, 'nom' => 'Réserve']);
        $this->atelier = Emplacement::create(['site_id' => $site2->id, 'nom' => 'Atelier']);
    }

    private function materiel(string $reference, array $valeurs = []): Materiel
    {
        return Materiel::create(array_merge([
            'reference' => $reference, 'type_materiel' => 'PC', 'marque' => 'Dell', 'modele' => 'Latitude',
            'condition_stock' => 'neuf', 'statut' => 'disponible', 'emplacement_id' => $this->reserve->id,
        ], $valeurs))->fresh();
    }

    private function donnees(): array
    {
        return $this->actingAs($this->admin)->get('/dashboard')->assertOk()->original->getData();
    }

    public function test_les_compteurs_par_statut_condition_et_site(): void
    {
        $this->materiel('PC-1');
        $this->materiel('PC-2', ['condition_stock' => 'occasion', 'statut' => 'reserve']);
        $this->materiel('PC-3', ['condition_stock' => 'reconditionne', 'statut' => 'reparation', 'emplacement_id' => $this->atelier->id]);
        $this->materiel('PC-4', ['emplacement_id' => null, 'statut' => 'a_verifier']);

        $donnees = $this->donnees();

        $this->assertSame(4, (int) $donnees['total']);
        $this->assertSame(1, (int) $donnees['parStatut']['reserve']);
        $this->assertSame(1, (int) $donnees['parCondition']['reconditionne']);
        $this->assertSame(2, (int) $donnees['parCondition']['neuf']);
        $this->assertSame(['Site 1' => 2, 'Site 2' => 1], $donnees['parSite']->mapWithKeys(fn ($s) => [$s->nom => (int) $s->nombre])->all());
        $this->assertSame(['PC-4'], $donnees['sansEmplacement']->pluck('reference')->all());

        $this->actingAs($this->admin)->get('/dashboard')
            ->assertSee('Reconditionné')
            ->assertSee('Matériels sans emplacement')
            ->assertSee(route('materiels.index', ['statut' => 'reserve']), false);
    }

    public function test_la_reparation_longue_se_base_sur_la_date_d_envoi_et_non_sur_la_derniere_modification(): void
    {
        $service = app(MaterielService::class);

        $ancien = $this->materiel('PC-ANCIEN');
        $service->mettreEnReparation($ancien, 'Écran cassé', $this->admin);
        Mouvement::where('materiel_id', $ancien->id)->update(['created_at' => now()->subDays(20)]);
        // Correction de la fiche aujourd'hui : updated_at change, la date d'envoi non.
        $service->modifier($ancien->fresh(), ['modele' => 'Latitude 5420'], $this->admin, null);

        $recent = $this->materiel('PC-RECENT');
        $service->mettreEnReparation($recent, null, $this->admin);
        Mouvement::where('materiel_id', $recent->id)->update(['created_at' => now()->subDay()]);

        $liste = $this->donnees()['enReparationLongue'];

        $this->assertSame(['PC-ANCIEN'], $liste->pluck('reference')->all());
        $this->assertSame(now()->subDays(20)->toDateString(), $liste->first()->envoye_le->toDateString());
        $this->actingAs($this->admin)->get('/dashboard')->assertSee('depuis le '.now()->subDays(20)->format('d/m/Y'));
    }

    public function test_les_stocks_faibles_suivent_le_seuil(): void
    {
        $this->materiel('PC-1');
        $this->materiel('PC-2');
        $this->materiel('ECR-1', ['type_materiel' => 'Écran']);
        $this->materiel('ECR-2', ['type_materiel' => 'Écran', 'statut' => 'attribue']);
        $this->materiel('TEL-1', ['type_materiel' => 'Téléphone', 'statut' => 'reforme']); // ignoré

        Parametre::definir(Parametre::SEUIL_STOCK_FAIBLE, 2);
        $this->assertSame(['Écran'], $this->donnees()['stocksFaibles']->pluck('type_materiel')->all());

        Parametre::definir(Parametre::SEUIL_STOCK_FAIBLE, 3);
        $this->assertSame(['Écran', 'PC'], $this->donnees()['stocksFaibles']->pluck('type_materiel')->all());

        Parametre::definir(Parametre::SEUIL_STOCK_FAIBLE, 0);
        $this->assertTrue($this->donnees()['stocksFaibles']->isEmpty());
        $this->actingAs($this->admin)->get('/dashboard')->assertSee('Alerte désactivée');
    }

    public function test_recemment_attribues_et_derniers_imports(): void
    {
        $jean = User::factory()->create(['prenom' => 'Jean', 'nom' => 'Dupont']);
        $materiel = $this->materiel('PC-ATTRIBUE');
        app(MaterielService::class)->attribuer($materiel, $jean->id, null, $this->admin);
        // Déplacer un matériel déjà attribué n'est pas une nouvelle attribution.
        app(MaterielService::class)->deplacer($materiel, $this->atelier->id, null, null, $this->admin);
        $this->assertCount(1, $this->donnees()['recemmentAttribues']);
        Import::create(['utilisateur_id' => $this->admin->id, 'nom_fichier' => 'inventaire_site_1.xlsx', 'statut' => 'termine', 'nombre_importees' => 12]);

        $this->actingAs($this->admin)->get('/dashboard')
            ->assertSee('Récemment attribués')
            ->assertSee('PC-ATTRIBUE')
            ->assertSee('à Jean Dupont')
            ->assertSee('inventaire_site_1.xlsx')
            ->assertSee('12 importé(s)');
    }

    public function test_le_tableau_de_bord_est_visible_par_le_role_consultation(): void
    {
        $consultation = User::factory()->create(['role' => 'consultation']);

        $this->actingAs($consultation)->get('/dashboard')->assertOk()->assertSee('Matériels par site')->assertDontSee('Paramètres');
    }

    // --- Paramètres -----------------------------------------------------------------------

    public function test_le_seuil_par_defaut_vient_de_la_migration(): void
    {
        $this->assertSame(2, Parametre::seuilStockFaible());
    }

    public function test_l_administrateur_modifie_le_seuil(): void
    {
        $this->actingAs($this->admin)->get('/parametres')->assertOk()->assertSee('Seuil de stock faible');

        $this->actingAs($this->admin)->put('/parametres', ['seuil_stock_faible' => 5])
            ->assertRedirect('/parametres')
            ->assertSessionHasNoErrors();

        $this->assertSame(5, Parametre::seuilStockFaible());
    }

    public function test_un_seuil_negatif_est_refuse(): void
    {
        $this->actingAs($this->admin)->put('/parametres', ['seuil_stock_faible' => -1])->assertSessionHasErrors('seuil_stock_faible');

        $this->assertSame(2, Parametre::seuilStockFaible());
    }

    public function test_seul_l_administrateur_accede_aux_parametres(): void
    {
        $gestionnaire = User::factory()->create(['role' => 'gestionnaire_stock']);

        $this->actingAs($gestionnaire)->get('/parametres')->assertForbidden();
        $this->actingAs($gestionnaire)->put('/parametres', ['seuil_stock_faible' => 9])->assertForbidden();
        $this->actingAs($gestionnaire)->get('/dashboard')->assertDontSee(route('parametres.edit'), false);
        $this->actingAs($this->admin)->get('/dashboard')->assertSee(route('parametres.edit'), false);
    }
}
