<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Database\Seeders\GenerosSeeder;
use Database\Seeders\IdiomasSeeder;
use Database\Seeders\PaisesSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    // Catálogo de géneros para normalización de obras
    $this->call(GenerosSeeder::class);
    // Catálogos ISO
    $this->call(IdiomasSeeder::class);
    $this->call(PaisesSeeder::class);
    }
}
