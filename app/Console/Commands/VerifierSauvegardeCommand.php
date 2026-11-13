<?php

namespace App\Console\Commands;

use App\Models\Sauvegarde;
use App\Services\SauvegardeService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('sauvegarde:verifier {fichier? : Nom du fichier (par défaut la dernière sauvegarde réussie)}')]
#[Description('Restaure une sauvegarde dans la base de test et vérifie matériels, mouvements et utilisateurs (Algorithme §16 et §21)')]
class VerifierSauvegardeCommand extends Command
{
    public function handle(SauvegardeService $service): int
    {
        $sauvegarde = $this->argument('fichier')
            ? Sauvegarde::where('nom_fichier', $this->argument('fichier'))->first()
            : Sauvegarde::where('statut', 'reussie')->latest()->latest('id')->first();

        if (! $sauvegarde) {
            $this->error('Aucune sauvegarde correspondante dans le journal.');

            return self::FAILURE;
        }

        $this->line("Restauration de {$sauvegarde->nom_fichier} dans la base de test…");

        try {
            $comparaison = $service->verifier($sauvegarde);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Table', 'Au moment du dump', 'Après restauration', ''],
            collect($comparaison)->map(fn ($c, $table) => [
                $table, $c['attendu'], $c['restaure'], $c['attendu'] === $c['restaure'] ? 'OK' : 'ÉCART',
            ])->values()->all()
        );

        if (collect($comparaison)->contains(fn ($c) => $c['attendu'] !== $c['restaure'])) {
            $this->error('La sauvegarde restaurée ne correspond pas aux effectifs enregistrés.');

            return self::FAILURE;
        }

        $this->info('Sauvegarde vérifiée : restauration complète.');

        return self::SUCCESS;
    }
}
