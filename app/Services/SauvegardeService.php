<?php

namespace App\Services;

use App\Models\Materiel;
use App\Models\Mouvement;
use App\Models\Sauvegarde;
use App\Models\User;
use App\Notifications\SauvegardeEchouee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Sauvegarde et vérification de restauration de la base (Algorithme §16, tests §21).
 *
 * Le dump est fait par mysqldump (client MySQL/MariaDB) : il doit être installé sur le serveur.
 * Les fichiers vont dans storage/app/private/sauvegardes/, qui n'est jamais servi par le web.
 */
class SauvegardeService
{
    /**
     * Crée une sauvegarde complète. Ne lève jamais d'exception : un échec est enregistré
     * dans le journal, détaillé dans les logs et signalé aux administrateurs.
     *
     * @param  User|null  $par  null pour une sauvegarde automatique (tâche planifiée)
     */
    public function creer(?User $par = null): Sauvegarde
    {
        $disque = Storage::disk('local');
        $dossier = config('sauvegarde.dossier');
        // Les secondes évitent qu'une sauvegarde manuelle écrase l'automatique de la même minute.
        $nom = 'gestion_stock_'.now()->format('Y-m-d_H-i-s').'.sql.gz';
        $temporaire = $disque->path($dossier.'/'.$nom.'.tmp.sql');
        $final = $disque->path($dossier.'/'.$nom);

        $journal = [
            'nom_fichier' => $nom,
            'utilisateur_id' => $par?->id,
        ];

        try {
            // 1. Créer le dossier de sauvegarde.
            $disque->makeDirectory($dossier);

            // Effectifs au moment du dump, relus par sauvegarde:verifier après restauration.
            $journal += [
                'nombre_materiels' => Materiel::count(),
                'nombre_mouvements' => Mouvement::count(),
                'nombre_utilisateurs' => User::count(),
            ];

            // 2. Exécuter mysqldump.
            $this->dumper($temporaire);

            // 3. Compresser le fichier.
            $this->compresser($temporaire, $final);

            // 4. Calculer son empreinte.
            $journal['taille'] = filesize($final);
            $journal['empreinte'] = hash_file('sha256', $final);

            // 5. Copier le fichier vers un emplacement externe (si configuré).
            $journal['copie_externe'] = $this->copierVersExterne($final, $nom);
        } catch (Throwable $e) {
            @unlink($final);

            // Algorithme §17 : le détail technique va dans les journaux, jamais à l'écran.
            Log::error('Sauvegarde de la base échouée', ['fichier' => $nom, 'exception' => $e]);

            $sauvegarde = Sauvegarde::create($journal + [
                'statut' => 'echec',
                'message' => $this->messageLisible($e),
            ]);

            $this->alerterAdministrateurs($sauvegarde);

            return $sauvegarde;
        } finally {
            @unlink($temporaire);
        }

        // 6. Enregistrer le résultat dans le journal.
        $sauvegarde = Sauvegarde::create($journal + [
            'statut' => 'reussie',
            'message' => $journal['copie_externe'] ? null : 'Pas de copie externe configurée.',
        ]);

        // 7. Supprimer les sauvegardes trop anciennes (après la réussite, jamais avant).
        $this->purger();

        Log::info('Sauvegarde de la base réussie', ['fichier' => $nom, 'taille' => $sauvegarde->taille]);

        return $sauvegarde;
    }

    /**
     * Restaure une sauvegarde dans la base de test et compare les effectifs avec ceux du dump.
     *
     * @return array<string, array{attendu: int, restaure: int}> table => effectifs
     *
     * @throws RuntimeException avec un message lisible si la restauration est impossible
     */
    public function verifier(Sauvegarde $sauvegarde): array
    {
        if (! $sauvegarde->estReussie()) {
            throw new RuntimeException("La sauvegarde {$sauvegarde->nom_fichier} a échoué : il n'y a rien à restaurer.");
        }

        $chemin = Storage::disk('local')->path($sauvegarde->chemin());
        if (! is_file($chemin)) {
            throw new RuntimeException("Le fichier {$sauvegarde->nom_fichier} est introuvable (supprimé ou déplacé).");
        }
        if (hash_file('sha256', $chemin) !== $sauvegarde->empreinte) {
            throw new RuntimeException("Le fichier {$sauvegarde->nom_fichier} a été modifié ou est corrompu : son empreinte ne correspond plus.");
        }

        $principale = $this->connexionPrincipale();
        $test = array_merge($principale, array_filter(config('sauvegarde.base_test'), fn ($v) => $v !== null && $v !== ''));

        if (empty(config('sauvegarde.base_test.database'))) {
            throw new RuntimeException('Aucune base de test configurée (SAUVEGARDE_TEST_DB_DATABASE).');
        }
        if ($test['host'] === $principale['host'] && (string) $test['port'] === (string) $principale['port']
            && $test['database'] === $principale['database']) {
            throw new RuntimeException('La base de test est la base principale : restauration refusée pour ne pas écraser les données.');
        }

        $flux = fopen('compress.zlib://'.$chemin, 'rb');
        try {
            $resultat = Process::env(['MYSQL_PWD' => (string) $test['password']])
                ->timeout(1800)
                ->input($flux)
                ->run([config('sauvegarde.mysql'), ...$this->optionsConnexion($test), $test['database']]);
        } finally {
            fclose($flux);
        }

        if ($resultat->failed()) {
            Log::error('Restauration de test échouée', ['fichier' => $sauvegarde->nom_fichier, 'sortie' => $resultat->errorOutput()]);

            throw new RuntimeException('La restauration dans la base de test a échoué. Détail dans storage/logs/laravel.log.');
        }

        config(['database.connections.sauvegarde_test' => $test]);
        DB::purge('sauvegarde_test');
        $connexion = DB::connection('sauvegarde_test');

        $comparaison = [];
        foreach (['materiels' => 'nombre_materiels', 'mouvements' => 'nombre_mouvements', 'users' => 'nombre_utilisateurs'] as $table => $colonne) {
            $comparaison[$table] = [
                'attendu' => (int) $sauvegarde->{$colonne},
                'restaure' => $connexion->table($table)->count(),
            ];
        }
        DB::purge('sauvegarde_test');

        return $comparaison;
    }

