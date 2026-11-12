<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

#[Signature('app:create-admin {email} {--prenom=Admin} {--nom=Principal} {--password=}')]
#[Description("Crée un compte administrateur (usage ponctuel en production, pas d'accès SSH requis)")]
class CreateAdminCommand extends Command
{
    public function handle(): int
    {
        $email = $this->argument('email');

        if (User::where('email', $email)->exists()) {
            $this->error("Un compte existe déjà avec l'email {$email}.");

            return self::FAILURE;
        }

        $password = $this->option('password') ?: Str::password(16);

        User::create([
            'prenom' => $this->option('prenom'),
            'nom' => $this->option('nom'),
            'email' => $email,
            'role' => 'administrateur',
            'actif' => true,
            'password' => Hash::make($password),
        ]);

        $this->info("Compte administrateur créé pour {$email}.");

        if (! $this->option('password')) {
            $this->warn("Mot de passe genere (a noter, il ne sera plus jamais affiche) : {$password}");
        }

        return self::SUCCESS;
    }
}
