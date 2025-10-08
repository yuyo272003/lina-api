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
    Schema::create('datos_personales', function (Blueprint $table) {
        $table->id('idDatosPersonales');
        $table->string('NombreDatosPersonales');
        $table->string('ApellidoPaternoDatosPersonales');
        $table->string('ApellidoMaternoDatosPersonales')->nullable();

        $table->unsignedBigInteger('user_id');
        $table->foreign('user_id')->references('id')->on('users');

        $table->integer('CreatedBy');
        $table->integer('UpdatedBy');
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('datos_personales');
    }
};
