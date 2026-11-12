<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Réglages de l'application modifiables depuis le site par un administrateur (clé → valeur).
     */
    public function up(): void
    {
        Schema::create('parametres', function (Blueprint $table) {
            $table->id();
            $table->string('cle')->unique();
            $table->string('valeur');
            $table->timestamps();
        });

        // Valeur initiale, présente dès le déploiement (sans passer par le seeder).
        DB::table('parametres')->insert([
            'cle' => 'seuil_stock_faible', 'valeur' => '2', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('parametres');
    }
};
