<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mouvements', function (Blueprint $table) {
            // Champs de la fiche modifiés lors d'un mouvement "modification" :
            // {"marque": ["HP", "Dell"], ...} = champ => [ancienne valeur, nouvelle valeur]
            // (cahier des charges §11 : conserver l'ancienne et la nouvelle valeur).
            $table->json('modifications')->nullable()->after('commentaire');
        });
    }

    public function down(): void
    {
        Schema::table('mouvements', function (Blueprint $table) {
            $table->dropColumn('modifications');
        });
    }
};
