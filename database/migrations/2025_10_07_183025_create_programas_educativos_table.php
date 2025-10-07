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
        Schema::create('programas_educativos', function (Blueprint $table) {
            $table->id('idPE');
            $table->string('nombrePE');
            $table->foreignId('facultad_id')->constrained('facultades', 'idFacultad');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('programas_educativos');
    }
};
