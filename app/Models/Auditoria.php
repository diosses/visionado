<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Auditoria extends Model
{
    protected $table = 'auditorias';
    protected $fillable = [
        'visionado_id', 'user_id', 'fecha', 'estado', 'observaciones'
    ];

    protected $casts = [
        'fecha' => 'datetime',
    ];

    public function visionado()
    {
        return $this->belongsTo(Visionado::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}