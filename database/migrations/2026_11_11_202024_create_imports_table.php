<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->string('nom_fichier');
            $table->foreignId('utilisateur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('nombre_lignes')->default(0);
            $table->unsignedInteger('nombre_importees')->default(0);
            $table->unsignedInteger('nombre_erreurs')->default(0);
            $table->enum('statut', ['en_attente', 'valide', 'partiel', 'rejete', 'termine'])->default('en_attente');
            $table->json('rapport')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};
