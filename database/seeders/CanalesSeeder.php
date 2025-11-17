<?php

namespace Database\Seeders;

use App\Models\Canal;
use Illuminate\Database\Seeder;

class CanalesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Canal::create(['nombre' => 'TVN', 'codigo' => 'TVN', 'tipo' => 'abierta']);
        Canal::create(['nombre' => 'Mega', 'codigo' => 'MEGA', 'tipo' => 'abierta']);
        Canal::create(['nombre' => 'Canal 13', 'codigo' => 'C13', 'tipo' => 'abierta']);
        Canal::create(['nombre' => 'Chilevisión', 'codigo' => 'CHV', 'tipo' => 'abierta']);
        Canal::create(['nombre' => 'La Red', 'codigo' => 'LARED', 'tipo' => 'abierta']);
    }
}
