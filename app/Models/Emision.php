<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo Emision
 * Representa una emisión de contenido en un canal y fecha/horario determinados.
 *
 * Atributos principales:
 * - obra_id (NMObra)
 * - TituloEmision (string opcional)
 * - canal_id (fk)
 * - fecha_emision (date)
 * - hora_inicio, hora_fin (HH:MM:SS)
 * - duracion (minutos)
 * - protegido (bool)
 * - tipo (miscelaneo|serie|...)
 */
class Emision extends Model
{
    protected $table = 'emisiones';
    protected $fillable = [
    'obra_id', 'TituloEmision', 'canal_id', 'fecha_emision', 'hora_inicio', 'hora_fin',
    'duracion', 'protegido', 'tipo', 'episodio', 'fuente_datos'
    ];

    protected $casts = [
        'fecha_emision' => 'date',
    // Store/return time columns as raw strings (e.g., HH:MM:SS)
    'hora_inicio' => 'string',
    'hora_fin' => 'string',
        'protegido' => 'boolean',
    ];

    public function obra()
    {
        return $this->belongsTo(Obra::class, 'obra_id', 'NMObra');
    }

    public function canal()
    {
        return $this->belongsTo(Canal::class);
    }

    public function asignaciones()
    {
        return $this->hasMany(Asignacion::class);
    }
    
    // Formateador para mostrar información de emisión
    public function getInfoEmisionAttribute()
    {
    $hi = is_string($this->hora_inicio) ? substr($this->hora_inicio, 0, 5) : null;
    $hf = is_string($this->hora_fin) ? substr($this->hora_fin, 0, 5) : null;
    return ($this->canal->nombre ?? '—') . ' - ' . 
        optional($this->fecha_emision)->format('d/m/Y') . ' - ' .
        ($hi ?? '—') . ' a ' . 
        ($hf ?? '—');
    }
}