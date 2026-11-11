<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mouvements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('materiel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ancien_emplacement_id')->nullable()->constrained('emplacements')->nullOnDelete();
            $table->foreignId('nouvel_emplacement_id')->nullable()->constrained('emplacements')->nullOnDelete();
            $table->string('ancien_statut')->nullable();
            $table->string('nouveau_statut')->nullable();
            $table->foreignId('ancien_utilisateur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('nouvel_utilisateur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motif')->nullable();
            $table->text('commentaire')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mouvements');
    }
};
