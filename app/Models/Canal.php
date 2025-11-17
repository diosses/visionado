<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Canal extends Model
{
    protected $table = 'canales';
    protected $fillable = ['nombre', 'codigo', 'tipo', 'activo'];

    public function emisiones()
    {
        return $this->hasMany(Emision::class);
    }
}