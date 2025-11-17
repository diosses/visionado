<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bloque extends Model
{
    protected $table = 'bloques';
    protected $fillable = [
        'visionado_id', 'numero_bloque', 'tiempo_inicio', 'tiempo_fin',
        'tipo', 'protegido', 'observaciones'
    ];

    protected $casts = [
        'protegido' => 'boolean',
    ];

    public function visionado()
    {
        return $this->belongsTo(Visionado::class);
    }
}