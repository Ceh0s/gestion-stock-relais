<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emplacements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('nom');
            $table->string('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['site_id', 'nom']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emplacements');
    }
};
