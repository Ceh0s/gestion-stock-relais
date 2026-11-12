<?php

namespace Tests\Feature;

use App\Models\Emplacement;
use App\Models\Materiel;
use App\Models\Mouvement;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Retour en stock : Algorithme §8 (matériel attribué) et §18 (retour de réparation par le technicien).
 */
class RetourStockTest extends TestCase
{
    use RefreshDatabase;

    private User $gestionnaire;

    private User $jean;

    private Emplacement $bureau;

    private Emplacement $armoireB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gestionnaire = User::factory()->create(['role' => 'gestionnaire_stock']);
        $this->jean = User::factory()->create(['prenom' => 'Jean', 'nom' => 'Dupont']);
        $site = Site::create(['nom' => 'Site 1']);
        $this->bureau = Emplacement::create(['site_id' => $site->id, 'nom' => 'Bureau']);
        $this->armoireB = Emplacement::create(['site_id' => $site->id, 'nom' => 'Réserve / Armoire B']);
    }

    private function materiel(array $valeurs = []): Materiel
    {
        return Materiel::create(array_merge([
            'reference' => 'PC-000312', 'type_materiel' => 'Ordinateur portable', 'marque' => 'Dell',
            'modele' => 'Latitude 5420', 'condition_stock' => 'occasion', 'statut' => 'attribue',
            'emplacement_id' => $this->bureau->id, 'utilisateur_id' => $this->jean->id,
        ], $valeurs))->fresh();
    }

    private function retourner(Materiel $materiel, string $statut = 'disponible')
    {
        return $this->actingAs($this->gestionnaire)->post("/materiels/{$materiel->id}/retour-stock", [
            'emplacement_id' => $this->armoireB->id, 'statut' => $statut, 'version' => $materiel->version,
        ]);
    }

    public function test_un_materiel_attribue_revient_en_stock(): void
    {
        // Exemple de l'Algorithme §8 : attribué à Jean Dupont → disponible, Site 1 / Réserve / Armoire B.
        $materiel = $this->materiel();

        $this->retourner($materiel)->assertSessionHasNoErrors();

        $materiel->refresh();
        $this->assertSame('disponible', $materiel->statut);
        $this->assertNull($materiel->utilisateur_id);
        $this->assertSame($this->armoireB->id, $materiel->emplacement_id);

        $mouvement = Mouvement::sole();
        $this->assertSame('retour_en_stock', $mouvement->motif);
        $this->assertSame('attribue', $mouvement->ancien_statut);
        $this->assertSame($this->jean->id, $mouvement->ancien_utilisateur_id);
        $this->assertSame($this->bureau->id, $mouvement->ancien_emplacement_id);
    }

    public function test_un_materiel_en_reparation_revient_disponible(): void
    {
        $materiel = $this->materiel(['statut' => 'reparation', 'utilisateur_id' => null]);

        $this->retourner($materiel)->assertSessionHasNoErrors();

        $this->assertSame('disponible', $materiel->fresh()->statut);
    }

    public function test_un_materiel_disponible_ne_peut_pas_etre_retourne(): void
    {
        $materiel = $this->materiel(['statut' => 'disponible', 'utilisateur_id' => null]);

        $this->retourner($materiel)->assertSessionHasErrors([
            'statut' => 'Seul un matériel attribué ou en réparation peut être retourné en stock (statut actuel : Disponible).',
        ]);

        $this->assertSame(0, Mouvement::count());
        $this->assertSame($materiel->version, $materiel->fresh()->version);
        $this->assertSame($this->bureau->id, $materiel->fresh()->emplacement_id);
    }

    public function test_un_materiel_en_reparation_ne_peut_pas_repartir_en_reparation(): void
    {
        $materiel = $this->materiel(['statut' => 'reparation', 'utilisateur_id' => null]);

        $this->retourner($materiel, 'reparation')->assertSessionHasErrors(['statut' => 'Le matériel est déjà en réparation.']);

        $this->assertSame(0, Mouvement::count());
    }

    public function test_le_formulaire_n_apparait_que_pour_un_materiel_attribue_ou_en_reparation(): void
    {
        $disponible = $this->materiel(['reference' => 'PC-DISPO', 'statut' => 'disponible', 'utilisateur_id' => null]);
        $this->actingAs($this->gestionnaire)->get("/materiels/{$disponible->id}")->assertOk()->assertDontSee('Retour en stock');

        $attribue = $this->materiel();
        $this->actingAs($this->gestionnaire)->get("/materiels/{$attribue->id}")->assertOk()->assertSee('Retour en stock');

        $enReparation = $this->materiel(['reference' => 'PC-REPA', 'statut' => 'reparation', 'utilisateur_id' => null]);
        $this->actingAs($this->gestionnaire)->get("/materiels/{$enReparation->id}")
            ->assertOk()
            ->assertSee('Retour en stock')
            ->assertDontSee('<option value="reparation">', false);
    }
}
