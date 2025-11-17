<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaisesSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['codigo' => 'CL', 'nombre' => 'Chile'],
            ['codigo' => 'AR', 'nombre' => 'Argentina'],
            ['codigo' => 'UY', 'nombre' => 'Uruguay'],
            ['codigo' => 'BR', 'nombre' => 'Brasil'],
            ['codigo' => 'PE', 'nombre' => 'Perú'],
            ['codigo' => 'BO', 'nombre' => 'Bolivia'],
            ['codigo' => 'CO', 'nombre' => 'Colombia'],
            ['codigo' => 'US', 'nombre' => 'Estados Unidos'],
            ['codigo' => 'MX', 'nombre' => 'México'],
            ['codigo' => 'ES', 'nombre' => 'España'],
        ];
        foreach ($rows as $r) {
            DB::table('paises')->updateOrInsert(['codigo' => strtoupper($r['codigo'])], ['nombre' => $r['nombre']]);
        }
    }
}
