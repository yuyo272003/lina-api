<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Configuracion extends Model
{
    use HasFactory;

    protected $table = 'configuraciones';

    /**
     * Atributos asignables en masa.
     * Utilizado para almacenar configuraciones globales del sistema
     * Estructura: Clave (Unique Index) -> Valor.
     */
    protected $fillable = [
        'clave',
        'valor',
    ];
}