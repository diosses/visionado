<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de Géneros de obras.
 * Normaliza el campo Genero usando un código corto (codigo) y un nombre legible (nombre).
 */
class Genero extends Model
{
    protected $table = 'generos';
    protected $primaryKey = 'codigo';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = ['codigo','nombre'];
}
