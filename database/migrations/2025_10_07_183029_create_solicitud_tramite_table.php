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
        Schema::create('solicitud_tramite', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idSolicitud')->constrained('solicitudes', 'idSolicitud');
            $table->foreignId('idTramite')->constrained('tramites', 'idTramite');
            $table->json('datos_requisitos')->nullable(); // Para guardar los datos extra
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitud_tramite');
    }
};
