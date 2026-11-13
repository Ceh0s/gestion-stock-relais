<?php

namespace App\Console\Commands;

use App\Services\SauvegardeService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sauvegarde:creer')]
#[Description('Sauvegarde la base de données (Algorithme §16) ; lancée chaque nuit par le planificateur')]
class CreerSauvegardeCommand extends Command
{
    public function handle(SauvegardeService $service): int
    {
        $sauvegarde = $service->creer();

        if (! $sauvegarde->estReussie()) {
            $this->error("Échec : {$sauvegarde->message}");

            return self::FAILURE;
        }

        $this->info("Sauvegarde créée : {$sauvegarde->nom_fichier} ({$sauvegarde->tailleLisible()})");
        $this->line("Empreinte SHA-256 : {$sauvegarde->empreinte}");
        if ($sauvegarde->message) {
            $this->line($sauvegarde->message);
        }

        return self::SUCCESS;
    }
}
