<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo Asignacion
 * Vincula una Emision con una usuaria visionadora y su estado de proceso.
 */
class Asignacion extends Model
{
    protected $table = 'asignaciones';
    protected $fillable = [
    'emision_id', 'user_id', 'estado', 'fecha_asignacion',
        'fecha_completado', 'asignado_por'
    ];

    protected $casts = [
        'fecha_asignacion' => 'datetime',
        'fecha_completado' => 'datetime',
    ];

    public function emision()
    {
        return $this->belongsTo(Emision::class);
    }

    public function usuario()
    {
    return $this->belongsTo(User::class, 'user_id');
    }

    public function asignador()
    {
        return $this->belongsTo(User::class, 'asignado_por');
    }

    public function visionado()
    {
        return $this->hasOne(Visionado::class);
    }
}