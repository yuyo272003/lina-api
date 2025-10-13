<?php

// database/migrations/xxxx_xx_xx_xxxxxx_create_solicitud_respuestas_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('solicitud_respuestas', function (Blueprint $table) {
            $table->id();
            
            // Llave foránea a la solicitud
            $table->foreignId('solicitud_id')->constrained('solicitudes', 'idSolicitud')->onDelete('cascade');
            
            // Llave foránea al trámite
            $table->foreignId('tramite_id')->constrained('tramites', 'idTramite')->onDelete('cascade');
            
            // Llave foránea al requisito
            $table->foreignId('requisito_id')->constrained('requisitos', 'idRequisito')->onDelete('cascade');
            
            // La respuesta del usuario
            $table->text('respuesta');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('solicitud_respuestas');
    }
};