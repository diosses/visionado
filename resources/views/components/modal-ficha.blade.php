@php
/* =============================================================================
     COMPONENTE: modal-ficha.blade.php
     ---------------------------------------------------------------------------
     Propósito: Modal reutilizable para gestionar la "ficha" (elenco) de una obra.

     Parámetros:
         - obra (Obra requerido)           : Modelo de obra sobre el cual se edita.
         - listaElenco (Collection|null)   : Lista precargada de participaciones. Si
                                                                                 no se pasa, toma $obra->elencos (lazy).
         - idPrefix (string)               : Prefijo para construir el id del modal
                                                                                 (default 'modal-ficha'). Resultado:
                                                                                 "{idPrefix}-{NMObra}".

     Lógica principal:
         - Muestra chips (tags) de actores ya asociados.
         - Permite agregar actores vía typeahead (data-elenco="typeahead").
         - Cada actor agregado se refleja como input hidden NMActor[].
         - Al eliminar un chip se remueve su hidden y si no quedan actores se muestra
             mensaje vacío nuevamente.
         - En submit: se envía POST al endpoint obras.elenco.save.
         - Tras éxito: cierra modal y actualiza apariencia del botón resumen externo
             (si existe) indicando "Con ficha" / "Sin ficha".

     Dependencias JS globales esperadas:
         - window.Typeahead.attach(wrapper, { url })
         - window.simpleAjax (opcional) / fetch fallback
         - window.showToast (opcional para feedback)
         - window.ModalManager.close (opcional para cierre centralizado)

     Notas:
         - Se asume que la vista se incluye sólo cuando $obra no es null.
         - El selector del botón externo se intenta con varias heurísticas para
             soportar variantes de naming previas.
     ========================================================================== */
$idPrefix = $idPrefix ?? 'modal-ficha';
$listaElenco = $listaElenco ?? ($obra->elencos ?? collect());
@endphp

