<?php

namespace Tests\Feature;

use App\Models\Sauvegarde;
use App\Models\User;
use App\Notifications\SauvegardeEchouee;
use App\Services\SauvegardeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Sauvegardes de la base (Algorithme §16, tests §21 « Créer une sauvegarde »).
 * Les tests tournent sur SQLite : mysqldump est simulé avec Process::fake(). La restauration
 * réelle (sauvegarde:verifier) se vérifie sur MariaDB, voir le README.
 */
class SauvegardeTest extends TestCase
{
    use RefreshDatabase;

    private const DUMP = "-- MariaDB dump\nCREATE TABLE `materiels` (`id` int);\nINSERT INTO `materiels` VALUES (1);\n";

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Notification::fake();

        config([
            'sauvegarde.connexion' => 'mysql',
            'database.connections.mysql.database' => 'gestion_stock',
            'database.connections.mysql.username' => 'laravel',
            'database.connections.mysql.password' => 'mot-de-passe-secret',
            'database.connections.mysql.unix_socket' => '',
            'sauvegarde.disque_externe' => null,
        ]);

        $this->admin = User::factory()->create(['role' => 'administrateur']);
    }

    /** Simule un mysqldump qui réussit : il écrit le dump dans le fichier de --result-file. */
    private function mysqldumpReussi(): void
    {
        Process::fake(function (PendingProcess $process) {
            foreach ($process->command as $argument) {
                if (str_starts_with($argument, '--result-file=')) {
                    file_put_contents(substr($argument, strlen('--result-file=')), self::DUMP);
                }
            }

            return Process::result();
        });
    }

    private function mysqldumpEnEchec(): void
    {
        Process::fake(fn () => Process::result(
            errorOutput: "mysqldump: Got error: 1045: \"Access denied for user 'laravel'@'localhost'\"",
            exitCode: 2,
        ));
    }

    public function test_creer_une_sauvegarde(): void
    {
        $this->mysqldumpReussi();

        $sauvegarde = app(SauvegardeService::class)->creer($this->admin);

        $this->assertTrue($sauvegarde->estReussie());
        Storage::disk('local')->assertExists($sauvegarde->chemin());
        $chemin = Storage::disk('local')->path($sauvegarde->chemin());

        // Le fichier est un gzip qui redonne exactement le dump.
        $this->assertSame(self::DUMP, gzdecode(file_get_contents($chemin)));
        $this->assertSame(hash_file('sha256', $chemin), $sauvegarde->empreinte);
        $this->assertSame(filesize($chemin), $sauvegarde->taille);
        $this->assertSame(1, $sauvegarde->nombre_utilisateurs);
        $this->assertSame(0, $sauvegarde->nombre_materiels);
        $this->assertSame($this->admin->id, $sauvegarde->utilisateur_id);

        // Le .sql temporaire non compressé ne reste pas sur le disque.
        $this->assertSame([$sauvegarde->chemin()], Storage::disk('local')->allFiles('sauvegardes'));
        Notification::assertNothingSent();
    }

    public function test_le_mot_de_passe_ne_passe_pas_par_la_ligne_de_commande(): void
    {
        $this->mysqldumpReussi();

        app(SauvegardeService::class)->creer();

        Process::assertRan(function (PendingProcess $process) {
            return $process->command[0] === 'mysqldump'
                && in_array('gestion_stock', $process->command, true)
                && in_array('--single-transaction', $process->command, true)
                && ! str_contains(implode(' ', $process->command), 'mot-de-passe-secret')
                && $process->environment['MYSQL_PWD'] === 'mot-de-passe-secret';
        });
    }

    public function test_un_echec_est_journalise_et_signale_aux_administrateurs(): void
    {
        $this->mysqldumpEnEchec();
        $adminInactif = User::factory()->create(['role' => 'administrateur', 'actif' => false]);
        $gestionnaire = User::factory()->create(['role' => 'gestionnaire_stock']);

        $sauvegarde = app(SauvegardeService::class)->creer();

        $this->assertSame('echec', $sauvegarde->statut);
        $this->assertNull($sauvegarde->utilisateur_id);
        // Message compréhensible, sans la sortie brute de mysqldump (Algorithme §17).
        $this->assertStringContainsString('refusé l\'accès', $sauvegarde->message);
        $this->assertStringNotContainsString('1045', $sauvegarde->message);
        $this->assertSame([], Storage::disk('local')->allFiles('sauvegardes'));

        Notification::assertSentTo($this->admin, SauvegardeEchouee::class);
        Notification::assertNotSentTo([$adminInactif, $gestionnaire], SauvegardeEchouee::class);

        $this->actingAs($this->admin)->get('/dashboard')->assertSee('La dernière sauvegarde de la base a échoué.');
        $this->actingAs($gestionnaire)->get('/dashboard')->assertDontSee('La dernière sauvegarde de la base a échoué.');
    }

    public function test_les_sauvegardes_trop_anciennes_sont_supprimees(): void
    {
        $this->mysqldumpReussi();
        config(['sauvegarde.conservation_jours' => 30]);
        $service = app(SauvegardeService::class);

        $this->travelTo(now()->subDays(40));
        $ancienne = $service->creer();
        $this->travelBack();
        $this->travelTo(now()->subDays(10));
        $recente = $service->creer();
        $this->travelBack();

        $nouvelle = $service->creer();

        $this->assertModelMissing($ancienne);
        Storage::disk('local')->assertMissing($ancienne->chemin());
        $this->assertModelExists($recente);
        Storage::disk('local')->assertExists($recente->chemin());
        $this->assertModelExists($nouvelle);
    }

    public function test_la_derniere_sauvegarde_reussie_est_gardee_meme_ancienne(): void
    {
        config(['sauvegarde.conservation_jours' => 30]);
        $service = app(SauvegardeService::class);

        $this->mysqldumpReussi();
        $this->travelTo(now()->subDays(60));
        $seuleReussie = $service->creer();
        $this->travelBack();

        // Les sauvegardes suivantes échouent : on ne doit pas perdre la seule copie valide.
        $this->mysqldumpEnEchec();
        $service->creer();

        $this->assertModelExists($seuleReussie);
        Storage::disk('local')->assertExists($seuleReussie->chemin());
    }

    public function test_la_sauvegarde_est_copiee_vers_le_disque_externe(): void
    {
        $this->mysqldumpReussi();
        Storage::fake('externe');
        config(['sauvegarde.disque_externe' => 'externe']);

        $sauvegarde = app(SauvegardeService::class)->creer();

        $this->assertTrue($sauvegarde->copie_externe);
        Storage::disk('externe')->assertExists($sauvegarde->nom_fichier);
        $this->assertSame(self::DUMP, gzdecode(Storage::disk('externe')->get($sauvegarde->nom_fichier)));
    }

    public function test_l_administrateur_lance_et_telecharge_une_sauvegarde(): void
    {
        $this->mysqldumpReussi();

        $this->actingAs($this->admin)->get('/sauvegardes')->assertOk()->assertSee('Aucune sauvegarde.');
        $this->actingAs($this->admin)->post('/sauvegardes')
            ->assertRedirect(route('sauvegardes.index'))
            ->assertSessionHas('status');

        $sauvegarde = Sauvegarde::sole();
        $this->actingAs($this->admin)->get('/sauvegardes')->assertSee($this->admin->nomComplet());

        $reponse = $this->actingAs($this->admin)->get(route('sauvegardes.download', $sauvegarde));
        $reponse->assertOk()->assertDownload($sauvegarde->nom_fichier);
    }

    public function test_un_echec_lance_depuis_la_page_affiche_un_message_lisible(): void
    {
        $this->mysqldumpEnEchec();

        $this->actingAs($this->admin)->post('/sauvegardes')
            ->assertRedirect(route('sauvegardes.index'))
            ->assertSessionHasErrors('sauvegarde');

        // Une sauvegarde en échec n'a pas de fichier à télécharger.
        $this->actingAs($this->admin)->get(route('sauvegardes.download', Sauvegarde::sole()))
            ->assertRedirect(route('sauvegardes.index'));
    }

    public function test_seul_l_administrateur_accede_aux_sauvegardes(): void
    {
        $this->mysqldumpReussi();
        $sauvegarde = app(SauvegardeService::class)->creer();

        foreach (['gestionnaire_stock', 'technicien', 'consultation', 'auditeur'] as $role) {
            $utilisateur = User::factory()->create(['role' => $role]);

            $this->actingAs($utilisateur)->get('/sauvegardes')->assertForbidden();
            $this->actingAs($utilisateur)->post('/sauvegardes')->assertForbidden();
            $this->actingAs($utilisateur)->get(route('sauvegardes.download', $sauvegarde))->assertForbidden();
        }

        $this->assertSame(1, Sauvegarde::count());
    }

    public function test_la_commande_renvoie_une_erreur_en_cas_d_echec(): void
    {
        $this->mysqldumpEnEchec();
        $this->artisan('sauvegarde:creer')->assertFailed();

        $this->mysqldumpReussi();
        $this->artisan('sauvegarde:creer')->assertSuccessful();
    }

    public function test_la_sauvegarde_automatique_est_planifiee_chaque_nuit(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('sauvegarde:creer')->assertSuccessful();
    }
}
