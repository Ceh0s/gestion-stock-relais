<?php

namespace Tests\Feature;

use App\Models\Emplacement;
use App\Models\Materiel;
use App\Models\Mouvement;
use App\Models\Site;
use App\Models\User;
use App\Services\MaterielService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Suppression définitive (administrateur) : seulement ce qui n'a jamais servi,
 * pour ne rien retirer de l'historique. Sinon on désactive (sites, emplacements)
 * ou on réforme (matériels).
 */
class SuppressionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Site $site;

    private Emplacement $reserve;

    private Emplacement $atelier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'administrateur']);
        $this->site = Site::create(['nom' => 'Site 1']);
        $this->reserve = Emplacement::create(['site_id' => $this->site->id, 'nom' => 'Réserve']);
        $this->atelier = Emplacement::create(['site_id' => $this->site->id, 'nom' => 'Atelier']);
    }

    private function materielCree(array $valeurs = []): Materiel
    {
        return app(MaterielService::class)->ajouter(array_merge([
            'reference' => 'PC-ERREUR', 'type_materiel' => 'PC', 'marque' => 'Dell', 'modele' => 'X',
            'condition_stock' => 'neuf', 'statut' => 'disponible', 'emplacement_id' => $this->reserve->id,
        ], $valeurs), $this->admin);
    }

    // --- Emplacements ---------------------------------------------------------------------

    public function test_un_emplacement_jamais_utilise_est_supprime(): void
    {
        $this->actingAs($this->admin)->delete("/emplacements/{$this->atelier->id}")
            ->assertRedirect('/emplacements')
            ->assertSessionHasNoErrors();

        $this->assertNull(Emplacement::find($this->atelier->id));
    }

    public function test_un_emplacement_qui_porte_un_materiel_est_refuse(): void
    {
        $this->materielCree();

        $this->actingAs($this->admin)->from('/emplacements')->delete("/emplacements/{$this->reserve->id}")
            ->assertSessionHasErrors('emplacement');

        $this->assertNotNull(Emplacement::find($this->reserve->id));
    }

    public function test_un_emplacement_cite_seulement_dans_l_historique_est_refuse(): void
    {
        // Le matériel est passé par l'atelier puis en est reparti : l'atelier est vide mais l'historique le cite.
        $materiel = $this->materielCree();
        $service = app(MaterielService::class);
        $service->deplacer($materiel, $this->atelier->id, null, null, $this->admin);
        $service->deplacer($materiel, $this->reserve->id, null, null, $this->admin);

        $this->assertSame(0, $this->atelier->materiels()->count());
        $this->actingAs($this->admin)->delete("/emplacements/{$this->atelier->id}")->assertSessionHasErrors('emplacement');
        $this->assertNotNull(Emplacement::find($this->atelier->id));
    }

    // --- Sites ----------------------------------------------------------------------------

    public function test_un_site_jamais_utilise_est_supprime_avec_ses_emplacements(): void
    {
        $this->actingAs($this->admin)->delete("/sites/{$this->site->id}")
            ->assertRedirect('/sites')
            ->assertSessionHasNoErrors();

        $this->assertNull(Site::find($this->site->id));
        $this->assertSame(0, Emplacement::count());
    }

    public function test_un_site_utilise_est_refuse(): void
    {
        $this->materielCree();

        $this->actingAs($this->admin)->delete("/sites/{$this->site->id}")->assertSessionHasErrors('site');

        $this->assertNotNull(Site::find($this->site->id));
        $this->assertSame(2, Emplacement::count());
    }

    // --- Matériels ------------------------------------------------------------------------

    public function test_un_materiel_qui_n_a_que_sa_creation_est_supprime(): void
    {
        $materiel = $this->materielCree();
        $this->assertSame(1, Mouvement::count());

        $this->actingAs($this->admin)->delete("/materiels/{$materiel->id}")
            ->assertRedirect('/materiels')
            ->assertSessionHas('status', 'Matériel PC-ERREUR supprimé.');

        $this->assertNull(Materiel::find($materiel->id));
        $this->assertSame(0, Mouvement::count());
    }

    public function test_un_materiel_deplace_est_refuse(): void
    {
        $materiel = $this->materielCree();
        app(MaterielService::class)->deplacer($materiel, $this->atelier->id, null, null, $this->admin);

        $this->actingAs($this->admin)->delete("/materiels/{$materiel->id}")->assertSessionHasErrors('materiel');

        $this->assertNotNull(Materiel::find($materiel->id));
        $this->assertSame(2, Mouvement::count());
    }

    // --- Droits et affichage --------------------------------------------------------------

    public function test_le_gestionnaire_ne_peut_rien_supprimer(): void
    {
        $gestionnaire = User::factory()->create(['role' => 'gestionnaire_stock']);
        $materiel = $this->materielCree();

        $this->actingAs($gestionnaire)->delete("/sites/{$this->site->id}")->assertForbidden();
        $this->actingAs($gestionnaire)->delete("/emplacements/{$this->atelier->id}")->assertForbidden();
        $this->actingAs($gestionnaire)->delete("/materiels/{$materiel->id}")->assertForbidden();

        $this->actingAs($gestionnaire)->get('/emplacements')->assertOk()->assertDontSee('Supprimer');
        $this->actingAs($gestionnaire)->get("/materiels/{$materiel->id}")->assertOk()->assertDontSee('Supprimer');
    }

    public function test_le_bouton_n_apparait_que_pour_ce_qui_n_a_jamais_servi(): void
    {
        $materiel = $this->materielCree(); // occupe la réserve, l'atelier reste libre
        // L'URL de suppression est aussi le début du lien "Modifier" : on cherche le formulaire lui-même.
        $formulaire = fn (string $url) => 'action="'.$url.'"';

        $this->actingAs($this->admin)->get('/emplacements')
            ->assertOk()
            ->assertSee($formulaire(route('emplacements.destroy', $this->atelier)), false)
            ->assertDontSee($formulaire(route('emplacements.destroy', $this->reserve)), false);

        $this->actingAs($this->admin)->get('/sites')->assertOk()->assertDontSee($formulaire(route('sites.destroy', $this->site)), false);

        $this->actingAs($this->admin)->get("/materiels/{$materiel->id}")->assertSee($formulaire(route('materiels.destroy', $materiel)), false);
        app(MaterielService::class)->deplacer($materiel, $this->atelier->id, null, null, $this->admin);
        $this->actingAs($this->admin)->get("/materiels/{$materiel->id}")->assertDontSee($formulaire(route('materiels.destroy', $materiel)), false);
    }
}