@if($obra)
<div id="{{ $idPrefix }}-{{ $obra->NMObra }}" class="modal-component fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black bg-opacity-50" data-modal-close></div>
    <div class="relative bg-white w-full max-w-2xl mx-auto mt-20 rounded-lg shadow-lg">
        <div class="p-6 border-b flex items-center justify-between">
            <h3 class="text-lg font-semibold">Elenco — {{ $obra->TituloObra }}</h3>
            <button type="button" class="text-gray-500" data-modal-close aria-label="Cerrar">✕</button>
        </div>
        <div class="p-6">
            <form method="POST" action="{{ route('obras.elenco.save', $obra->NMObra) }}" class="mt-2" data-elenco-form="{{ $obra->NMObra }}">
                @csrf
                <div class="space-y-3">
                    <div>
                        <p class="text-gray-500 text-sm mb-2 {{ $listaElenco->count() ? 'hidden' : '' }}" data-elenco="empty">Aún no hay actores cargados.</p>
                        <div class="flex flex-wrap items-center gap-2" data-elenco="tags">
                            @foreach($listaElenco as $item)
                                @php($label = $item->actor->display_name ?? ($item->actor->NombreArtistico ?? $item->actor->Nombre ?? 'Actor'))
                                <span class="inline-flex items-center gap-2 bg-blue-50 text-blue-800 text-xs px-2 py-1 rounded" data-id="{{ $item->NMActor }}">
                                    {{ $label }}
                                    <button type="button" class="text-blue-600" aria-label="Quitar">✕</button>
                                </span>
                            @endforeach
                        </div>
                        <div data-elenco="hidden">
                            @foreach($listaElenco as $item)
                                <input type="hidden" name="NMActor[]" value="{{ $item->NMActor }}">
                            @endforeach
                        </div>
                    </div>

                    <div class="flex flex-col md:flex-row md:items-center gap-3">
                        <div class="relative md:flex-1" data-elenco="typeahead" data-obra="{{ $obra->NMObra }}">
                            <input type="text" class="w-full border rounded px-3 py-2" placeholder="Buscar y agregar actores..." data-elenco="input">
                            <div class="absolute z-20 left-0 right-0 top-full mt-1 bg-white border rounded shadow hidden max-h-56 overflow-y-auto" data-elenco="dropdown"></div>
                        </div>
                        <div class="md:w-64">
                            <select name="tipo_participacion" class="w-full border rounded px-3 py-2">
                                <option value="Actuación">Actuación</option>
                                <option value="Danza">Danza</option>
                                <option value="Voz">Voz</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex justify-end">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Guardar elenco</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function initElencoBehaviors(root = document) {
        // Initialize for this specific modal
        const modal = root.querySelector('#{{ $idPrefix }}-{{ $obra->NMObra }}');
        if (!modal) return;

        // Use the shared Typeahead module for elenco inputs and listen for selections
        modal.querySelectorAll('[data-elenco="typeahead"]').forEach(wrapper => {
            if (wrapper.dataset.bound) return; 
            wrapper.dataset.bound = '1';
            
            const form = wrapper.closest('form');
            const tags = form?.querySelector('[data-elenco="tags"]');
            const emptyMsg = form?.querySelector('[data-elenco="empty"]');
            const hiddenContainer = form?.querySelector('[data-elenco="hidden"]');
            
            function escHtml(s) { 
                return (s||'').replace(/[&<>"]+/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m]||m)); 
            }
            
            function addTag(id, label) {
                if (!tags || !hiddenContainer) return;
                if (hiddenContainer.querySelector(`input[name="NMActor[]"][value="${id}"]`)) return;
                if (tags.querySelector(`[data-id="${id}"]`)) return;
                
                const chip = document.createElement('span');
                chip.className = 'inline-flex items-center gap-2 bg-blue-50 text-blue-800 text-xs px-2 py-1 rounded';
                chip.setAttribute('data-id', id);
                chip.innerHTML = `${escHtml(label)}<button type="button" class="text-blue-600" aria-label="Quitar">✕</button>`;
                
                chip.querySelector('button').addEventListener('click', () => {
                    chip.remove();
                    const hidden = hiddenContainer.querySelector(`input[name="NMActor[]"][value="${id}"]`);
                    if (hidden) hidden.remove();
                    
                    // Show empty message if no more actors
                    if (!hiddenContainer.querySelector('input[name="NMActor[]"]') && emptyMsg) {
                        emptyMsg.classList.remove('hidden');
                    }
                });
                
                tags.appendChild(chip);
                const hidden = document.createElement('input'); 
                hidden.type = 'hidden'; 
                hidden.name = 'NMActor[]'; 
                hidden.value = id; 
                hiddenContainer.appendChild(hidden);
                
                if (emptyMsg) emptyMsg.classList.add('hidden');
            }

            // Attach the shared typeahead behavior
            try { 
                window.Typeahead.attach(wrapper, { url: '{{ route('actores.search', [], false) }}' }); 
            } catch(e) { 
                console.error('Typeahead not available:', e); 
            }

            // When the typeahead selects, add tag
            wrapper.addEventListener('typeahead:select', (ev) => {
                const it = ev.detail || {};
                const id = it.NMActor ?? it.id;
                const label = it.label || it.nombre || '';
                if (id) addTag(id, label);
            });
        });

        // Handle elenco form submission (centralized helpers)
        modal.addEventListener('submit', async (e) => {
            const form = e.target.closest('form[data-elenco-form]');
            if (!form) return;

            e.preventDefault();
            const btn = document.activeElement;
            if (btn && btn.type === 'submit') btn.disabled = true;

            const fd = new FormData(form);
            const wrapper = form.querySelector('[data-elenco="typeahead"]');
            const obraId = wrapper?.dataset.obra;

            try {
                const data = await (window.simpleAjax ? window.simpleAjax(form.action, { method: 'POST', body: fd }) : (async () => {
                    const res = await fetch(form.action, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
                    if (!res.ok) throw new Error('Network');
                    try { return await res.json(); } catch { return {}; }
                })());

                if (window.showToast) { window.showToast('Elenco actualizado correctamente', 'success'); }

                // Close modal using ModalManager if available
                try {
                    if (window.ModalManager?.close) {
                        window.ModalManager.close('{{ $idPrefix }}-{{ $obra->NMObra }}');
                    } else {
                        modal.classList.add('hidden');
                    }
                } catch {}

                // Update the button appearance based on whether there are actors
                if (obraId) {
                    const hasAny = form.querySelectorAll('[data-elenco="hidden"] input[name="NMActor[]"]').length > 0;
                    const pill = document.querySelector(`button[data-modal-target="modal-ficha-${obraId}"]`) || 
                               document.querySelector(`button[data-modal-target="modal-ficha-obra-${obraId}"]`) || 
                               document.querySelector(`button[onclick*="'modal-ficha-${obraId}'"]`) || 
                               document.querySelector(`button[onclick*="'modal-ficha-obra-${obraId}'"]`);

                    if (pill) {
                        pill.classList.toggle('bg-green-100', hasAny);
                        pill.classList.toggle('text-green-800', hasAny);
                        pill.classList.toggle('bg-gray-100', !hasAny);
                        pill.classList.toggle('text-gray-700', !hasAny);
                        pill.textContent = hasAny ? 'Con ficha' : 'Sin ficha';
                    }
                }

            } catch(err) {
                console.error('Error submitting elenco form:', err);
                if (window.showToast) { window.showToast('Error al actualizar elenco', 'error'); }
            } finally {
                if (btn && btn.type === 'submit') btn.disabled = false;
            }
        });
    }

    // Initialize immediately
    initElencoBehaviors();

    // Re-initialize when ajax content swaps
    document.addEventListener('ajax:swapped', () => initElencoBehaviors());
});
</script>
@endif
