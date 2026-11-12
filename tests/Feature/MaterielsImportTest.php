<?php

namespace Tests\Feature;

use App\Imports\MaterielsImport;
use App\Models\Emplacement;
use App\Models\Materiel;
use App\Models\Import;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaterielsImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $site = Site::create(['nom' => 'Site 1']);
        Emplacement::create(['site_id' => $site->id, 'nom' => 'Bureau']);
    }

    private function ligne(array $valeurs = []): array
    {
        return array_merge([
            'reference' => 'PC-001', 'type_materiel' => 'PC', 'marque' => 'Dell', 'modele' => 'X',
            'condition_stock' => 'neuf', 'statut' => 'disponible', 'site' => 'Site 1', 'emplacement' => 'Bureau',
        ], $valeurs);
    }

    public function test_une_ligne_valide_est_importee(): void
    {
        $import = new MaterielsImport;
        $import->collection(collect([$this->ligne()]));

        $this->assertSame([], $import->erreurs);
        $this->assertSame(1, Materiel::count());
    }

    public function test_la_reference_constructeur_est_importee_et_la_casse_harmonisee(): void
    {
        Materiel::create($this->ligne(['reference' => 'PC-EXISTANT', 'emplacement_id' => Emplacement::first()->id]));

        $import = new MaterielsImport;
        $import->collection(collect([
            $this->ligne(['reference_constructeur' => ' DL-LAT-5420 ', 'marque' => 'DELL']),
            // Sans colonne reference_constructeur, et "hp" puis "HP" dans le même fichier.
            $this->ligne(['reference' => 'PC-002', 'marque' => 'hp']),
            $this->ligne(['reference' => 'PC-003', 'marque' => 'HP']),
        ]));

        $this->assertSame([], $import->erreurs);
        $this->assertSame('DL-LAT-5420', Materiel::where('reference', 'PC-001')->value('reference_constructeur'));
        $this->assertSame('Dell', Materiel::where('reference', 'PC-001')->value('marque'));
        $this->assertNull(Materiel::where('reference', 'PC-002')->value('reference_constructeur'));
        $this->assertSame(['hp'], Materiel::whereIn('reference', ['PC-002', 'PC-003'])->distinct()->pluck('marque')->all());
    }

    public function test_une_reference_deja_en_base_est_refusee_et_rien_n_est_importe(): void
    {
        Materiel::create($this->ligne(['reference' => '621', 'emplacement_id' => Emplacement::first()->id]));

        $import = new MaterielsImport;
        $import->collection(collect([
            $this->ligne(['reference' => 'PC-NOUVEAU']),
            // Référence numérique, comme Excel la renvoie pour une cellule "621".
            $this->ligne(['reference' => 621]),
        ]));

        $this->assertSame(['Ligne 3 : la référence "621" existe déjà dans l\'inventaire.'], $import->erreurs);
        $this->assertSame(1, Materiel::count());
    }

    public function test_un_numero_de_serie_deja_utilise_par_un_autre_materiel_est_refuse(): void
    {
        Materiel::create($this->ligne(['reference' => 'PC-EXISTANT', 'numero_serie' => 'SN-1', 'emplacement_id' => Emplacement::first()->id]));

        $import = new MaterielsImport;
        $import->collection(collect([$this->ligne(['reference' => 'PC-NOUVEAU', 'numero_serie' => 'SN-1'])]));

        $this->assertCount(1, $import->erreurs);
        $this->assertStringContainsString('déjà utilisé par le matériel "PC-EXISTANT"', $import->erreurs[0]);
    }

    public function test_une_reference_en_double_dans_le_fichier_est_refusee(): void
    {
        $import = new MaterielsImport;
        $import->collection(collect([$this->ligne(), $this->ligne()]));

        $this->assertCount(1, $import->erreurs);
        $this->assertStringContainsString('apparaît plusieurs fois dans le fichier (déjà vue ligne 2)', $import->erreurs[0]);
        $this->assertSame(0, Materiel::count());
    }

    public function test_le_statut_attribue_est_refuse_et_rien_n_est_importe(): void
    {
        $import = new MaterielsImport;
        $import->collection(collect([
            $this->ligne(),
            $this->ligne(['reference' => 'PC-002', 'statut' => 'attribue']),
        ]));

        $this->assertCount(1, $import->erreurs);
        $this->assertStringStartsWith('Ligne 3 : le statut "attribue"', $import->erreurs[0]);
        $this->assertSame(0, Materiel::count());
    }

    // --- Tests "Import Excel" de l'Algorithme §21 et colonnes facultatives (§10.1) ---------

    public function test_une_colonne_obligatoire_manquante_donne_un_seul_message(): void
    {
        $ligne = $this->ligne();
        unset($ligne['site']);

        $import = new MaterielsImport;
        $import->collection(collect([$ligne, ['reference' => 'PC-002'] + $ligne]));

        $this->assertSame(['Le fichier Excel ne contient pas la colonne "site".'], $import->erreurs);
        $this->assertSame(0, Materiel::count());
    }

    public function test_un_site_inconnu_est_refuse(): void
    {
        $import = new MaterielsImport;
        $import->collection(collect([$this->ligne(['site' => 'Site 9'])]));

        $this->assertSame(['Ligne 2 : le site "Site 9" n\'existe pas.'], $import->erreurs);
    }

    public function test_une_cellule_vide_est_signalee_et_les_lignes_vides_sont_ignorees(): void
    {
        $import = new MaterielsImport;
        $import->collection(collect([$this->ligne(['marque' => '  '])]));
        $this->assertSame(['Ligne 2 : la cellule "marque" est vide.'], $import->erreurs);

        // Ligne entièrement vide en fin de fichier : ignorée, pas comptée.
        $vide = array_fill_keys(array_keys($this->ligne()), null);
        $import = new MaterielsImport;
        $import->collection(collect([$this->ligne(), $vide]));
        $this->assertSame([], $import->erreurs);
        $this->assertSame(1, $import->nombreLignes);
    }

    public function test_les_caracteres_accentues_sont_conserves(): void
    {
        Emplacement::create(['site_id' => Site::first()->id, 'nom' => 'Réserve informatique - Étagère 2']);

        $import = new MaterielsImport;
        $import->collection(collect([$this->ligne([
            'type_materiel' => 'Écran', 'emplacement' => 'Réserve informatique - Étagère 2',
            'commentaire' => 'Démarre une fois sur deux, câble à changer',
        ])]));

        $this->assertSame([], $import->erreurs);
        $materiel = Materiel::with('emplacement')->sole();
        $this->assertSame('Écran', $materiel->type_materiel);
        $this->assertSame('Réserve informatique - Étagère 2', $materiel->emplacement->nom);
        $this->assertSame('Démarre une fois sur deux, câble à changer', $materiel->commentaire);
    }

    public function test_les_libelles_affiches_sont_acceptes_pour_le_statut_et_le_stock(): void
    {
        $import = new MaterielsImport;
        $import->collection(collect([$this->ligne(['statut' => 'À vérifier', 'condition_stock' => 'Reconditionné'])]));

        $this->assertSame([], $import->erreurs);
        $this->assertSame('a_verifier', Materiel::sole()->statut);
        $this->assertSame('reconditionne', Materiel::sole()->condition_stock);
    }

    public function test_la_colonne_utilisateur_attribue_le_materiel_par_email(): void
    {
        $jean = User::factory()->create(['email' => 'jean.dupont@exemple.fr']);

        $import = new MaterielsImport;
        $import->collection(collect([$this->ligne(['statut' => 'attribue', 'utilisateur' => 'Jean.Dupont@exemple.fr'])]));

        $this->assertSame([], $import->erreurs);
        $this->assertSame($jean->id, Materiel::sole()->utilisateur_id);
    }

    public function test_un_utilisateur_inconnu_desactive_ou_hors_attribution_est_refuse(): void
    {
        User::factory()->create(['email' => 'parti@exemple.fr', 'actif' => false]);
        User::factory()->create(['email' => 'jean@exemple.fr']);

        $import = new MaterielsImport;
        $import->collection(collect([
            $this->ligne(['reference' => 'PC-1', 'statut' => 'attribue', 'utilisateur' => 'inconnu@exemple.fr']),
            $this->ligne(['reference' => 'PC-2', 'statut' => 'attribue', 'utilisateur' => 'parti@exemple.fr']),
            $this->ligne(['reference' => 'PC-3', 'statut' => 'disponible', 'utilisateur' => 'jean@exemple.fr']),
        ]));

        $this->assertSame([
            'Ligne 2 : aucun compte utilisateur avec l\'email "inconnu@exemple.fr".',
            'Ligne 3 : le compte "parti@exemple.fr" est désactivé.',
            'Ligne 4 : un utilisateur n\'est accepté que pour un matériel au statut "attribue".',
        ], $import->erreurs);
        $this->assertSame(0, Materiel::count());
    }

    public function test_les_dates_sont_lues_en_francais_en_iso_et_au_format_excel(): void
    {
        $import = new MaterielsImport;
        $import->collection(collect([
            $this->ligne(['reference' => 'PC-1', 'date_acquisition' => '15/03/2025', 'date_fin_garantie' => '2028-03-15']),
            // 45731 = 15/03/2025 pour une cellule Excel au format date.
            $this->ligne(['reference' => 'PC-2', 'date_acquisition' => 45731]),
        ]));

        $this->assertSame([], $import->erreurs);
        $this->assertSame('2025-03-15', Materiel::where('reference', 'PC-1')->sole()->date_acquisition->toDateString());
        $this->assertSame('2028-03-15', Materiel::where('reference', 'PC-1')->sole()->date_fin_garantie->toDateString());
        $this->assertSame('2025-03-15', Materiel::where('reference', 'PC-2')->sole()->date_acquisition->toDateString());
    }

    public function test_une_date_impossible_est_refusee(): void
    {
        $import = new MaterielsImport;
        $import->collection(collect([$this->ligne(['date_acquisition' => '31/02/2025'])]));

        $this->assertCount(1, $import->erreurs);
        $this->assertStringContainsString('la date "31/02/2025" (date_acquisition) n\'est pas reconnue', $import->erreurs[0]);
    }

    public function test_le_resume_compte_les_doublons(): void
    {
        $import = new MaterielsImport;
        $import->collection(collect([
            $this->ligne(['numero_serie' => 'SN-1']),
            $this->ligne(['numero_serie' => 'SN-1']),
            $this->ligne(['reference' => 'PC-2', 'statut' => 'inconnu']),
        ]));

        $this->assertSame(3, $import->nombreLignes);
        $this->assertSame(2, $import->nombreDoublons);
        $this->assertCount(3, $import->erreurs);
    }

    public function test_import_complet_par_le_site_avec_journal_et_resume(): void
    {
        $gestionnaire = User::factory()->create(['role' => 'gestionnaire_stock']);
        $csv = "reference,type_materiel,marque,modele,condition_stock,statut,site,emplacement\n"
            ."PC-001,PC,Dell,X,neuf,disponible,Site 1,Bureau\n"
            ."PC-002,PC,Dell,X,neuf,disponible,Site 1,Bureau\n";

        $this->actingAs($gestionnaire)
            ->post('/imports', ['fichier' => UploadedFile::fake()->createWithContent('inventaire.csv', $csv)])
            ->assertSessionHas('status', '2 matériel(s) importé(s) avec succès sur 2 ligne(s) lue(s).');

        $journal = Import::sole();
        $this->assertSame('termine', $journal->statut);
        $this->assertSame(2, $journal->nombre_importees);
        $this->assertSame($gestionnaire->id, $journal->utilisateur_id);
    }
}
