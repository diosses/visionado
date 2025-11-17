{{--
================================================================================
COMPONENTE: import-emisiones.blade.php
--------------------------------------------------------------------------------
Propósito: Disparar el flujo de importación masiva de emisiones desde un archivo
                     XLSX y mostrar feedback del proceso (progreso/resultado/errores).

Data attributes:
    - data-import-emisiones : wrapper del módulo.
    - data-import-file      : input file oculto (acepta .xlsx).
    - data-import-trigger   : botón visible que abre el selector de archivo.
    - data-import-msg       : zona donde se inyectan mensajes de estado.

Flujo esperado (JS externo):
    1. Click en trigger -> se hace click programático en input oculto.
    2. On change -> se sube el archivo vía fetch/XHR a emisiones.import.
    3. Respuesta OK -> se notifica éxito y se refresca pestaña material.
    4. Respuesta error -> se muestra mensaje en data-import-msg.

Extensiones posibles:
    - Mostrar barra de progreso si se soporta chunk upload.
    - Validación previa del tipo/mime y tamaño.
    - Plantilla de ejemplo descargable (link).
================================================================================
--}}
<div class="mb-4 flex items-center gap-3" data-import-emisiones>
    <input type="file" class="hidden" accept=".xlsx" data-import-file />
    <button type="button" class="inline-flex items-center px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors" data-import-trigger>
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
        </svg>
        Importar Emisiones (XLSX)
    </button>
    <div data-import-msg class="text-sm"></div>
</div>
