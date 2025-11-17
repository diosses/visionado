<?php
/*
================================================================================
Archivo: routes/web.php
--------------------------------------------------------------------------------
Definición de rutas web (HTTP) de la aplicación.

Se agrupan en:
 1. Autenticación (login/logout)
 2. Dashboards protegidos por auth (admin y usuario visionadora)
 3. CRUD y utilidades de Obras (búsqueda incremental, quick store, capítulos)
 4. Gestión de Asignaciones y Visionados (iniciar, preparar, bulk)
 5. Emisiones (info, crear/editar, asociar/desasociar obra, set general para series)
 6. Elenco y Actores (add/remove/lista, typeahead search)
 7. Wizard de Series (flujo asistido multi-step vía AJAX)
 8. Acciones masivas (bulk actions genéricas)
 9. Reset administrativo de datos (borrado controlado de tablas)

Middlewares:
    - auth: protege la mayoría de rutas internas.
    - admin: restringe acceso al dashboard administrativo.

Convenciones:
    - Rutas AJAX devuelven HTML parcial o JSON según el controlador.
    - Para endpoints de modales se usan POST (guardado) y GET (información previa).
    - Los nombres (name()) se usan extensivamente en Blade y JS para construir URLs.
================================================================================
*/

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ObraController;
use App\Http\Controllers\AsignacionController;
use App\Http\Controllers\VisionadoController;
use App\Http\Controllers\ImportEmisionesController;
use App\Http\Controllers\EmisionController;
use App\Http\Controllers\ElencoController;
use App\Http\Controllers\ActorController;
use Illuminate\Support\Facades\DB;

// === Autenticación ===
Route::get('/', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// === Dashboards y recursos protegidos ===
Route::middleware(['auth'])->group(function () {
    // Dashboard admin y dashboard de usuario
    Route::get('/dashboard/admin', [AdminController::class, 'index'])->middleware('admin')->name('dashboard.admin');
    Route::get('/dashboard/user', [\App\Http\Controllers\UserController::class, 'index'])->name('dashboard.user');
    // Contenido de pestañas de dashboard usuario (AJAX)
    Route::get('/dashboard/user/tab', [\App\Http\Controllers\UserController::class, 'tab'])->name('dashboard.user.tab');
    // Redirección inicial según rol
    Route::get('/inicio', function () {
        $user = auth()->user();
        if ($user && method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return redirect()->route('dashboard.admin');
        }
        return redirect()->route('dashboard.user');
    })->name('inicio');

    // Obras: búsqueda incremental, capítulos y alta rápida (antes del resource para evitar colisión con {obra})
    Route::get('/obras/search', [ObraController::class, 'search'])->name('obras.search');
    Route::get('/obras/{NMObra}/capitulos', [ObraController::class, 'capitulos'])->name('obras.capitulos');
    Route::post('/obras/quick-store', [ObraController::class, 'quickStore'])->name('obras.quickStore');

    // Resource REST completo para Obras
    Route::resource('obras', ObraController::class);
});

// === Asignaciones, Visionados, Emisiones, Elenco, Actores, Wizard, Bulk ===
Route::middleware(['auth'])->group(function () {
    // Asignar emisión a visionadora (modal)
    Route::post('/asignaciones/asignar/{emision_id}', [AsignacionController::class, 'asignar'])->name('asignaciones.asignar');
    Route::post('/asignaciones/cambiar-visionadora/{asignacion}', [AsignacionController::class, 'cambiarVisionadora'])->name('asignaciones.cambiarVisionadora');
    // Asignación masiva
    Route::post('/asignaciones/asignar-bulk', [AsignacionController::class, 'asignarBulk'])->name('asignaciones.asignar.bulk');

    // Flujos de visionado (preparación / inicio)
    Route::get('/visionados/dashboard', [VisionadoController::class, 'dashboard'])->name('visionados.dashboard');
    Route::get('/visionados/preparacion/{asignacion_id}', [VisionadoController::class, 'preparacion'])->name('visionados.preparacion');
    Route::post('/visionados/iniciar/{asignacion_id}', [VisionadoController::class, 'iniciar'])->name('visionados.iniciar');

    // Importación masiva de emisiones (XLSX)
    Route::post('/emisiones/import', [ImportEmisionesController::class, 'import'])->name('emisiones.import');

    // Elenco: agregar actor
    Route::post('/obras/{NMObra}/elenco/add', [ElencoController::class, 'add'])->name('obras.elenco.add');

    // Elenco: guardar selección completa
    Route::post('/obras/{NMObra}/elenco/save', [ElencoController::class, 'save'])->name('obras.elenco.save');

    // Elenco: eliminar actor
    Route::delete('/obras/{NMObra}/elenco/{NMActor}', [ElencoController::class, 'remove'])->name('obras.elenco.remove');

    // Actores: búsqueda incremental (typeahead)
    Route::get('/actores/search', [ActorController::class, 'search'])->name('actores.search');

    // (las rutas de búsqueda y quick-store ya se declararon arriba)


    // Emisiones: info (detalle para modal)
    Route::get('/emisiones/info/{emision}', [EmisionController::class, 'info'])->name('emisiones.info');
    // Emisiones: crear/editar (modal AJAX)
    Route::post('/emisiones/save', [EmisionController::class, 'save'])->name('emisiones.save');
    // Emisiones: asociar obra
    Route::post('/emisiones/asignar-obra', [EmisionController::class, 'asignarObra'])->name('emisiones.asignarObra');
    Route::post('/emisiones/renombrar', [EmisionController::class, 'renombrar'])->name('emisiones.renombrar');
    // Emisiones: desasociar obra
    Route::post('/emisiones/quitar-obra', [EmisionController::class, 'quitarObra'])->name('emisiones.quitarObra');

    // Emisiones: definir obra general (serie) y vincular hijos
    Route::post('/emisiones/set-general', [EmisionController::class, 'setGeneralForGroup'])->name('emisiones.setGeneral');

    // Acciones masivas genéricas
    Route::post('/bulk-actions', [\App\Http\Controllers\BulkActionController::class, 'handle'])->name('bulk.actions');

    // Wizard de series (paso 1 y aplicación final)
    Route::get('/series/wizard', [\App\Http\Controllers\SeriesWizardController::class, 'show'])->name('series.wizard');
    Route::post('/emisiones/basic-info', [\App\Http\Controllers\SeriesWizardApplyController::class, 'basicInfo']);
    Route::post('/series/wizard/apply', [\App\Http\Controllers\SeriesWizardApplyController::class, 'apply']);

    // Reset administrativo de datos (excepto usuarios / roles)
    Route::post('/admin/reset-datos', function () {
        DB::transaction(function () {
            DB::table('bloques')->delete();
            DB::table('auditorias')->delete();
            DB::table('visionados')->delete();
            DB::table('asignaciones')->delete();
            DB::table('emisiones')->delete();
            DB::table('elencos')->delete();
            DB::table('obras')->delete();
        });
        return back()->with('success', 'Datos reiniciados (excepto usuarios y roles).');
    })->name('admin.reset-datos');
});

