<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Journal des sauvegardes (Algorithme §16 : "Enregistrer le résultat dans un journal").
        Schema::create('sauvegardes', function (Blueprint $table) {
            $table->id();
            $table->string('nom_fichier');
            $table->string('statut'); // reussie | echec
            $table->unsignedBigInteger('taille')->nullable(); // octets, fichier compressé
            $table->string('empreinte', 64)->nullable(); // SHA-256 du fichier compressé
            $table->boolean('copie_externe')->default(false);
            $table->text('message')->nullable(); // message compréhensible, jamais le détail technique
            // Effectifs au moment du dump : servent à vérifier une restauration.
            $table->unsignedInteger('nombre_materiels')->nullable();
            $table->unsignedInteger('nombre_mouvements')->nullable();
            $table->unsignedInteger('nombre_utilisateurs')->nullable();
            // null = sauvegarde automatique (tâche planifiée).
            $table->foreignId('utilisateur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sauvegardes');
    }
};
