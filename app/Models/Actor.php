<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Actor extends Model
{
    protected $table = 'actores';
    protected $primaryKey = 'NMActor';
    public $timestamps = false;
    protected $fillable = [
        'Nombre',
        'NombreArtistico',
    ];

    public function elencos()
    {
        return $this->hasMany(Elenco::class);
    }

    public function obras()
    {
        return $this->belongsToMany(Obra::class, 'elencos', 'NMActor', 'NMObra', 'NMActor', 'NMObra')
            ->withPivot(['tipo_participacion','confirmado']);
    }

    // Accessor for a clean, user-facing label
    public function getDisplayNameAttribute(): string
    {
        $nombre = (string)($this->attributes['Nombre'] ?? '');
        $artistico = (string)($this->attributes['NombreArtistico'] ?? '');
        $label = $artistico !== '' ? $artistico : $nombre;
        // Remove Unicode control chars and normalize whitespace
        $label = preg_replace('/[\p{C}]+/u', ' ', $label ?? '');
        $label = trim(preg_replace('/\s+/u', ' ', $label));
        return $label;
    }
}
