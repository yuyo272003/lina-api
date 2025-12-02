<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Campus extends Model
{
    use HasFactory;

    protected $table = 'campuses';
    protected $primaryKey = 'idCampus';
    protected $fillable = ['nombreCampus'];

    public function facultades()
    {
        return $this->hasMany(Facultad::class, 'idCampus', 'idCampus');
    }
}