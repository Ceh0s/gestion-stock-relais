<?php

namespace Tests\Feature;

use App\Models\Emplacement;
use App\Models\Materiel;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests "Matériel" demandés par Algorithme.pdf §21, plus la référence constructeur
 * et l'harmonisation des libellés (cahier des charges §1 et §7).
 */
class MaterielTest extends TestCase
{
    use RefreshDatabase;

    private User $gestionnaire;

    private Emplacement $emplacement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gestionnaire = User::factory()->create(['role' => 'gestionnaire_stock']);
        $site = Site::create(['nom' => 'Site 1']);
        $this->emplacement = Emplacement::create(['site_id' => $site->id, 'nom' => 'Réserve']);
    }

    private function donnees(array $valeurs = []): array
    {
        return array_merge([
            'reference' => 'pc-000245', 'type_materiel' => 'Ordinateur portable', 'marque' => 'Dell',
            'modele' => 'Latitude 5420', 'numero_serie' => 'ABC123456', 'reference_constructeur' => 'DL-LAT-5420',
            'condition_stock' => 'neuf', 'statut' => 'disponible', 'emplacement_id' => $this->emplacement->id,
        ], $valeurs);
    }

    private function materielExistant(array $valeurs = []): Materiel
    {
        return Materiel::create(array_merge([
            'reference' => 'PC-EXISTANT', 'type_materiel' => 'Ordinateur portable', 'marque' => 'Dell',
            'modele' => 'Latitude 5420', 'condition_stock' => 'neuf', 'statut' => 'disponible',
            'emplacement_id' => $this->emplacement->id,
        ], $valeurs));
    }

    public function test_creer_un_materiel_valide(): void
    {
        $this->actingAs($this->gestionnaire)->post('/materiels', $this->donnees())
            ->assertSessionHasNoErrors();

        $materiel = Materiel::sole();
        $this->assertSame('PC-000245', $materiel->reference);
        $this->assertSame('DL-LAT-5420', $materiel->reference_constructeur);
    }

    public function test_refuser_une_reference_vide(): void
    {
        $this->actingAs($this->gestionnaire)->post('/materiels', $this->donnees(['reference' => '']))
            ->assertSessionHasErrors('reference');

        $this->assertSame(0, Materiel::count());
    }

    public function test_refuser_une_reference_en_double(): void
    {
        $this->materielExistant(['reference' => 'PC-000245']);

        $this->actingAs($this->gestionnaire)->post('/materiels', $this->donnees())
            ->assertSessionHasErrors('reference');

        $this->assertSame(1, Materiel::count());
    }

    public function test_refuser_un_numero_de_serie_en_double(): void
    {
        $this->materielExistant(['numero_serie' => 'ABC123456']);

        $this->actingAs($this->gestionnaire)->post('/materiels', $this->donnees())
            ->assertSessionHasErrors('numero_serie');
    }

    public function test_refuser_un_numero_de_serie_en_double_a_la_modification(): void
    {
        $this->materielExistant(['numero_serie' => 'ABC123456']);
        $autre = $this->materielExistant(['reference' => 'PC-AUTRE']);

        $this->actingAs($this->gestionnaire)
            ->put("/materiels/{$autre->id}", $this->donnees(['numero_serie' => 'ABC123456']))
            ->assertSessionHasErrors('numero_serie');
    }

    public function test_refuser_un_statut_inconnu(): void
    {
        $this->actingAs($this->gestionnaire)->post('/materiels', $this->donnees(['statut' => 'casse']))
            ->assertSessionHasErrors('statut');
    }

    public function test_la_casse_est_alignee_sur_les_valeurs_existantes(): void
    {
        $this->materielExistant();

        $this->actingAs($this->gestionnaire)->post('/materiels', $this->donnees([
            'marque' => ' dell ', 'modele' => 'LATITUDE 5420', 'type_materiel' => 'ordinateur portable',
        ]))->assertSessionHasNoErrors();

        $nouveau = Materiel::where('reference', 'PC-000245')->sole();
        $this->assertSame('Dell', $nouveau->marque);
        $this->assertSame('Latitude 5420', $nouveau->modele);
        $this->assertSame('Ordinateur portable', $nouveau->type_materiel);
    }

    public function test_les_suggestions_apparaissent_dans_le_formulaire(): void
    {
        $this->materielExistant(['marque' => 'Lenovo']);

        $this->actingAs($this->gestionnaire)->get('/materiels/creer')
            ->assertOk()
            ->assertSee('<datalist id="suggestions-marque">', false)
            ->assertSee('<option value="Lenovo"></option>', false);
    }

    public function test_la_recherche_trouve_la_reference_constructeur(): void
    {
        $this->materielExistant(['reference_constructeur' => 'DL-LAT-5420']);

        $this->actingAs($this->gestionnaire)->get('/materiels?terme=DL-LAT')
            ->assertOk()
            ->assertSee('PC-EXISTANT');
    }
}
