<?php


namespace App\Http\Controllers;

use App\Models\Asignacion;
use App\Models\Emision;
use App\Models\Obra;
use App\Models\User;
use App\Models\Visionado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Controlador para la gestión de asignaciones y su creación masiva.
 *
 * La UI principal vive en el dashboard; estos endpoints se consumen por AJAX
 * desde los modales y botones de la pestaña correspondiente.
 */
class AsignacionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /** Procesa la creación o actualización de una asignación desde el modal (idempotente por emisión). */
    public function asignar(Request $request, $emision_id)
    {
        Log::info('[AsignacionController@asignar] called', [
            'emision_id' => $emision_id,
            'request_user_id' => $request->input('user_id'),
            'db' => DB::connection()->getDatabaseName(),
        ]);
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'notas' => 'nullable|string|max:500',
        ]);
        
        $emision = Emision::findOrFail($emision_id);

        // Upsert de asignación: una sola por emisión. Si existe, actualiza user/notas/fecha; si no, crea.
        $asignacion = Asignacion::where('emision_id', $emision_id)->first();
        $wasUpdate = false;
        if ($asignacion) {
            $asignacion->user_id = $request->user_id;
            // Si cambia de usuaria o hay notas nuevas, actualizamos fecha_asignacion y notas
            $asignacion->notas = $request->notas;
            $asignacion->asignado_por = Auth::id();
            if (!$asignacion->estado) { $asignacion->estado = 'pendiente'; }
            $asignacion->fecha_asignacion = now();
            $asignacion->save();
            $wasUpdate = true;
            Log::info('[AsignacionController@asignar] asignacion updated', [
                'asignacion_id' => $asignacion->id,
                'emision_id' => $asignacion->emision_id,
                'user_id' => $asignacion->user_id,
            ]);
            // Asegurar visionado asociado
            $visionado = $asignacion->visionado;
            if (!$visionado) {
                $visionado = new Visionado();
                $visionado->asignacion_id = $asignacion->id;
                $visionado->estado = 'pendiente';
                $visionado->modo = $emision->tipo == 'miscelaneo' ? 'minutaje' : 'secuencia';
                $visionado->save();
            }
    } else {
            // Crear asignación nueva
            $asignacion = new Asignacion();
            $asignacion->emision_id = $emision_id;
            $asignacion->user_id = $request->user_id;
            $asignacion->estado = 'pendiente';
            $asignacion->fecha_asignacion = now();
            $asignacion->asignado_por = Auth::id();
            $asignacion->notas = $request->notas;
            $asignacion->save();
            Log::info('[AsignacionController@asignar] asignacion created', [
                'asignacion_id' => $asignacion->id,
                'emision_id' => $asignacion->emision_id,
                'user_id' => $asignacion->user_id,
            ]);
            // Crear visionado asociado en estado pendiente
            $visionado = new Visionado();
            $visionado->asignacion_id = $asignacion->id;
            $visionado->estado = 'pendiente';
            $visionado->modo = $emision->tipo == 'miscelaneo' ? 'minutaje' : 'secuencia';
            $visionado->save();
            Log::info('[AsignacionController@asignar] visionado created', [
                'visionado_id' => $visionado->id,
                'asignacion_id' => $visionado->asignacion_id,
                'modo' => $visionado->modo,
            ]);
    }
        
    // Si la petición es AJAX devolvemos JSON para refrescar pestañas sin navegar
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['ok' => true, 'asignacion_id' => $asignacion->id, 'updated' => $wasUpdate]);
        }

    // Fallback: permanecer en el dashboard y mostrar pestaña Visionados
        return redirect()->route('dashboard.admin', ['tab' => 'visionados'])
            ->with('success', 'Visionado asignado correctamente.');
    }

    /** Crea asignaciones masivas para una lista de emisiones. */
    public function asignarBulk(Request $request)
    {
        $data = $request->validate([
            'emision_ids' => 'required|array|min:1',
            'emision_ids.*' => 'integer|exists:emisiones,id',
            'user_id' => 'required|exists:users,id',
            'notas' => 'nullable|string|max:500',
        ]);

        Log::info('[AsignacionController@asignarBulk] called', [
            'emision_ids' => $data['emision_ids'] ?? [],
            'user_id' => $data['user_id'] ?? null,
            'db' => DB::connection()->getDatabaseName(),
        ]);
    $created = [];
    $updated = [];
        foreach ($data['emision_ids'] as $emiId) {
            $emision = Emision::find($emiId);
            if (!$emision) continue;

            $asignacion = Asignacion::where('emision_id', $emiId)->first();
            if ($asignacion) {
                $asignacion->user_id = $data['user_id'];
                $asignacion->notas = $data['notas'] ?? null;
                $asignacion->asignado_por = Auth::id();
                if (!$asignacion->estado) { $asignacion->estado = 'pendiente'; }
                $asignacion->fecha_asignacion = now();
                $asignacion->save();
                $visionado = $asignacion->visionado;
                if (!$visionado) {
                    $visionado = new Visionado();
                    $visionado->asignacion_id = $asignacion->id;
                    $visionado->estado = 'pendiente';
                    $visionado->modo = $emision->tipo == 'miscelaneo' ? 'minutaje' : 'secuencia';
                    $visionado->save();
                }
                Log::info('[AsignacionController@asignarBulk] updated pair', [ 'asignacion_id' => $asignacion->id, 'emision_id' => $emiId ]);
                $updated[] = $asignacion->id;
            } else {
                $asignacion = new Asignacion();
                $asignacion->emision_id = $emiId;
                $asignacion->user_id = $data['user_id'];
                $asignacion->estado = 'pendiente';
                $asignacion->fecha_asignacion = now();
                $asignacion->asignado_por = Auth::id();
                $asignacion->notas = $data['notas'] ?? null;
                $asignacion->save();
                $visionado = new Visionado();
                $visionado->asignacion_id = $asignacion->id;
                $visionado->estado = 'pendiente';
                $visionado->modo = $emision->tipo == 'miscelaneo' ? 'minutaje' : 'secuencia';
                $visionado->save();
                Log::info('[AsignacionController@asignarBulk] created pair', [ 'asignacion_id' => $asignacion->id, 'emision_id' => $emiId ]);
            }
            $created[] = $asignacion->id;
        }

        return response()->json([
            'ok' => true,
            'created' => $created,
            'updated' => $updated,
            'count' => count($created),
            'count_updated' => count($updated)
        ]);
    }
    
    /** Cambia la visionadora de una asignación existente (inline en tabla-visionados). */
    public function cambiarVisionadora(Request $request, Asignacion $asignacion)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);
        $asignacion->user_id = $data['user_id'];
        $asignacion->save();
        // Mantener estado del visionado; no lo reiniciamos aquí
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['ok' => true, 'asignacion_id' => $asignacion->id, 'user_id' => $asignacion->user_id]);
        }
        return back()->with('success', 'Visionadora actualizada');
    }
    
    // Métodos legados removidos: materialSinAsignar, gestion, cancelar (migrado al dashboard).
    
}