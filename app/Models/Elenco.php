<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Elenco extends Model
{
    protected $table = 'elencos';
    protected $primaryKey = 'NMElenco';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $fillable = [
        'NMObra',
        'NMActor',
        'tipo_participacion',
        'confirmado',
    ];

    public function obra()
    {
        return $this->belongsTo(Obra::class, 'NMObra', 'NMObra');
    }

    public function actor()
    {
        return $this->belongsTo(Actor::class, 'NMActor', 'NMActor');
    }
}
