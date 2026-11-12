<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materiels', function (Blueprint $table) {
            // Référence du fabricant (ex. DL-LAT-5420), cahier des charges §1 et §4.
            $table->string('reference_constructeur', 100)->nullable()->after('numero_serie');
            $table->index('reference_constructeur');
        });
    }

    public function down(): void
    {
        Schema::table('materiels', function (Blueprint $table) {
            $table->dropIndex(['reference_constructeur']);
            $table->dropColumn('reference_constructeur');
        });
    }
};
