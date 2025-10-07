<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProgramaEducativo extends Model
{
    use HasFactory;

    protected $table = 'programas_educativos';
    protected $primaryKey = 'idPE';
    protected $fillable = ['nombrePE', 'facultad_id'];
}