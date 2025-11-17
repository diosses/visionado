{{--
================================================================================
COMPONENTE: ficha-badge.blade.php
--------------------------------------------------------------------------------
Propósito: Renderizar un pequeño botón/insignia que indica si una obra tiene
                     "ficha" (información de elenco / imagen) y permitir abrir su modal.

Props:
    - obra (Obra|null)         : Modelo asociado; si es null el botón queda disabled.
    - variant ('estado'|'accion'): Modo de copy:
                * estado -> "Con ficha" / "Sin ficha"
                * accion -> "Editar ficha" / "Crear ficha"
    - labelTrue / labelFalse   : Sobrescribir textos (opcional).
    - idPrefix (string)        : Prefijo para id del modal (default 'modal-ficha-obra').
    - includeModal (bool)      : Si true, incluye el modal-ficha inline.
    - listaElenco (Collection) : Lista pre-cargada para evitar nueva consulta.

Detección de ficha:
    - Considera true si la obra tiene imagen (FichaImagen) o al menos un actor.
    - Usa *_count si está disponible para evitar cargar colección completa.

Notas:
    - El botón expone data-modal-target = id del modal, consumido por gestor JS.
    - Deja estilos Tailwind minimalistas y colores condicionales.
================================================================================
--}}
@props([
        'obra' => null,
        'variant' => 'estado',
        'labelTrue' => null,
        'labelFalse' => null,
        'idPrefix' => 'modal-ficha-obra',
        'includeModal' => false,
        'listaElenco' => null,
])

@php
    $obraObj = $obra ?? null;
    if(!$listaElenco && $obraObj) {
        $listaElenco = $obraObj->elencos ?? collect();
    }
    $elencoCount = (int)($obraObj->elencos_count ?? ($listaElenco ? $listaElenco->count() : 0));
    $hasFicha = (bool)($obraObj && ( ($obraObj->FichaImagen ?? false) || $elencoCount > 0 ));
    // Labels por defecto según variante
    if($labelTrue === null) {
        $labelTrue = $variant === 'accion' ? 'Editar ficha' : 'Con ficha';
    }
    if($labelFalse === null) {
        $labelFalse = $variant === 'accion' ? 'Crear ficha' : 'Sin ficha';
    }
    $label = $hasFicha ? $labelTrue : $labelFalse;
    $modalId = $obraObj ? ($idPrefix . '-' . $obraObj->NMObra) : null;
@endphp

<button type="button"
        @if($obraObj) data-modal-target="{{ $modalId }}" @endif
        @disabled(!$obraObj)
    class="inline-flex items-center px-2 py-1 text-xs rounded-full transition-colors disabled:opacity-50 disabled:cursor-not-allowed {{ $obraObj ? ($hasFicha ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700') : 'bg-gray-100 text-gray-500' }}"
        title="{{ $obraObj ? ($hasFicha ? 'Ver / Editar ficha' : 'Crear ficha') : 'No hay obra asociada' }}">
    {{ $obraObj ? $label : ($labelFalse ?? 'Sin ficha') }}
</button>
@if($obraObj && $includeModal)
    @include('components.modal-ficha', ['obra' => $obraObj, 'listaElenco' => $listaElenco, 'idPrefix' => $idPrefix])
@endif
