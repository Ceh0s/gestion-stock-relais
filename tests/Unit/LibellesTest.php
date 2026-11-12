<?php

namespace Tests\Unit;

use App\Http\Controllers\UserController;
use App\Models\Materiel;
use App\Models\User;
use PHPUnit\Framework\TestCase;

class LibellesTest extends TestCase
{
    public function test_chaque_statut_a_un_libelle(): void
    {
        $this->assertEqualsCanonicalizing(Materiel::STATUTS, array_keys(Materiel::LIBELLES_STATUTS));
    }

    public function test_chaque_condition_a_un_libelle(): void
    {
        $this->assertEqualsCanonicalizing(Materiel::CONDITIONS, array_keys(Materiel::LIBELLES_CONDITIONS));
    }

    public function test_chaque_role_a_un_libelle(): void
    {
        $this->assertEqualsCanonicalizing(UserController::ROLES, array_keys(User::LIBELLES_ROLES));
    }

    public function test_une_valeur_inconnue_est_affichee_telle_quelle(): void
    {
        $this->assertSame('Attribué', Materiel::libelleStatut('attribue'));
        $this->assertSame('valeur_inconnue', Materiel::libelleStatut('valeur_inconnue'));
        $this->assertNull(Materiel::libelleStatut(null));
    }
}
