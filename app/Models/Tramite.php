<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tramite extends Model
{
    use HasFactory;
    protected $table = 'tramites';
    protected $primaryKey = 'idTramite';

    protected $fillable = ['nombreTramite', 'costoTramite']; 

    public function requisitos()
    {
        return $this->belongsToMany(\App\Models\Requisito::class, 'requisito_tramite', 'idTramite', 'idRequisito');
    }
}
