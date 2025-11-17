<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    // Columnas que se pueden asignar en masa
    protected $fillable = ['name'];

    // Relación con los usuarios
    public function users()
    {
        return $this->hasMany(User::class);
    }
}