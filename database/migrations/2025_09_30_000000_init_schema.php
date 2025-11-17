<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unified initial schema migration.
 *
 * Design choices:
 * - Uses cascade deletes for dependent chains (obras→emisiones, emisiones→asignaciones, asignaciones→visionados, visionados→bloques/auditorias, obras→elencos, actores→elencos).
 * - Catalog tables (generos, idiomas, paises) have string PKs and no timestamps.
 * - Visionados estado tinyInteger (sparse codes 0,1,3,4) and modo tinyInteger(1) boolean-like.
 * - obra_id in emisiones nullable and set null on obra deletion to preserve broadcast history (but you requested cascade broadly, so we will CASCADE here as per last instruction 'cascade nomás').
 * - Self reference in obras (NMSerie) nullable: CASCADE on delete (if a series root is deleted, its capítulos go too).
 * - Added useful indexes for filtering (emisiones canal_id+fecha_emision, emisiones TituloEmision, obras TituloObra, elencos composite unique NMObra+NMActor).
 *
 * Seeding note (optional): create a role 'admin' with id=1 and assign to a user
 * to satisfy User::isAdmin() logic relying on role_id === 1. Example quick seed:
 *   DB::table('roles')->insert(['id'=>1,'name'=>'admin']);
 *   DB::table('users')->insert([...,'role_id'=>1]);
 */
return new class extends Migration {
    public function up(): void
    {
        // 1. roles
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('name')->unique();
        });

        // 2. users
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
        });

        // 3. catalogos basicos
        Schema::create('generos', function (Blueprint $table) {
            $table->string('codigo', 5)->primary();
            $table->string('nombre', 30);
        });
        Schema::create('idiomas', function (Blueprint $table) {
            $table->string('codigo', 5)->primary();
            $table->string('nombre', 60);
        });
        Schema::create('paises', function (Blueprint $table) {
            $table->string('codigo', 5)->primary();
            $table->string('nombre', 60);
        });

        // 4. canales
        Schema::create('canales', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 50);
            $table->string('codigo', 10)->unique();
            $table->enum('tipo', ['abierta','cable','streaming'])->default('abierta');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // 5. obras
        Schema::create('obras', function (Blueprint $table) {
            $table->integerIncrements('NMObra');
            $table->integer('NMSerie')->nullable(); // self FK after table created
            $table->string('TituloObra', 129);
            $table->string('Genero', 13); // legacy plain text
            $table->string('PaisOrigen', 5);
            $table->string('Director', 122)->nullable();
            $table->integer('Duracion')->nullable();
            $table->integer('AnioProduccion')->nullable();
            $table->string('CodGenero', 6);
            $table->string('Idioma', 2);
            $table->boolean('FichaDoblaje')->nullable();
            $table->string('Guionista', 136)->nullable();
            $table->string('TipoObra', 20); // widen a bit from 7 for flexibility
            $table->boolean('FichaImagen')->nullable();
            $table->boolean('ProtegidoGlobal')->nullable();
            $table->integer('SecuenciasTotales')->nullable();
            $table->string('TituloOriginal', 138)->nullable();
            $table->index('TituloObra', 'obras_titulo_idx');
        });
        // self FK (must be separate to allow nullable referencing same table)
        Schema::table('obras', function (Blueprint $table) {
            $table->foreign('NMSerie')->references('NMObra')->on('obras')->onDelete('cascade');
        });

        // 6. actores
        Schema::create('actores', function (Blueprint $table) {
            $table->increments('NMActor');
            $table->string('Nombre', 55);
            $table->string('NombreArtistico', 93)->nullable();
            // Could add timestamps later if needed
        });

        // 7. elencos (pivot with extra data)
        Schema::create('elencos', function (Blueprint $table) {
            $table->increments('NMElenco');
            $table->integer('NMObra');
            $table->integer('NMActor');
            $table->string('tipo_participacion', 50)->nullable();
            $table->boolean('confirmado')->default(false);
            $table->unique(['NMObra','NMActor'], 'elencos_unq_obra_actor');
            $table->foreign('NMObra')->references('NMObra')->on('obras')->onDelete('cascade');
            $table->foreign('NMActor')->references('NMActor')->on('actores')->onDelete('cascade');
        });

        // 8. emisiones
        Schema::create('emisiones', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('obra_id')->nullable(); // references obras.NMObra
            $table->string('TituloEmision', 255)->nullable();
            $table->foreignId('canal_id')->constrained('canales')->onDelete('cascade');
            $table->date('fecha_emision');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->integer('duracion')->comment('Duración en minutos');
            $table->boolean('protegido')->default(false);
            $table->enum('tipo', ['pelicula','serie','miscelaneo']);
            $table->string('episodio', 100)->nullable()->comment('Número o nombre del episodio');
            $table->string('fuente_datos', 50)->nullable()->comment('IBOPE, etc.');
            $table->timestamps();
            $table->index(['canal_id','fecha_emision'],'emisiones_canal_fecha_idx');
            $table->index('TituloEmision','emisiones_titulo_idx');
            $table->foreign('obra_id')->references('NMObra')->on('obras')->onDelete('cascade');
        });

        // 9. asignaciones
        Schema::create('asignaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emision_id')->constrained('emisiones')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('estado', ['pendiente','en_progreso','completada','auditada'])->default('pendiente');
            $table->dateTime('fecha_asignacion');
            $table->dateTime('fecha_completado')->nullable();
            $table->foreignId('asignado_por')->constrained('users')->onDelete('cascade');
            $table->text('notas')->nullable();
            $table->timestamps();
        });

        // 10. visionados
        Schema::create('visionados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asignacion_id')->constrained('asignaciones')->onDelete('cascade');
            $table->dateTime('fecha_inicio')->nullable();
            $table->dateTime('fecha_fin')->nullable();
            $table->tinyInteger('estado')->unsigned();
            $table->tinyInteger('modo')->unsigned();
            $table->timestamps();
        });

        // 11. bloques
        Schema::create('bloques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visionado_id')->constrained('visionados')->onDelete('cascade');
            $table->integer('numero_bloque');
            $table->time('tiempo_inicio');
            $table->time('tiempo_fin');
            $table->enum('tipo', ['segmento','secuencia','sin_registro'])->default('segmento');
            $table->boolean('protegido')->default(true);
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        // 12. auditorias
        Schema::create('auditorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visionado_id')->constrained('visionados')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->dateTime('fecha');
            $table->enum('estado', ['aprobada','rechazada','con_observaciones'])->default('con_observaciones');
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        // 13. soporte Laravel básico (cache, jobs, etc.)
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });
        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });
        Schema::create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue');
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
            $table->index('queue');
        });
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity');
            $table->index('last_activity');
        });

        // migrations meta table
        Schema::create('migrations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('migration');
            $table->integer('batch');
        });
    }

    public function down(): void
    {
        // Drop in reverse dependency order
        Schema::dropIfExists('migrations');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('auditorias');
        Schema::dropIfExists('bloques');
        Schema::dropIfExists('visionados');
        Schema::dropIfExists('asignaciones');
        Schema::dropIfExists('emisiones');
        Schema::dropIfExists('elencos');
        Schema::dropIfExists('actores');
        Schema::dropIfExists('obras');
        Schema::dropIfExists('canales');
        Schema::dropIfExists('paises');
        Schema::dropIfExists('idiomas');
        Schema::dropIfExists('generos');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
    }
};
