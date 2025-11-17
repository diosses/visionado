<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IdiomasSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['codigo' => 'ES', 'nombre' => 'Español'],
            ['codigo' => 'EN', 'nombre' => 'Inglés'],
            ['codigo' => 'PT', 'nombre' => 'Portugués'],
            ['codigo' => 'FR', 'nombre' => 'Francés'],
            ['codigo' => 'DE', 'nombre' => 'Alemán'],
            ['codigo' => 'IT', 'nombre' => 'Italiano'],
        ];
        foreach ($rows as $r) {
            DB::table('idiomas')->updateOrInsert(['codigo' => strtoupper($r['codigo'])], ['nombre' => $r['nombre']]);
        }
    }
}
