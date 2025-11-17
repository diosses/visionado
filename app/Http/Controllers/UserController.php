<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Asignacion;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $userId = Auth::id();
        // Stats scoped to the authenticated visionadora
        $total = Asignacion::where('user_id', $userId)->count();
        $completados = Asignacion::where('user_id', $userId)->where('estado', 'completada')->count();
        $pendientes = Asignacion::where('user_id', $userId)->where('estado', 'pendiente')->count();
        $tasa = $total > 0 ? round(($completados / $total) * 100) : 0;

        $stats = [
            'total' => $total,
            'completados' => $completados,
            'pendientes' => $pendientes,
            'tasa' => $tasa,
        ];

        return view('dashboard.user', compact('stats'));
    }

    public function tab(Request $request)
    {
        $tab = $request->string('tab')->toString() ?: 'pending';
        $userId = Auth::id();

        $map = [
            'pending' => 'pendiente',
            'in_progress' => 'en_progreso',
            'completed' => 'completada',
            'stats' => 'stats',
        ];
        $estado = $map[$tab] ?? 'pendiente';

        if ($estado === 'stats') {
            // Render placeholder stats tab
            return view('dashboard.partials.user-tab-content', [
                'tab' => 'stats',
                'visionados' => null,
                'estado' => null,
            ]);
        }

        $visionados = Asignacion::with(['emision.canal', 'emision.obra'])
            ->where('user_id', $userId)
            ->where('estado', $estado)
            ->orderByDesc('created_at')
            ->paginate(10)
            ->appends($request->query());

        return view('dashboard.partials.user-tab-content', [
            'tab' => $tab,
            'visionados' => $visionados,
            'estado' => $estado,
        ]);
    }
}