    /** Lance mysqldump vers $fichier. Le mot de passe passe par l'environnement, pas par la ligne de commande. */
    private function dumper(string $fichier): void
    {
        $base = $this->connexionPrincipale();

        $resultat = Process::env(['MYSQL_PWD' => (string) $base['password']])
            ->timeout(1800)
            ->run([
                config('sauvegarde.mysqldump'),
                ...$this->optionsConnexion($base),
                '--single-transaction', // copie cohérente sans bloquer l'application (InnoDB)
                '--no-tablespaces',     // évite d'exiger le privilège PROCESS, rarement accordé en hébergement
                '--default-character-set=utf8mb4',
                '--result-file='.$fichier,
                $base['database'],
            ]);

        if ($resultat->failed()) {
            throw new RuntimeException('mysqldump a échoué (code '.$resultat->exitCode().') : '.trim($resultat->errorOutput()));
        }
        if (! is_file($fichier) || filesize($fichier) === 0) {
            throw new RuntimeException('mysqldump n\'a produit aucun fichier.');
        }
    }

    /** Compresse en gzip par blocs de 1 Mo, sans charger tout le dump en mémoire. */
    private function compresser(string $source, string $destination): void
    {
        $entree = fopen($source, 'rb');
        $sortie = gzopen($destination, 'wb9');

        try {
            while (! feof($entree)) {
                gzwrite($sortie, fread($entree, 1024 * 1024));
            }
        } finally {
            fclose($entree);
            gzclose($sortie);
        }
    }

    private function copierVersExterne(string $fichier, string $nom): bool
    {
        $disque = config('sauvegarde.disque_externe');
        if (! $disque) {
            return false;
        }

        $flux = fopen($fichier, 'rb');
        try {
            if (! Storage::disk($disque)->writeStream($nom, $flux)) {
                throw new RuntimeException("La copie vers le disque externe « {$disque} » a échoué.");
            }
        } finally {
            if (is_resource($flux)) {
                fclose($flux);
            }
        }

        return true;
    }

    /**
     * Supprime fichiers et lignes de journal plus anciens que la durée de conservation.
     * La dernière sauvegarde réussie est toujours gardée, même ancienne : si les sauvegardes
     * automatiques s'arrêtent, on ne doit pas finir sans aucune sauvegarde.
     */
    private function purger(): void
    {
        $derniereReussie = Sauvegarde::where('statut', 'reussie')->latest()->latest('id')->value('id');
        $externe = config('sauvegarde.disque_externe');

        Sauvegarde::where('created_at', '<', now()->subDays(config('sauvegarde.conservation_jours')))
            ->where('id', '!=', $derniereReussie)
            ->get()
            ->each(function (Sauvegarde $ancienne) use ($externe) {
                Storage::disk('local')->delete($ancienne->chemin());
                if ($externe && $ancienne->copie_externe) {
                    Storage::disk($externe)->delete($ancienne->nom_fichier);
                }
                $ancienne->delete();
            });
    }

    private function alerterAdministrateurs(Sauvegarde $sauvegarde): void
    {
        try {
            Notification::send(
                User::where('role', 'administrateur')->where('actif', true)->get(),
                new SauvegardeEchouee($sauvegarde)
            );
        } catch (Throwable $e) {
            // Un serveur mail mal configuré ne doit pas masquer l'échec : il reste dans le journal et sur le tableau de bord.
            Log::error('Alerte de sauvegarde non envoyée', ['exception' => $e]);
        }
    }

    /** Message affiché aux administrateurs (Algorithme §17 : pas de SQLSTATE ni de sortie brute). */
    private function messageLisible(Throwable $e): string
    {
        $detail = $e->getMessage();

        return match (true) {
            str_contains($detail, 'not found') || str_contains($detail, 'No such file') || str_contains($detail, 'code 127') => 'L\'outil mysqldump est introuvable sur le serveur (réglage SAUVEGARDE_MYSQLDUMP).',
            str_contains($detail, 'Access denied') => 'La base de données a refusé l\'accès à mysqldump (identifiants ou droits insuffisants).',
            str_contains($detail, 'disque externe') => $detail,
            default => 'La sauvegarde a échoué. Le détail technique est dans le journal de l\'application.',
        };
    }

    /** @return array{host: string, port: mixed, database: string, username: string, password: mixed, unix_socket?: string} */
    private function connexionPrincipale(): array
    {
        return config('database.connections.'.config('sauvegarde.connexion', config('database.default')));
    }

    /** @return list<string> */
    private function optionsConnexion(array $base): array
    {
        $options = ['--user='.$base['username']];

        if (! empty($base['unix_socket'])) {
            $options[] = '--socket='.$base['unix_socket'];
        } else {
            $options[] = '--host='.$base['host'];
            $options[] = '--port='.$base['port'];
        }

        return $options;
    }
}
