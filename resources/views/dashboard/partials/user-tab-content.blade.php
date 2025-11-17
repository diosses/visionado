{{--
Parcial: dashboard/partials/user-tab-content.blade.php
Renderiza el contenido interior (tabla o placeholder) para la pestaña activa
del panel de la visionadora. Se invoca vía AJAX (dashboard.user.tab) o en
render inicial si se quisiera server-side.

Entradas esperadas:
    - $tab      : slug de pestaña ('pending','in_progress','completed','stats')
    - $visionados: colección/paginador de asignaciones ya filtradas por estado.
    - $estado   : (opcional) estado textual final para el componente tabla cuando
                                no corresponde exactamente con los slugs externos.

Comportamiento:
    - 'stats' muestra placeholder (futuras métricas detalladas).
    - 'pending' fuerza estado="pendiente" en el componente.
    - Cualquier otro caso cae en el componente usando $estado o 'pendiente'.
--}}
@php($tab = $tab ?? request('tab','pending'))
@switch($tab)
    @case('stats')
        <div class="p-6 text-gray-600">
            <h3 class="text-lg font-semibold mb-2">Mis Estadísticas (próximamente)</h3>
            <p>Placeholder de estadísticas y gráficos.</p>
        </div>
        @break
    @case('pending')
        <x-visionadora.tabla-visionados-user :visionados="$visionados" estado="pendiente" />
        @break
    @default
        <x-visionadora.tabla-visionados-user :visionados="$visionados" :estado="$estado ?? 'pendiente'" />
@endswitch
