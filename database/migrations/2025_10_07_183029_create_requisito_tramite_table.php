<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('requisito_tramite', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idTramite')->constrained('tramites', 'idTramite');
            $table->foreignId('idRequisito')->constrained('requisitos', 'idRequisito');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requisito_tramite');
    }
};
