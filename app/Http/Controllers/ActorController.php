<?php

namespace App\Http\Controllers;

use App\Models\Actor;
use Illuminate\Http\Request;

class ActorController extends Controller
{
    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        // Ordering: prefix > word-boundary > substring; prefer NombreArtistico when present, then shorter labels, then alpha
        $qStart = $q . '%';
        $qWord = '% ' . $q . '%';
        $qLike = '%' . $q . '%';

        $orderSql = 'CASE '
            . 'WHEN NombreArtistico LIKE ? THEN 0 '
            . 'WHEN Nombre LIKE ? THEN 1 '
            . 'WHEN CONCAT(" ", NombreArtistico) LIKE ? THEN 2 '
            . 'WHEN CONCAT(" ", Nombre) LIKE ? THEN 3 '
            . 'WHEN NombreArtistico LIKE ? THEN 4 '
            . 'WHEN Nombre LIKE ? THEN 5 '
            . 'ELSE 9 END';

        $results = Actor::query()
            ->select(['NMActor','Nombre','NombreArtistico'])
            ->where(function($w) use ($qLike) {
                $w->where('Nombre', 'like', $qLike)
                  ->orWhere('NombreArtistico', 'like', $qLike);
            })
            ->orderByRaw($orderSql, [$qStart, $qStart, $qWord, $qWord, $qLike, $qLike])
            ->orderByRaw('CASE WHEN UPPER(COALESCE(NombreArtistico, Nombre, "")) LIKE "DOBLE %" THEN 1 ELSE 0 END')
            ->orderByRaw('CASE WHEN NombreArtistico IS NULL OR NombreArtistico = "" THEN 1 ELSE 0 END')
            ->orderByRaw('COALESCE(LENGTH(NombreArtistico), LENGTH(Nombre))')
            ->orderBy('NombreArtistico')
            ->orderBy('Nombre')
            ->limit(15)
            ->get()
            ->map(function($a){
                $nombre = trim(preg_replace('/[\p{C}]+/u', ' ', (string)$a->Nombre));
                $artistico = trim(preg_replace('/[\p{C}]+/u', ' ', (string)$a->NombreArtistico));
                $label = $artistico !== '' ? $artistico : $nombre;
                return [
                    'NMActor' => $a->NMActor,
                    'label' => $label,
                    'nombre' => $nombre,
                    'artistico' => $artistico,
                ];
            });

        return response()->json($results);
    }
}
