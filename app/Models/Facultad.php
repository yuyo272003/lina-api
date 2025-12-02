<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Facultad extends Model
{
    use HasFactory;

    protected $table = 'facultades';
    protected $primaryKey = 'idFacultad';
    protected $fillable = ['nombreFacultad', 'idCampus'];

    public function campus()
    {
        return $this->belongsTo(Campus::class, 'idCampus', 'idCampus');
    }
}