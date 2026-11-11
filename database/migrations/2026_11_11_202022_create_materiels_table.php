<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materiels', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('type_materiel');
            $table->string('marque');
            $table->string('modele');
            $table->string('numero_serie')->nullable()->unique();
            $table->enum('condition_stock', ['neuf', 'occasion', 'reconditionne', 'retour'])->default('neuf');
            $table->enum('statut', [
                'disponible', 'attribue', 'reserve', 'en_transit',
                'reparation', 'perdu', 'reforme', 'a_verifier',
            ])->default('disponible');
            $table->foreignId('emplacement_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('utilisateur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date_acquisition')->nullable();
            $table->date('date_fin_garantie')->nullable();
            $table->text('commentaire')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();

            $table->index(['type_materiel']);
            $table->index(['marque']);
            $table->index(['modele']);
            $table->index(['statut']);
            $table->index(['condition_stock']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materiels');
    }
};
