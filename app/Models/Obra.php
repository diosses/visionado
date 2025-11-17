<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo Obra
 * Representa una obra (película, serie o capítulo). Cuando NMSerie tiene valor
 * esta obra es un capítulo de la serie referida por NMSerie.
 */
class Obra extends Model
{
    protected $table = 'obras';
    protected $primaryKey = 'NMObra';
    public $timestamps = false;
    
    protected $fillable = [
        'NMSerie',
        'TituloObra',
        'Genero',
        'PaisOrigen',
        'Director',
        'Duracion',
        'AnioProduccion',
        'CodGenero',
        'Idioma',
        'FichaDoblaje',
        'Guionista',
        'TipoObra',
        'FichaImagen',
        'ProtegidoGlobal',
    'SecuenciasTotales'
    ];
    
    // Convertir campos de tinyint(1) a booleanos en PHP
    protected $casts = [
        'FichaDoblaje' => 'boolean',
        'FichaImagen' => 'boolean',
        'ProtegidoGlobal' => 'boolean',
        'NMSerie' => 'integer',
        'Duracion' => 'integer',
        'AnioProduccion' => 'integer',
        'SecuenciasTotales' => 'integer'
    ];

    // Relaciones de serie y capítulos
    public function serie()
    {
        // Obra padre (serie principal) cuando esta obra es un capítulo
        return $this->belongsTo(Obra::class, 'NMSerie', 'NMObra');
    }

    public function capitulos()
    {
        // Capítulos que pertenecen a esta obra (si es serie principal)
        return $this->hasMany(Obra::class, 'NMSerie', 'NMObra')->orderBy('TituloObra');
    }

    // Elenco (reparto) relaciones
    public function elencos()
    {
        return $this->hasMany(Elenco::class, 'NMObra', 'NMObra');
    }

    public function actores()
    {
        return $this->belongsToMany(Actor::class, 'elencos', 'NMObra', 'NMActor', 'NMObra', 'NMActor')
            ->withPivot(['tipo_participacion','confirmado']);
    }

    // Emisiones relacionadas a esta obra
    public function emisiones()
    {
        return $this->hasMany(Emision::class, 'obra_id', 'NMObra');
    }
}
