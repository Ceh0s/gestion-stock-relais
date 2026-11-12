<?php

namespace Tests\Feature;

use App\Imports\MaterielsImport;
use App\Models\Emplacement;
use App\Models\Materiel;
use App\Models\Site;
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
}
