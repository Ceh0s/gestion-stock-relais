<?php

namespace Tests\Feature;

use App\Imports\MaterielsImport;
use App\Models\Emplacement;
use App\Models\Materiel;
use App\Models\Mouvement;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Historique des mouvements : cahier des charges §4 et §11, Algorithme §2.3, §4, §6, §10.2, §17 et tests §21.
 */
class HistoriqueTest extends TestCase
{
    use RefreshDatabase;

    private User $gestionnaire;

    private Emplacement $reserve;

    private Emplacement $atelier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gestionnaire = User::factory()->create(['role' => 'gestionnaire_stock']);
        $site = Site::create(['nom' => 'Site 1']);
        $this->reserve = Emplacement::create(['site_id' => $site->id, 'nom' => 'Réserve']);
        $this->atelier = Emplacement::create(['site_id' => $site->id, 'nom' => 'Atelier']);
    }

    private function materiel(array $valeurs = []): Materiel
    {
        return Materiel::create(array_merge([
            'reference' => 'PC-000245', 'type_materiel' => 'Ordinateur portable', 'marque' => 'HP',
            'modele' => 'EliteBook', 'numero_serie' => 'ABC123', 'condition_stock' => 'neuf',
            'statut' => 'disponible', 'emplacement_id' => $this->reserve->id, 'date_acquisition' => '2025-03-15',
        ], $valeurs));
    }

    /** Données complètes du formulaire "Modifier la fiche", reprises de la fiche actuelle. */
    private function formulaireModification(Materiel $materiel, array $valeurs = []): array
    {
        // Relu depuis la base, comme le fait la page "Modifier" (sinon version vaut null en mémoire).
        $materiel = $materiel->fresh();

        return array_merge([
            'type_materiel' => $materiel->type_materiel, 'marque' => $materiel->marque, 'modele' => $materiel->modele,
            'numero_serie' => $materiel->numero_serie, 'reference_constructeur' => $materiel->reference_constructeur,
            'condition_stock' => $materiel->condition_stock,
            'date_acquisition' => $materiel->date_acquisition?->toDateString(),
            'date_fin_garantie' => $materiel->date_fin_garantie?->toDateString(),
            'commentaire' => $materiel->commentaire, 'version' => $materiel->version,
        ], $valeurs);
    }

    // --- Création -------------------------------------------------------------------------

    public function test_creer_un_materiel_ecrit_un_evenement_de_creation(): void
    {
        $this->actingAs($this->gestionnaire)->post('/materiels', [
            'reference' => 'PC-NOUVEAU', 'type_materiel' => 'Écran', 'marque' => 'HP', 'modele' => 'E243',
            'condition_stock' => 'neuf', 'statut' => 'disponible', 'emplacement_id' => $this->reserve->id,
        ])->assertSessionHasNoErrors();

        $mouvement = Mouvement::sole();
        $this->assertSame('creation', $mouvement->motif);
        $this->assertSame($this->gestionnaire->id, $mouvement->user_id);
        $this->assertNull($mouvement->ancien_emplacement_id);
        $this->assertSame($this->reserve->id, $mouvement->nouvel_emplacement_id);
        $this->assertSame('disponible', $mouvement->nouveau_statut);
    }

    public function test_l_import_ecrit_un_evenement_de_creation_par_materiel(): void
    {
        $ligne = ['type_materiel' => 'PC', 'marque' => 'Dell', 'modele' => 'X', 'condition_stock' => 'neuf',
            'statut' => 'disponible', 'site' => 'Site 1', 'emplacement' => 'Réserve'];

        $import = new MaterielsImport($this->gestionnaire, 'inventaire.xlsx');
        $import->collection(collect([['reference' => 'PC-1', ...$ligne], ['reference' => 'PC-2', ...$ligne]]));

        $this->assertSame(2, Mouvement::where('motif', 'creation')->count());
        $this->assertSame(['Import du fichier inventaire.xlsx'], Mouvement::distinct()->pluck('commentaire')->all());
        $this->assertSame([$this->gestionnaire->id], Mouvement::distinct()->pluck('user_id')->all());
    }

    public function test_un_import_rejete_n_ecrit_aucun_mouvement(): void
    {
        $import = new MaterielsImport($this->gestionnaire, 'faux.xlsx');
        $import->collection(collect([['reference' => 'PC-1', 'statut' => 'inconnu']]));

        $this->assertNotEmpty($import->erreurs);
        $this->assertSame(0, Mouvement::count());
    }

    // --- Modification de la fiche ---------------------------------------------------------

    public function test_une_modification_enregistre_l_ancienne_et_la_nouvelle_valeur(): void
    {
        $materiel = $this->materiel();

        $this->actingAs($this->gestionnaire)
            ->put("/materiels/{$materiel->id}", $this->formulaireModification($materiel, [
                'marque' => 'Dell', 'numero_serie' => '', 'date_acquisition' => '2025-04-01',
            ]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Matériel mis à jour.');

        $mouvement = Mouvement::sole();
        $this->assertSame('modification', $mouvement->motif);
        $this->assertSame($this->gestionnaire->id, $mouvement->user_id);
        $this->assertSame([
            'marque' => ['HP', 'Dell'],
            'numero_serie' => ['ABC123', null],
            'date_acquisition' => ['2025-03-15', '2025-04-01'],
        ], $mouvement->modifications);

        $materiel->refresh();
        $this->assertSame('Dell', $materiel->marque);
        $this->assertNull($materiel->numero_serie);
        $this->assertSame(1, $materiel->version);
    }

    public function test_une_modification_sans_changement_n_ecrit_rien(): void
    {
        $materiel = $this->materiel();

        $this->actingAs($this->gestionnaire)
            ->put("/materiels/{$materiel->id}", $this->formulaireModification($materiel))
            ->assertSessionHas('status', 'Aucune modification.');

        $this->assertSame(0, Mouvement::count());
        $this->assertSame(0, $materiel->fresh()->version);
    }

    public function test_une_modification_sur_une_version_perimee_est_refusee(): void
    {
        $materiel = $this->materiel();
        $formulaire = $this->formulaireModification($materiel, ['marque' => 'Dell']);

        // Un autre utilisateur déplace le matériel entre l'ouverture et l'envoi du formulaire.
        $materiel->update(['version' => 1]);

        $this->actingAs($this->gestionnaire)
            ->put("/materiels/{$materiel->id}", $formulaire)
            ->assertSessionHasErrors(['version' => 'Le matériel a été modifié par un autre utilisateur. Rechargez la fiche avant de recommencer.']);

        $this->assertSame('HP', $materiel->fresh()->marque);
        $this->assertSame(0, Mouvement::count());
    }

    // --- Déplacement (Algorithme §21) -----------------------------------------------------

    public function test_deplacer_vers_un_emplacement_valide_cree_un_mouvement_et_conserve_l_ancien_emplacement(): void
    {
        $materiel = $this->materiel();

        $this->actingAs($this->gestionnaire)
            ->post("/materiels/{$materiel->id}/deplacer", [
                'emplacement_id' => $this->atelier->id, 'motif' => 'Rééquilibrage du stock', 'version' => 0,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($this->atelier->id, $materiel->fresh()->emplacement_id);
        $mouvement = Mouvement::sole();
        $this->assertSame($this->reserve->id, $mouvement->ancien_emplacement_id);
        $this->assertSame($this->atelier->id, $mouvement->nouvel_emplacement_id);
        $this->assertSame('Rééquilibrage du stock', $mouvement->motif);
    }

    public function test_refuser_un_emplacement_inexistant(): void
    {
        $materiel = $this->materiel();

        $this->actingAs($this->gestionnaire)
            ->post("/materiels/{$materiel->id}/deplacer", ['emplacement_id' => 999])
            ->assertSessionHasErrors('emplacement_id');

        $this->assertSame(0, Mouvement::count());
    }

    public function test_refuser_le_meme_emplacement(): void
    {
        $materiel = $this->materiel();

        $this->actingAs($this->gestionnaire)
            ->post("/materiels/{$materiel->id}/deplacer", ['emplacement_id' => $this->reserve->id])
            ->assertSessionHasErrors(['emplacement_id' => 'Le matériel est déjà à cet emplacement.']);

        $this->assertSame(0, Mouvement::count());
    }

    public function test_un_deplacement_sur_une_version_perimee_est_refuse(): void
    {
        $materiel = $this->materiel(['version' => 3]);

        $this->actingAs($this->gestionnaire)
            ->post("/materiels/{$materiel->id}/deplacer", ['emplacement_id' => $this->atelier->id, 'version' => 2])
            ->assertSessionHasErrors('version');

        $this->assertSame($this->reserve->id, $materiel->fresh()->emplacement_id);
    }

    // --- Affichage ------------------------------------------------------------------------

    public function test_l_historique_affiche_le_plus_recent_d_abord_meme_dans_la_meme_seconde(): void
    {
        $this->freezeSecond();
        $materiel = $this->materiel();
        foreach (['creation', 'modification', 'attribution'] as $motif) {
            Mouvement::create(['materiel_id' => $materiel->id, 'motif' => $motif]);
        }

        $this->assertSame(['attribution', 'modification', 'creation'], $materiel->mouvements()->pluck('motif')->all());
    }

    public function test_la_fiche_affiche_le_detail_des_modifications_et_le_commentaire(): void
    {
        $materiel = $this->materiel();
        Mouvement::create([
            'materiel_id' => $materiel->id, 'user_id' => $this->gestionnaire->id, 'motif' => 'modification',
            'modifications' => ['marque' => ['HP', 'Dell'], 'date_acquisition' => [null, '2025-04-01']],
        ]);
        Mouvement::create([
            'materiel_id' => $materiel->id, 'user_id' => $this->gestionnaire->id, 'motif' => 'mise_en_reparation',
            'commentaire' => 'Écran cassé',
        ]);

        $this->actingAs($this->gestionnaire)->get("/materiels/{$materiel->id}")
            ->assertOk()
            ->assertSee('Modification de la fiche')
            ->assertSee('Marque :')
            ->assertSee('HP &rarr; Dell', false)
            ->assertSee('— &rarr; 01/04/2025', false)
            ->assertSee('Écran cassé')
            ->assertSee('name="version" value="0"', false);
    }
}
