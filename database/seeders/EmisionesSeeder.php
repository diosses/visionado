<?php

namespace Database\Seeders;

use App\Models\Emision;
use App\Models\Obra;
use App\Models\Canal;
use Illuminate\Database\Seeder;

class EmisionesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener algunas obras aleatorias
        $obras = Obra::inRandomOrder()->limit(10)->get();
        $canales = Canal::all();
        
        foreach ($obras as $obra) {
            // Crear entre 1 y 3 emisiones para cada obra
            $numEmisiones = rand(1, 3);
            
            for ($i = 0; $i < $numEmisiones; $i++) {
                $fecha = now()->addDays(rand(-5, 5))->format('Y-m-d');
                $horaInicio = sprintf('%02d:00:00', rand(12, 22));
                $duracion = rand(30, 120);
                $horaFin = date('H:i:s', strtotime($horaInicio) + $duracion * 60);
                
                Emision::create([
                    'obra_id' => $obra->NMObra,
                    'canal_id' => $canales->random()->id,
                    'fecha_emision' => $fecha,
                    'hora_inicio' => $horaInicio,
                    'hora_fin' => $horaFin,
                    'duracion' => $duracion,
                    'protegido' => rand(0, 1),
                    'tipo' => $obra->TipoObra == 'Serie' ? 'serie' : ($obra->TipoObra == 'MISC' ? 'miscelaneo' : 'pelicula'),
                    'episodio' => $obra->TipoObra == 'Serie' ? 'Episodio ' . ($i+1) : null,
                    'fuente_datos' => 'IBOPE',
                ]);
            }
        }
    }
}
