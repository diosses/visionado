<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GenerosSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['codigo' => 'CINE', 'nombre' => 'Cine'],
            ['codigo' => 'PCON', 'nombre' => 'P. Concurso'],
            ['codigo' => 'CORT', 'nombre' => 'Cortometraje'],
            ['codigo' => 'DANI', 'nombre' => 'Dib. Animado'],
            ['codigo' => 'DOC',  'nombre' => 'Documental'],
            ['codigo' => 'MAG',  'nombre' => 'Magazine'],
            ['codigo' => 'PMUS', 'nombre' => 'P. Musical'],
            ['codigo' => 'PHUM', 'nombre' => 'P. Humor'],
            ['codigo' => 'PVAR', 'nombre' => 'P. Variedades'],
            ['codigo' => 'SER',  'nombre' => 'Serie'],
            ['codigo' => 'TEAT', 'nombre' => 'Teatro'],
        ];
        foreach ($rows as $r) {
            DB::table('generos')->updateOrInsert(['codigo' => $r['codigo']], ['nombre' => $r['nombre']]);
        }
    }
}
