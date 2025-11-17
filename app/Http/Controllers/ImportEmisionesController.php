<?php

namespace App\Http\Controllers;

use App\Models\Canal;
use App\Models\Emision;
use App\Models\Obra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Imports\EmisionesImport;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Controlador de importación de Emisiones desde archivos XLSX.
 *
 * Requisitos:
 * - Hojas "Programas" y "Resumen" (nombres flexibles; también se admite por índice).
 * - En "Resumen", se tomarán sólo filas cuyo campo Visionado contenga "PARA VISIONAR".
 * - En "Programas", se importan sólo las filas cuyo título coincida exactamente con las
 *   recopiladas desde "Resumen".
 *
 * Implementación:
 * - Se usa PhpSpreadsheet directamente para evitar errores del iterador de filas de Laravel Excel.
 * - Se detectan encabezados de forma robusta (primera(s) filas no vacías) y se normalizan.
 */
class ImportEmisionesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // Form view no longer used; import is embedded in dashboard via POST

    /**
     * Procesa un archivo XLSX y crea/actualiza emisiones.
     * Devuelve JSON cuando la petición es AJAX (uso en el dashboard).
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx',
        ], [
            'file.mimes' => 'El archivo debe ser XLSX.',
        ]);

        // Preflight: verificar soporte del servidor para lectura XLSX
        if (!class_exists(\ZipArchive::class)) {
            $msg = 'El servidor no tiene la extensión PHP Zip habilitada (requerida para XLSX). Instala/activa php-zip.';
            if ($request->ajax() || $request->wantsJson()) return response()->json(['ok' => false, 'error' => $msg], 422);
            return back()->with('error', $msg);
        }
        if (!function_exists('simplexml_load_string')) {
            $msg = 'El servidor no tiene la extensión PHP XML habilitada (requerida para XLSX). Instala/activa php-xml.';
            if ($request->ajax() || $request->wantsJson()) return response()->json(['ok' => false, 'error' => $msg], 422);
            return back()->with('error', $msg);
        }
        if (!class_exists(\PhpOffice\PhpSpreadsheet\Reader\Xlsx::class)) {
            $msg = 'Biblioteca PhpSpreadsheet no disponible. Ejecuta composer install o verifica vendor/. (requerida para XLSX)';
            if ($request->ajax() || $request->wantsJson()) return response()->json(['ok' => false, 'error' => $msg], 422);
            return back()->with('error', $msg);
        }
        if (!extension_loaded('mbstring')) {
            // No bloquea, pero registramos aviso
            Log::warning('PHP extension mbstring is not loaded; XLSX import may behave unexpectedly.');
        }

        $import = new EmisionesImport();
        try {
            // Abrimos el XLSX directamente con PhpSpreadsheet para evitar errores del iterador de filas
            $path = $request->file('file')->getRealPath();
            $reader = new XlsxReader();
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);

            // Buscar hojas por nombre o por índice como fallback
            $programasSheet = $this->findSheet($spreadsheet, ['Programas', 'PROGRAMAS', 'programas'], 0);
            $resumenSheet   = $this->findSheet($spreadsheet, ['Resumen', 'RESUMEN', 'resumen'], 1);

            if (!$programasSheet && !$resumenSheet) {
                throw new \RuntimeException('No se encontraron las hojas "Programas" ni "Resumen".');
            }

            if ($resumenSheet) {
                $rows = $this->sheetToAssocRows($resumenSheet, $import);
                foreach ($rows as $row) {
                    $data = $import->normalizeRow($row);
                    $visionado = \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii(trim((string)($data['visionado'] ?? ''))));
                    if ($visionado !== '' && str_contains($visionado, 'para visionar')) {
                        $titulo = $import->pickTitulo($data);
                        if ($titulo !== '') $import->allowTitle($titulo);
                    }
                }
            }

            if ($programasSheet) {
                $rows = $this->sheetToAssocRows($programasSheet, $import);
                foreach ($rows as $row) {
                    $data = $import->normalizeRow($row);
                    $import->pushProgramRow($data);
                }
            }

            // Ejecutar importación filtrada
            $import->finalizeImport();

        } catch (\Throwable $e) {
            Log::error('Import XLSX failed', [
                'class' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            if ($request->ajax() || $request->wantsJson()) {
                $hint = ' Verifica que el archivo sea .xlsx válido y que PHP tenga habilitada la extensión Zip/Xml.';
                return response()->json(['ok' => false, 'error' => $e->getMessage() . $hint], 422);
            }
            return back()->with('error', 'Error al importar: '.$e->getMessage());
        }

        $msg = "Importación completada. Creadas: {$import->created}, Actualizadas: {$import->updated}, Omitidas: {$import->skipped}";
        if ($import->errors) { $msg .= ' | Errores: '.min(3, count($import->errors)).' mostrados'; }
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $msg,
                'created' => $import->created,
                'updated' => $import->updated,
                'skipped' => $import->skipped,
                'errors' => array_slice($import->errors, 0, 10),
            ]);
        }
        return back()->with('success', $msg)->with('import_errors', array_slice($import->errors, 0, 3));
    }

    // Ayudantes CSV eliminados; se utiliza PhpSpreadsheet + EmisionesImport

    // Capabilities endpoint removed; client preflight no longer calls this

    /** Devuelve la hoja por alguno de los nombres indicados (insensible a mayúsculas) o por índice. */
    private function findSheet(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet, array $names, int $fallbackIndex): ?Worksheet
    {
        foreach ($names as $name) {
            $s = $spreadsheet->getSheetByName($name);
            if ($s) return $s;
            // probar variantes simples
            $s = $spreadsheet->getSheetByName(mb_strtoupper($name)); if ($s) return $s;
            $s = $spreadsheet->getSheetByName(mb_strtolower($name)); if ($s) return $s;
            $s = $spreadsheet->getSheetByName(ucfirst(mb_strtolower($name))); if ($s) return $s;
        }
        // fallback por índice si existe
        $count = $spreadsheet->getSheetCount();
        if ($fallbackIndex >= 0 && $fallbackIndex < $count) return $spreadsheet->getSheet($fallbackIndex);
        return null;
    }

    /** Lee una hoja a filas asociativas detectando el encabezado de manera robusta. */
    private function sheetToAssocRows(Worksheet $sheet, EmisionesImport $import): array
    {
        // toArray: nullValue, calculateFormulas, formatData, returnCellRef(false = índices numéricos)
        $rows = $sheet->toArray(null, true, true, false);

        // Limpiar filas completamente vacías del inicio
        while ($rows && count(array_filter($rows[0], fn($v) => $v !== null && $v !== '')) === 0) {
            array_shift($rows);
        }
        if (!$rows) return [];

    // Intentar hallar encabezado en las primeras 5 filas
        $headerRow = $rows[0]; $headerIndex = 0;
        $searchLimit = min(5, count($rows));
        for ($i = 0; $i < $searchLimit; $i++) {
            $vals = array_values(array_filter($rows[$i], fn($v) => $v !== null && $v !== ''));
            if (count($vals) < 2) continue;
            $joined = \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii(implode(' ', $vals)));
            if (str_contains($joined, 'titulo') || str_contains($joined, 'programa') || str_contains($joined, 'fecha') || str_contains($joined, 'visionado')) {
                $headerRow = $rows[$i];
                $headerIndex = $i;
                break;
            }
        }

        // Normalizar encabezados
        $headers = array_map(fn($h) => $import->normalizeHeader((string)$h), $headerRow);
        // Asegurar al menos un nombre por columna
        foreach ($headers as $idx => $h) { if ($h === '') $headers[$idx] = 'col'.($idx+1); }

    // Construir filas asociativas
        $assoc = [];
        for ($i = $headerIndex + 1; $i < count($rows); $i++) {
            $vals = array_values($rows[$i]);
            if (empty(array_filter($vals, fn($v) => $v !== null && $v !== ''))) continue;
            $vals = array_pad($vals, count($headers), null);
            $assoc[] = array_combine($headers, array_slice($vals, 0, count($headers)));
        }
        return $assoc;
    }
}
