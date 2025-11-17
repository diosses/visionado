// --- Helpers y bindings globales migrados desde Blade ---
if (typeof window !== 'undefined') {
    // Typeahead binding (auto rebind tras ajax swap)
    function bindTypeahead() {
        const modal = document.getElementById('modal-identificar-obra');
        if (!modal) return;
        const wrapper = modal.querySelector('[data-obra-typeahead]');
        if (!wrapper || wrapper.dataset.bound) return;
        const url = wrapper.getAttribute('data-typeahead-url');
        if (window.Typeahead?.attach && url) {
            try { window.Typeahead.attach(wrapper, { url }); wrapper.dataset.bound = '1'; } catch(e) { /* noop */ }
        }
    }
    document.addEventListener('DOMContentLoaded', bindTypeahead);
    document.addEventListener('ajax:swapped', bindTypeahead);

    // setObraSeleccionContext global
    window.setObraSeleccionContext = function(context) {
        const header = document.getElementById('selected-obra-header');
        if (header) {
            if (context === 'sugerida' || context === 'fuzzy') {
                header.textContent = 'Obra sugerida:';
            } else {
                header.textContent = 'Obra seleccionada:';
            }
        }
    };

    // mostrarSugerenciaAutomatica global
    window.mostrarSugerenciaAutomatica = function(sugerencia) {
        if (!sugerencia) return;
        const sugId = sugerencia.NMObra || sugerencia.obra_id || sugerencia.id;
        const sugTitulo = sugerencia.TituloObra || sugerencia.titulo || sugerencia.label;
        if (!sugId || !sugTitulo) return;
        // No mostrar sugerencia si ya hay una obra asignada actualmente
        const currentObra = document.getElementById('current-obra');
        if (currentObra && !currentObra.classList.contains('hidden')) {
            return;
        }
        const panel = document.getElementById('selected-obra-info');
        const titulo = document.getElementById('selected-obra-titulo');
        const detalles = document.getElementById('selected-obra-detalles');
        const btnAsignar = document.getElementById('btn-asignar-obra');
        if (panel && titulo && detalles) {
            titulo.textContent = sugTitulo;
            if (typeof sugerencia.score !== 'undefined') {
                detalles.textContent = `ID: ${sugId} • Score: ${sugerencia.score}%`;
            } else {
                detalles.textContent = `ID: ${sugId}`;
            }
            panel.classList.remove('hidden');
            panel.dataset.obraId = sugId;
            window.setObraSeleccionContext('sugerida');
            if (btnAsignar) btnAsignar.disabled = false;
            const searchInput = document.getElementById('obra-search');
            // Evitar prellenar el input si ya hay obra asignada (redundante, pero seguro)
            if (searchInput && (!currentObra || currentObra.classList.contains('hidden'))) {
                searchInput.value = sugTitulo;
            }
            // Post-setup: run validation to ensure container/general isn't assignable directly
            try { window.modalIdentificarObra?.validateSelection?.(); } catch(_) { /* noop */ }
        }
    };

    // Botón crear obra (delegado y robusto frente a swaps)
    if (!document.body.dataset.boundOpenCrearObraDelegated) {
        document.body.dataset.boundOpenCrearObraDelegated = '1';
        document.addEventListener('click', function(e) {
            const btnCreateObra = e.target.closest('#btn-create-obra');
            if (!btnCreateObra) return;
            e.preventDefault();
            // Construir título de sugerencia desde el modal identificar
            const ident = document.getElementById('modal-identificar-obra');
            const emisionTituloRaw = document.getElementById('modal-emision-titulo')?.textContent?.trim() || '';
            const emisionTitulo = (emisionTituloRaw === '-' || emisionTituloRaw === '—') ? '' : emisionTituloRaw;
            const groupTitulo = ident?.dataset?.groupTitle || '';
            const titulo = emisionTitulo || groupTitulo || '';
            const bulkCtx = !!ident?.dataset?.bulkContext;

            // Cerrar identificar mediante ModalManager para mantener estados consistentes
            try { window.ModalManager?.close('modal-identificar-obra'); } catch {}

            // Abrir modal de creación con opciones estables
            try { window.openModalObra?.({ titulo, mostrarAnidar: !bulkCtx }); } catch {}
        });
    }

    // Removed checkbox/input flows for creating chapter and editing original title.

    // Evento obra:selected para cambiar contexto
    document.addEventListener('obra:selected', function(event) {
        if (event.detail && event.detail.manual) {
            window.setObraSeleccionContext('manual');
        }
    });
}
/**
 * Modal Identificar Obra - Módulo dedicado
 * Maneja toda la lógica del modal para identificar y asignar obras a emisiones
 */

class ModalIdentificarObra {
    constructor() {
        this.modalId = 'modal-identificar-obra';
        this.isInitialized = false;
        this.lastSelected = null;
    }

    // Método principal para abrir el modal (reemplaza window.openIdentificarObraModal)
    open(data = {}) {
        // Guardar data para uso interno
        if (typeof window !== 'undefined') {
            window._lastIdentificarObraData = data;
        }

        // Always reset initialization and UI state before opening
        this.isInitialized = false;
        ModalManager.open(this.modalId, data);
        this.resetModalState();

        // Remove all context flags and UI panels before setup
        const modal = document.getElementById(this.modalId);
        if (modal) {
            delete modal.dataset.bulkContext;
            delete modal.dataset.groupTitle;
            // Set optional list of known series container NMObra codes for validation
            if (Array.isArray(data.series_nmobras)) {
                try { modal.setAttribute('data-series-nmobras', JSON.stringify(data.series_nmobras)); } catch(_) { /* noop */ }
            } else {
                modal.removeAttribute('data-series-nmobras');
            }
        }

        // Setup context and UI for this call
        this._populateAll(data);
        this._bindAll(data);

        // Ensure all panels (capítulos, sugerencia, etc.) are toggled according to current data
        // (resetModalState already hides them, _populateAll will show as needed)

        // Si ya hay obra asignada, no enfocar el input de búsqueda automáticamente
        setTimeout(() => {
            const currentObra = document.getElementById('current-obra');
            if (!(currentObra && !currentObra.classList.contains('hidden'))) {
                this._focusInput('obra-search');
            }
        }, 100);
    }

    // Centraliza el poblamiento de datos
    _populateAll(data) {
        this.populateEmisionInfo(data);
        this.handleObraAsignada(data.obra);
        this.handleSugerencia(data.sugerencia || data.suggestion);
        this.setupBulkContext(data);
        this.setupCrearSerieButton(data);
    }

    // Centraliza el bindeo de eventos
    _bindAll(data) {
        if (this.isInitialized) return;
        this.bindTypeaheadEvents();
        this.bindFormSubmit(data);
        this.bindClearSelection();
        this.bindUnassignButton(data.obra);
        this.bindEditTitle(data);
        this.bindCapSelectChange();
        this.isInitialized = true;
    }

    // Helper para enfocar input
    _focusInput(id) {
        const el = document.getElementById(id);
        if (el && el.focus) el.focus();
    }

    // Poblar información de la emisión
    populateEmisionInfo(data) {
        const titleEl = document.getElementById('modal-emision-titulo');
        if (titleEl) {
            // Si viene group_title (padre), mostrarlo como prioridad
            if (data.group_title) {
                titleEl.textContent = data.group_title;
            } else {
                titleEl.textContent = data.titulo || '-';
            }
        }

        const metaDiv = document.getElementById('modal-emision-meta');
        if (metaDiv) {
            const canal = data.canal?.nombre;
            const fecha = this.formatFechaDMY(data.fecha_emision);
            const hora = this.formatHorario(data.hora_inicio, data.hora_fin);
            let extra = '';
            if (Array.isArray(data.group_emision_ids) && data.group_emision_ids.length > 1) {
                extra = `<span class='font-semibold text-indigo-700'>${data.group_emision_ids.length} emisiones agrupadas</span>`;
            }
            metaDiv.innerHTML = [
                canal ? `<span>Canal: <span class='font-medium text-gray-700'>${canal}</span></span>` : '',
                fecha ? `<span>Fecha: <span class='font-medium text-gray-700'>${fecha}</span></span>` : '',
                hora ? `<span>Horario: <span class='font-medium text-gray-700'>${hora}</span></span>` : '',
                extra
            ].filter(Boolean).join('<span class="mx-1">·</span>');
        }
    }

    // Manejar obra ya asignada (panel verde)
    handleObraAsignada(obra) {
        const obraDiv = document.getElementById('current-obra');
        if (!obra || !obraDiv) {
            if (obraDiv) obraDiv.classList.add('hidden');
            return;
        }

        const titleEl = document.getElementById('current-obra-titulo');
        const detailsEl = document.getElementById('current-obra-detalles');

        if (titleEl) titleEl.textContent = obra.titulo || obra.TituloObra || '-';
        if (detailsEl) {
            const details = this.buildObraDetails(obra);
            detailsEl.textContent = details || '-';
        }

        // Guardar ID para acciones (editar/desasignar)
        try { obraDiv.dataset.obraId = obra.id || obra.NMObra || obra.nmobra || ''; } catch(_) {}

        obraDiv.classList.remove('hidden');
        this.bindUnassignButton(obra);
        this.bindEditObraButton(obra);

        // Si hay obra asignada, limpiar cualquier selección o prefill del buscador
        const selectedPanel = document.getElementById('selected-obra-info');
        if (selectedPanel) selectedPanel.classList.add('hidden');
        const searchInput = document.getElementById('obra-search');
        if (searchInput) searchInput.value = '';
    }

    // Manejar sugerencia automática
    handleSugerencia(sugerencia) {
        if (!sugerencia || !window.mostrarSugerenciaAutomatica) return;
        // Normalizar forma de sugerencia
        if (!('NMObra' in sugerencia) && (sugerencia.obra_id || sugerencia.id)) {
            sugerencia = {
                NMObra: sugerencia.obra_id || sugerencia.id,
                TituloObra: sugerencia.titulo || sugerencia.label || '',
                score: sugerencia.score
            };
        }
        // Si ya hay una obra asignada visible, no mostrar la sugerencia
        const currentObra = document.getElementById('current-obra');
        if (currentObra && !currentObra.classList.contains('hidden')) return;
        // Usar la función global existente
        window.mostrarSugerenciaAutomatica(sugerencia);
        // Si la sugerencia corresponde a una obra general (padre), mostrar capítulos existentes y también ofrecer "Añadir a Serie"
        if (sugerencia && sugerencia.NMObra) {
            this.loadCapitulosIfPadre?.({ NMObra: sugerencia.NMObra, id: sugerencia.NMObra, is_padre: true, TituloObra: sugerencia.TituloObra });
        }
    }

    // Setup del contexto bulk/grupo
    setupBulkContext(data) {
        const modal = document.getElementById(this.modalId);
        if (!modal) return;

        // Marcar contexto bulk
        const isBulk = (Array.isArray(data.group_emision_ids) && data.group_emision_ids.length) || 
                      (Array.isArray(data.bulk_ids) && data.bulk_ids.length);
        
        if (isBulk) {
            modal.dataset.bulkContext = '1';
        } else {
            delete modal.dataset.bulkContext;
        }

        // Cambiar texto del botón submit si es bulk
        const submitBtn = document.getElementById('btn-asignar-obra');
        if (submitBtn && Array.isArray(data.bulk_ids)) {
            submitBtn.textContent = `Asignar a ${data.bulk_ids.length} emisiones`;
        } else if (submitBtn) {
            submitBtn.textContent = 'Asignar obra';
        }

        // Guardar group title para botón crear obra
        if (data.group_title) {
            modal.dataset.groupTitle = data.group_title;
        } else {
            delete modal.dataset.groupTitle;
        }
    }

    // Setup del botón Crear Serie
    setupCrearSerieButton(data) {
        const wrapper = document.getElementById('identificar-crear-serie-wrap');
        const button = document.getElementById('btn-open-wizard-serie');
        if (!wrapper || !button) return;
        // Siempre visible, siempre sin preselección; solo pasa emisiones si hay grupo o single
        wrapper.classList.remove('hidden');
        button.textContent = 'Crear Serie';
        if (!button.dataset.boundCrearSerie) {
            button.dataset.boundCrearSerie = '1';
            button.addEventListener('click', () => {
                ModalManager.close(this.modalId);
                // Construir emisiones del contexto (grupo o individual)
                const emis = [];
                if (Array.isArray(data.group_emision_ids) && data.group_emision_ids.length) {
                    for (const id of data.group_emision_ids) {
                        const row = document.querySelector(`[data-nmemision="${id}"]`);
                        if (row) {
                            const obj = {}; Object.assign(obj, row.dataset);
                            row.querySelectorAll('td[data-field]').forEach(cell => { const f = cell.getAttribute('data-field'); if (f) obj[f] = cell.textContent.trim(); });
                            obj.NMEmision = obj.NMEmision || row.getAttribute('data-nmemision') || id;
                            emis.push(obj.NMEmision ? obj : { NMEmision: id });
                        } else { emis.push({ NMEmision: id }); }
                    }
                } else {
                    const id = data.id || data.emisionId || data.emision_id;
                    if (id) {
                        const row = document.querySelector(`[data-nmemision="${id}"]`);
                        if (row) {
                            const obj = {}; Object.assign(obj, row.dataset);
                            row.querySelectorAll('td[data-field]').forEach(cell => { const f = cell.getAttribute('data-field'); if (f) obj[f] = cell.textContent.trim(); });
                            obj.NMEmision = obj.NMEmision || row.getAttribute('data-nmemision') || id;
                            emis.push(obj.NMEmision ? obj : { NMEmision: id });
                        } else { emis.push({ NMEmision: id }); }
                    }
                }
                // Abrir wizard sin preselección de serie
                window.openSeriesWizard?.(emis);
            });
        }
    }


    // Limpia todos los campos y paneles del modal
    resetModalState() {
        [
            'selected-obra-info',
            'current-obra',
            'obra-capitulos-panel'
        ].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.classList.add('hidden');
        });
        [
            'selected-obra-titulo',
            'selected-obra-detalles',
            'current-obra-titulo',
            'current-obra-detalles',
            'modal-emision-titulo',
            'modal-emision-meta'
        ].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.textContent = '-';
        });
        const submitBtn = document.getElementById('btn-asignar-obra');
        if (submitBtn) submitBtn.disabled = true;
        const wrapper = document.querySelector(`#${this.modalId} [data-obra-typeahead]`);
        if (wrapper) delete wrapper.dataset.selected;
        // Limpiar dataset de selección previa para no auto-seleccionar al reabrir
        const selectedPanel = document.getElementById('selected-obra-info');
        if (selectedPanel && selectedPanel.dataset) delete selectedPanel.dataset.obraId;
        // Limpiar input de búsqueda y capítulos
        const searchInput = document.getElementById('obra-search');
        if (searchInput) searchInput.value = '';
    const capsSelect = document.getElementById('obra-capitulos-select');
    if (capsSelect) { capsSelect.innerHTML = ''; capsSelect.disabled = false; }
        // Clear local state and warnings
        this.lastSelected = null;
        this.clearWarning();
    }

    // Event listeners del typeahead
    bindTypeaheadEvents() {
        const wrapper = document.querySelector(`#${this.modalId} [data-obra-typeahead]`);
        if (!wrapper || wrapper.dataset.identBound) return;

        wrapper.dataset.identBound = '1';
        wrapper.addEventListener('typeahead:select', (ev) => {
            const item = ev.detail || {};
            this.selectObra(item);
            // Si es un padre (obra general), mostrar panel de capítulos y botón "Añadir a Serie"
            this.loadCapitulosIfPadre(item);
            // Persist last selected obra data for later use (full JSON if provided by typeahead)
            try {
                wrapper.dataset.selected = item.NMObra || item.id || '';
                // Guardar JSON completo de forma segura (URL encoded para evitar conflictos)
                wrapper.dataset.selectedJson = encodeURIComponent(JSON.stringify(item));
                window._identificarObraSelectedItem = item; // fallback global
            } catch(_) { /* noop */ }
            // Validate selection after binding
            this.validateSelection();
        });

        // Si la blade setea dataset.obraId en el panel seleccionado, reflejarlo para el módulo
        const selectedPanel = document.getElementById('selected-obra-info');
        if (selectedPanel && selectedPanel.dataset.obraId) {
            const obraId = selectedPanel.dataset.obraId;
            const label = document.getElementById('selected-obra-titulo')?.textContent || '';
            this.selectObra({ NMObra: obraId, TituloObra: label, isAutomatic: true });
        }
    }

    // Seleccionar obra del typeahead
    selectObra(item) {
        const elements = {
            panel: document.getElementById('selected-obra-info'),
            title: document.getElementById('selected-obra-titulo'),
            details: document.getElementById('selected-obra-detalles'),
            button: document.getElementById('btn-asignar-obra'),
            wrapper: document.querySelector(`#${this.modalId} [data-obra-typeahead]`)
        };

        if (elements.panel) elements.panel.classList.remove('hidden');
        if (elements.title) elements.title.textContent = item.label || item.TituloObra || '';
        if (elements.details) {
            elements.details.textContent = [item.Genero, item.AnioProduccion].filter(Boolean).join(' · ');
        }
        if (elements.button) elements.button.disabled = false;
    if (elements.wrapper) elements.wrapper.dataset.selected = item.NMObra || item.id || '';
    if (elements.panel) elements.panel.dataset.obraId = item.NMObra || item.id || '';

        // Cambiar contexto a "seleccionada" si fue manual
        if (window.setObraSeleccionContext && !item.isAutomatic) {
            window.setObraSeleccionContext('manual');
        }
        // Record last selected for validation
        this.lastSelected = item || null;
        // Validate after selection
        this.validateSelection();
    }

    // Cargar y mostrar capítulos para la obra seleccionada (tratada como padre) según contexto
    async loadCapitulosIfPadre(item) {
        const panel = document.getElementById('obra-capitulos-panel');
        const select = document.getElementById('obra-capitulos-select');
        // Mostrar panel de capítulos para cualquier obra general seleccionada (las búsquedas excluyen capítulos)
        if (!item || (!item.NMObra && !item.id)) {
            this.hideChaptersUI();
            return;
        }

        // Si el modal viene disparado desde un ELEMENTO PADRE (contexto de grupo/parent), ocultar capítulos aquí.
        const modal = document.getElementById(this.modalId);
        const isParentContext = !!(modal && (modal.dataset.groupTitle || (Array.isArray((ModalManager.getData?.(this.modalId)||{}).group_emision_ids) && (ModalManager.getData(this.modalId).group_emision_ids.length>0))));
        if (isParentContext) {
            this.hideChaptersUI();
            // Mostrar botón inline "Añadir a Serie" con preselección
            this.showAddToSerieInline(item);
            return;
        }

        // Contexto normal: Mostrar botón/acción "Añadir a Serie" en header sin preselección; aquí mantenemos opción de capítulos
        this.hideAddToSerieInline();

        // Mostrar panel y cargar capítulos existentes
        if (panel) panel.classList.remove('hidden');
        if (select) {
            // Placeholder mientras carga
            select.innerHTML = '<option value="" disabled selected>Cargando capítulos…</option>';
            select.disabled = false;
        }

        // --- Remove checkbox and text field, add button ---
        let btnCrearCap = document.getElementById('btn-crear-capitulo');
        const ensureButtonClasses = (btn) => {
            // Compact button, same height as select, balanced in a flex row
            btn.className = 'inline-flex items-center justify-center px-3 text-xs bg-blue-600 text-white rounded border border-blue-600 hover:bg-blue-700 flex-none';
            // Remove any explicit width/spacing that makes it too big
            btn.style.width = 'auto';
            btn.style.marginTop = '0';
            btn.style.paddingTop = '0';
            btn.style.paddingBottom = '0';
            btn.style.lineHeight = '1';
        };
        if (!btnCrearCap) {
            btnCrearCap = document.createElement('button');
            btnCrearCap.id = 'btn-crear-capitulo';
            btnCrearCap.type = 'button';
            ensureButtonClasses(btnCrearCap);
            btnCrearCap.textContent = 'Crear nuevo capítulo';
            // Insert after select inside the same flex row
            if (select && select.parentNode) {
                select.parentNode.insertBefore(btnCrearCap, select.nextSibling);
            }
        } else {
            ensureButtonClasses(btnCrearCap);
        }
        // Match button height to the select's rendered height
        try {
            const h = select?.getBoundingClientRect?.().height;
            if (h && h > 0) { btnCrearCap.style.height = `${h}px`; }
        } catch(_) { /* noop */ }
        // Always show the button, hide old checkbox/text if present
        btnCrearCap.style.display = '';
        const chkCrear = document.getElementById('chk-crear-capitulo');
        const inputCrear = document.getElementById('input-crear-capitulo');
        if (chkCrear) chkCrear.style.display = 'none';
        if (inputCrear) inputCrear.style.display = 'none';
        // Remove any helper text for the old input
        const helper = select?.parentNode?.querySelector('.crear-cap-helper');
        if (helper) helper.style.display = 'none';

        // Delegated event for robustness
        if (!document.body.dataset.boundCrearCapituloBtn) {
            document.body.dataset.boundCrearCapituloBtn = '1';
            document.addEventListener('click', async function(e) {
                const btn = e.target.closest('#btn-crear-capitulo');
                if (!btn) return;
                e.preventDefault();
                // Get parent obra data from current selection
                const selectObra = document.getElementById('obra-capitulos-select');
                const typeaheadWrap = document.querySelector(`#${modalIdentificarObra.modalId} [data-obra-typeahead]`);
                const searchInput = document.getElementById('obra-search');
                // Determine parent obra primarily from typeahead selection and search input
                let parentObra = {};
                const parentIdFromTypeahead = typeaheadWrap?.dataset?.selected;
                const parentTitleFromSearch = searchInput?.value?.trim();
                if (parentIdFromTypeahead) {
                    parentObra.NMObra = parentIdFromTypeahead;
                    if (parentTitleFromSearch) parentObra.TituloObra = parentTitleFromSearch;
                    // Try to enrich with full JSON from stored dataset / global selection
                    try {
                        const encoded = typeaheadWrap?.dataset?.selectedJson;
                        if (encoded) {
                            const parsed = JSON.parse(decodeURIComponent(encoded));
                            if (parsed && typeof parsed === 'object') Object.assign(parentObra, parsed);
                        } else if (window._identificarObraSelectedItem) {
                            Object.assign(parentObra, window._identificarObraSelectedItem);
                        }
                    } catch(_) { /* noop */ }
                } else {
                    // Fallback: use the selected panel
                    const selectedPanel = document.getElementById('selected-obra-info');
                    if (selectedPanel && selectedPanel.dataset.obraId) {
                        parentObra.NMObra = selectedPanel.dataset.obraId;
                        parentObra.TituloObra = document.getElementById('selected-obra-titulo')?.textContent || parentTitleFromSearch || '';
                    }
                }
                // Last fallback: if a real capítulo option is chosen, derive parent label (but do NOT override id)
                if ((!parentObra.TituloObra || !parentObra.NMObra) && selectObra && selectObra.value) {
                    // Keep NMObra from earlier; only use the text as label if missing
                    if (!parentObra.TituloObra) parentObra.TituloObra = selectObra.selectedOptions[0]?.textContent || '';
                }
                // Get emission title for prefill
                const emisionTituloRaw = document.getElementById('modal-emision-titulo')?.textContent?.trim() || '';
                const emisionTitulo = (emisionTituloRaw === '-' || emisionTituloRaw === '—') ? '' : emisionTituloRaw;

                // If we only have NMObra and minimal fields, try to fetch full JSON details before opening
                if (parentObra && parentObra.NMObra && window.modalObras?.fetchObraDetails) {
                    try {
                        const details = await window.modalObras.fetchObraDetails(parentObra.NMObra);
                        if (details && typeof details === 'object') {
                            // Keep original title if we already set it from search input
                            const keepTitle = parentObra.TituloObra;
                            parentObra = { ...details };
                            if (keepTitle) parentObra.TituloObra = keepTitle;
                        }
                    } catch (_) { /* non-fatal */ }
                }

                // Open modal-obras with prefilled data
                if (window.openModalObra) {
                    window.openModalObra({
                        titulo: emisionTitulo,
                        mostrarAnidar: true,
                        forzarAnidar: true,
                        padreId: parentObra.NMObra,
                        padreLabel: parentObra.TituloObra,
                        parentObra: parentObra
                    });
                }
                // Close identificar modal for consistency
                try { window.ModalManager?.close('modal-identificar-obra'); } catch {}
            });
        }

        try {
            const parentId = item.NMObra || item.id;
            if (!parentId) return;
            const lista = await simpleAjax(`/obras/${parentId}/capitulos`, { method: 'GET' });
            // Normalizar respuesta: array de objetos con id/NMObra y titulo/TituloObra
            const capitulos = Array.isArray(lista) ? lista : (Array.isArray(lista?.data) ? lista.data : []);
            if (select) {
                if (!capitulos.length) {
                    select.innerHTML = '<option value="" disabled selected>Sin capítulos</option>';
                } else {
                    const opts = ['<option value="" disabled selected>Selecciona un capítulo…</option>'].concat(
                        capitulos.map(c => {
                            const id = c.NMObra || c.id;
                            const tit = c.TituloObra || c.titulo || c.label || `Capítulo ${id}`;
                            return `<option value="${id}">${tit}</option>`;
                        })
                    );
                    select.innerHTML = opts.join('');
                }
                // Re-validate after loading options
                this.validateSelection();
            }
        } catch (err) {
            console.warn('No se pudieron cargar capítulos', err);
            if (select) select.innerHTML = '<option value="" disabled selected>Error cargando capítulos</option>';
        }
    }

    // Detectar si un título parece "OBRA GENERAL"
    isGeneralTitle(text) {
        if (!text) return false;
        const t = String(text).toUpperCase();
        return t.includes('(OBRA GENERAL)') || t.includes('[OBRA GENERAL]') || /\bOBRA\s+GENERAL\b/.test(t);
    }

    // Chequear si el item es un contenedor de serie
    isSeriesContainer(item) {
        if (!item) return false;
        const nmobra = item.NMObra || item.id || item.obra_id || item.value;
        const nmserie = item.NMSerie || item.nm_serie || item.nmserie;
        if (nmobra && nmserie && String(nmobra) === String(nmserie)) return true;
        if (item.is_padre) return true;
        // Dataset del modal con lista de NMObra que son series
        const modal = document.getElementById(this.modalId);
        const raw = modal?.getAttribute('data-series-nmobras');
        if (raw && nmobra) {
            try {
                const arr = JSON.parse(raw);
                if (Array.isArray(arr)) {
                    const hit = arr.map(String).includes(String(nmobra));
                    if (hit) return true;
                }
            } catch(_) { /* ignore */ }
        }
        return false;
    }

    getWarningEl() {
        return document.getElementById('obra-selection-warning');
    }

    clearWarning() {
        const el = this.getWarningEl();
        if (el) {
            el.textContent = '';
            el.classList.add('hidden');
        }
    }

    showWarning(msg) {
        const el = this.getWarningEl();
        if (el) {
            el.textContent = msg;
            el.classList.remove('hidden');
        } else {
            try { showToast(msg, 'warning'); } catch(_) { /* noop */ }
        }
    }

    bindCapSelectChange() {
        const capSelect = document.getElementById('obra-capitulos-select');
        if (!capSelect || capSelect.dataset.boundChange) return;
        capSelect.dataset.boundChange = '1';
        capSelect.addEventListener('change', () => this.validateSelection());
    }

    // Valida la selección actual y habilita/deshabilita el submit
    validateSelection() {
        const submitBtn = document.getElementById('btn-asignar-obra');
        const selectedPanel = document.getElementById('selected-obra-info');
        const capsPanel = document.getElementById('obra-capitulos-panel');
        const capSelect = document.getElementById('obra-capitulos-select');

        // Default: require a selection
        if (!selectedPanel || selectedPanel.classList.contains('hidden')) {
            if (submitBtn) submitBtn.disabled = true;
            this.clearWarning();
            return false;
        }

        const item = this.lastSelected || {};
        const title = (item.TituloObra || item.label || '').trim();
        const isGeneral = this.isGeneralTitle(title);
        const isContainer = this.isSeriesContainer(item);

        // If a chapter is explicitly chosen, allow submit
        const chapterChosen = !!(capsPanel && !capsPanel.classList.contains('hidden') && capSelect && capSelect.value);

        if ((isGeneral || isContainer) && !chapterChosen) {
            const msg = isGeneral
                ? 'Esta obra está marcada como "OBRA GENERAL". Selecciona un capítulo específico o crea uno nuevo.'
                : 'La obra seleccionada es un contenedor de serie. Debes elegir un episodio/hijo específico.';
            this.showWarning(msg);
            if (submitBtn) submitBtn.disabled = true;
            return false;
        }

        // OK to submit
        this.clearWarning();
        if (submitBtn) submitBtn.disabled = false;
        return true;
    }

    // Mostrar botón inline de "Añadir a Serie" bajo "Quitar selección" con preselección
    showAddToSerieInline(obra) {
        const wrap = document.getElementById('add-to-serie-inline-wrap');
        const btn = document.getElementById('btn-add-to-serie-inline');
        if (!wrap || !btn) return;
        wrap.classList.remove('hidden');
        // Guardar datos para preselección
        btn.dataset.preselectSerieId = String(obra.NMObra || obra.id || '');
        btn.dataset.preselectSerieTitulo = obra.label || obra.TituloObra || '';
        if (!btn.dataset.boundInline) {
            btn.dataset.boundInline = '1';
            btn.addEventListener('click', () => {
                const data = ModalManager.getData(this.modalId) || {};
                ModalManager.close(this.modalId);
                // Construir emisiones (grupo o individual)
                let emis = [];
                if (Array.isArray(data.group_emision_ids) && data.group_emision_ids.length) {
                    emis = data.group_emision_ids.map(id => {
                        const row = document.querySelector(`[data-nmemision="${id}"]`);
                        if (row) {
                            const obj = {}; Object.assign(obj, row.dataset);
                            row.querySelectorAll('td[data-field]').forEach(cell => { const f = cell.getAttribute('data-field'); if (f) obj[f] = cell.textContent.trim(); });
                            obj.NMEmision = obj.NMEmision || row.getAttribute('data-nmemision') || id;
                            return obj.NMEmision ? obj : { NMEmision: id };
                        }
                        return { NMEmision: id };
                    });
                } else {
                    const id = data.id || data.emisionId || data.emision_id;
                    if (id) {
                        const row = document.querySelector(`[data-nmemision="${id}"]`);
                        if (row) {
                            const obj = {}; Object.assign(obj, row.dataset);
                            row.querySelectorAll('td[data-field]').forEach(cell => { const f = cell.getAttribute('data-field'); if (f) obj[f] = cell.textContent.trim(); });
                            obj.NMEmision = obj.NMEmision || row.getAttribute('data-nmemision') || id;
                            emis = obj.NMEmision ? [obj] : [{ NMEmision: id }];
                        } else { emis = [{ NMEmision: id }]; }
                    }
                }
                window.openSeriesWizard?.(emis);
                // Preseleccionar serie
                setTimeout(() => {
                    const serieId = btn.dataset.preselectSerieId;
                    const titulo = btn.dataset.preselectSerieTitulo || '';
                    if (!serieId) return;
                    const wrapWizard = document.querySelector('#modal-series-wizard [data-obra-typeahead]');
                    if (wrapWizard) {
                        const evt = new CustomEvent('typeahead:select', { detail: { NMObra: Number(serieId) || serieId, TituloObra: titulo, label: titulo } });
                        wrapWizard.dispatchEvent(evt);
                    }
                }, 300);
            });
        }
    }

    hideAddToSerieInline() {
        const wrap = document.getElementById('add-to-serie-inline-wrap');
        const btn = document.getElementById('btn-add-to-serie-inline');
        if (wrap) wrap.classList.add('hidden');
        if (btn) { delete btn.dataset.preselectSerieId; delete btn.dataset.preselectSerieTitulo; }
    }

    // Ocultar panel de capítulos y resetear controles
    hideChaptersUI() {
        const panel = document.getElementById('obra-capitulos-panel');
        const select = document.getElementById('obra-capitulos-select');
        const chk = document.getElementById('chk-crear-capitulo');
        const input = document.getElementById('input-crear-capitulo');
        if (panel) panel.classList.add('hidden');
        if (select) { select.innerHTML = ''; select.disabled = false; }
        if (chk) chk.checked = false;
        if (input) { input.value = ''; input.disabled = true; }
    }

    // Mostrar botón "Añadir a Serie" y preseleccionar serie al abrir el wizard
    showAddToSerieForParent(obra) {
        const wrapper = document.getElementById('identificar-crear-serie-wrap');
        const button = document.getElementById('btn-open-wizard-serie');
        if (!wrapper || !button) return;
        // Mostrar wrapper y ajustar label
        wrapper.classList.remove('hidden');
        button.textContent = 'Añadir a Serie';
        // Guardar datos para preselección
        button.dataset.preselectSerieId = String(obra.NMObra || obra.id || '');
        button.dataset.preselectSerieTitulo = obra.label || obra.TituloObra || '';
        if (!button.dataset.boundAddToSerie) {
            button.dataset.boundAddToSerie = '1';
            button.addEventListener('click', () => {
                const data = ModalManager.getData(this.modalId) || {};
                // Cerrar identificar
                ModalManager.close(this.modalId);
                // Construir emisiones (grupo o individual)
                let emis = [];
                if (Array.isArray(data.group_emision_ids) && data.group_emision_ids.length) {
                    emis = data.group_emision_ids.map(id => {
                        const row = document.querySelector(`[data-nmemision="${id}"]`);
                        if (row) {
                            const obj = {};
                            Object.assign(obj, row.dataset);
                            const cells = row.querySelectorAll('td[data-field]');
                            cells.forEach(cell => {
                                const field = cell.getAttribute('data-field');
                                if (field) obj[field] = cell.textContent.trim();
                            });
                            obj.NMEmision = obj.NMEmision || row.getAttribute('data-nmemision') || id;
                            return obj.NMEmision ? obj : { NMEmision: id };
                        }
                        return { NMEmision: id };
                    });
                } else {
                    const id = data.id || data.emisionId || data.emision_id;
                    if (id) {
                        const row = document.querySelector(`[data-nmemision="${id}"]`);
                        if (row) {
                            const obj = {};
                            Object.assign(obj, row.dataset);
                            const cells = row.querySelectorAll('td[data-field]');
                            cells.forEach(cell => {
                                const field = cell.getAttribute('data-field');
                                if (field) obj[field] = cell.textContent.trim();
                            });
                            obj.NMEmision = obj.NMEmision || row.getAttribute('data-nmemision') || id;
                            emis = obj.NMEmision ? [obj] : [{ NMEmision: id }];
                        } else {
                            emis = [{ NMEmision: id }];
                        }
                    }
                }
                // Abrir wizard con emisiones
                window.openSeriesWizard?.(emis);
                // Preseleccionar serie
                setTimeout(() => {
                    const wrap = document.querySelector('#modal-series-wizard [data-obra-typeahead]');
                    const serieId = button.dataset.preselectSerieId;
                    const titulo = button.dataset.preselectSerieTitulo || '';
                    if (wrap && serieId) {
                        const evt = new CustomEvent('typeahead:select', { detail: { NMObra: Number(serieId) || serieId, TituloObra: titulo, label: titulo } });
                        wrap.dispatchEvent(evt);
                    }
                }, 300);
            });
        }
    }

    // Submit del formulario (asignar obra)
    bindFormSubmit(data) {
        const form = document.getElementById('form-identificar-obra');
        if (!form || form.dataset.boundSubmit) return;

        form.dataset.boundSubmit = '1';
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.handleFormSubmit(data);
        });
    }

    // Manejar submit del form
    async handleFormSubmit(data) {
    const wrapper = document.querySelector(`#${this.modalId} [data-obra-typeahead]`);
    // Preferir capítulo si el panel está visible; si no, usar obra padre del panel o selection del typeahead
    const panel = document.getElementById('selected-obra-info');
    const capsPanel = document.getElementById('obra-capitulos-panel');
    const capSelect = document.getElementById('obra-capitulos-select');
    const chkCrear = document.getElementById('chk-crear-capitulo');
    const inputCrear = document.getElementById('input-crear-capitulo');
    let obraId = (capsPanel && !capsPanel.classList.contains('hidden') && capSelect?.value)
        ? capSelect.value
        : (panel?.dataset.obraId || wrapper?.dataset.selected);
        
        if (!obraId) {
            showToast('Selecciona una obra primero', 'warning');
            return;
        }

        // Validate current selection state (blocks general/container without chapter)
        if (!this.validateSelection()) {
            return;
        }

        const bulkIds = Array.isArray(data.bulk_ids) ? data.bulk_ids : null;
        const singleId = data.id || data.emisionId || data.emision_id;
        const idsToAssign = bulkIds || [singleId];

        if (!idsToAssign.length) {
            showToast('No hay emisiones para asignar', 'error');
            return;
        }

        try {
            setButtonLoading('btn-asignar-obra', true);
            
            const originalData = this.getOriginalTitleData();
            let successCount = 0;

            for (const emisionId of idsToAssign) {
                try {
                    // Si está marcado crear capítulo, primero crear hijo y usar su id
                    let finalObraId = obraId;
                    if (chkCrear && chkCrear.checked) {
                        const parentId = panel?.dataset.obraId || wrapper?.dataset.selected;
                        const tituloNuevo = (inputCrear?.value || '').trim();
                        if (!parentId) { throw new Error('Falta obra padre para crear capítulo'); }
                        if (!tituloNuevo) { showToast('Ingresa un título para el nuevo capítulo', 'warning'); return; }
                        const quick = await simpleAjax('/obras/quick-store', {
                            method: 'POST',
                            // Para capítulos no enviar TipoObra: el hecho de tener NMSerie ya los clasifica
                            body: JSON.stringify({ TituloObra: tituloNuevo, NMSerie: Number(parentId) || parentId })
                        });
                        if (quick && quick.NMObra) {
                            finalObraId = quick.NMObra;
                        } else {
                            throw new Error('No se pudo crear el capítulo');
                        }
                    }
                    const payload = { emision_id: emisionId, obra_id: finalObraId, ...originalData };
                    await simpleAjax('/emisiones/asignar-obra', { method: 'POST', body: JSON.stringify(payload) });
                    successCount++;
                } catch (error) {
                    console.warn('Assignment failed for emission:', emisionId, error);
                }
            }

            const message = successCount === idsToAssign.length ? 
                'Obra asignada correctamente' : 
                `Obra asignada (${successCount}/${idsToAssign.length})`;
            
            showToast(message, successCount === idsToAssign.length ? 'success' : 'warning');
            ModalManager.close(this.modalId);

            // Limpiar selección masiva actual para evitar inconsistencias con filas anidadas
            try { window.BulkSelection?.deselectAll(); } catch {}

            // Refrescar pestaña activa si existe (coherente con edición inline)
            try {
                // Después de asignar en lote, reiniciar selección masiva tras el swap
                window.requestBulkResetOnNextSwap?.();
                const active = document.querySelector('#tabs-nav a.tab-link.bg-gray-100');
                if (active && window.ajaxSwap) {
                    await window.ajaxSwap({ url: active.getAttribute('href') + '&ajax=1', target: '#tab-content' });
                }
            } catch (_) {}

        } catch (error) {
            console.error('Assignment error:', error);
            showToast('Error al asignar obra', 'error');
        } finally {
            setButtonLoading('btn-asignar-obra', false);
        }
    }

    // Obtener datos del título original si está marcado
    getOriginalTitleData() {
        const checkbox = document.getElementById('chk-guardar-original');
        const input = document.getElementById('input-titulo-original');
        
        if (checkbox?.checked && input?.value.trim()) {
            return {
                store_original: true,
                original_title: input.value.trim()
            };
        }
        return {};
    }

    // Bind botón limpiar selección
    bindClearSelection() {
        const button = document.getElementById('btn-quitar-obra');
        if (!button || button.dataset.boundClear) return;
        button.dataset.boundClear = '1';
        button.onclick = () => {
            this.clearSelectedObra();
            if (window.setObraSeleccionContext) window.setObraSeleccionContext('manual');
        };
    }

    // Limpia sólo la selección actual (panel azul, typeahead, capítulos), sin tocar la info de Emisión
    clearSelectedObra() {
        const panel = document.getElementById('selected-obra-info');
        if (panel) {
            panel.classList.add('hidden');
            if (panel.dataset) delete panel.dataset.obraId;
        }
        const title = document.getElementById('selected-obra-titulo');
        if (title) title.textContent = '-';
        const details = document.getElementById('selected-obra-detalles');
        if (details) details.textContent = '-';
        const wrapper = document.querySelector(`#${this.modalId} [data-obra-typeahead]`);
        if (wrapper && wrapper.dataset) delete wrapper.dataset.selected;
        const searchInput = document.getElementById('obra-search');
        if (searchInput) searchInput.value = '';
        const capsPanel = document.getElementById('obra-capitulos-panel');
        const capSelect = document.getElementById('obra-capitulos-select');
        if (capSelect) { capSelect.innerHTML = ''; capSelect.disabled = false; }
        if (capsPanel) capsPanel.classList.add('hidden');
    // Removed old checkbox/input for creating chapter
        const submitBtn = document.getElementById('btn-asignar-obra');
        if (submitBtn) submitBtn.disabled = true;
        this.clearWarning();
        // Asegurar que el botón de cabecera quede siempre visible como "Crear Serie" sin preselección
        const wrapperSerie = document.getElementById('identificar-crear-serie-wrap');
        const btnSerie = document.getElementById('btn-open-wizard-serie');
        if (wrapperSerie) wrapperSerie.classList.remove('hidden');
        if (btnSerie) {
            btnSerie.textContent = 'Crear Serie';
            delete btnSerie.dataset.preselectSerieId;
            delete btnSerie.dataset.preselectSerieTitulo;
        }
    }

    // Bind botón desasignar obra
    bindUnassignButton(obra) {
        const button = document.getElementById('btn-unassign-obra');
        if (!button || !obra) return;

        button.onclick = async () => {
            const data = ModalManager.getData(this.modalId);
            const emisionId = data.id || data.emisionId || data.emision_id;
            
            if (!emisionId) return;

            try {
                await simpleAjax('/emisiones/quitar-obra', {
                    method: 'POST',
                    body: JSON.stringify({ emision_id: emisionId })
                });

                showToast('Obra desasignada', 'success');
                
                // Ocultar panel verde
                const obraDiv = document.getElementById('current-obra');
                if (obraDiv) obraDiv.classList.add('hidden');

                // Limpiar selección azul también
                this.bindClearSelection();
                document.getElementById('btn-quitar-obra')?.click();

            } catch (error) {
                showToast('Error al desasignar', 'error');
            }
        };
    }

    // Bind botón editar obra (abre el modal de edición si existe en el DOM)
    bindEditObraButton(obra) {
        const button = document.getElementById('btn-edit-obra');
        if (!button || button.dataset.boundEditObra) return;
        button.dataset.boundEditObra = '1';
        button.addEventListener('click', (e) => {
            e.preventDefault();
            const panel = document.getElementById('current-obra');
            const id = (obra && (obra.id || obra.NMObra)) || panel?.dataset?.obraId;
            if (!id) return;
            const modalId = `modal-edit-obra-${id}`;
            if (window.ModalManager) {
                window.ModalManager.open(modalId);
            }
        });
    }

    // Bind edición inline del título
    bindEditTitle(data) {
        const editBtn = document.getElementById('btn-edit-emision-title');
        if (!editBtn || editBtn.dataset.boundEdit) return;

        editBtn.dataset.boundEdit = '1';
        // Implementar lógica de edición inline...
        // (Por brevedad, no incluyo toda la lógica aquí)
    }

    // Helpers de formato
    formatFechaDMY(dateString) {
        if (!dateString) return null;
        
        const match = String(dateString).match(/(\d{4})-(\d{2})-(\d{2})/);
        if (match) return `${match[3]}/${match[2]}/${match[1]}`;
        
        try {
            const date = new Date(dateString);
            if (!isNaN(date)) {
                const dd = String(date.getDate()).padStart(2, '0');
                const mm = String(date.getMonth() + 1).padStart(2, '0');
                const yyyy = date.getFullYear();
                return `${dd}/${mm}/${yyyy}`;
            }
        } catch (_) {}
        
        return String(dateString);
    }

    formatHorario(inicio, fin) {
        if (inicio && fin) return `${inicio} - ${fin}`;
        if (inicio) return inicio;
        return '';
    }

    buildObraDetails(obra) {
        const genero = obra.genero || obra.Genero;
        const anios = obra.anio_ini ? 
            (obra.anio_fin && obra.anio_fin !== obra.anio_ini ? `${obra.anio_ini}-${obra.anio_fin}` : obra.anio_ini) : 
            (obra.AnioProduccion || null);
        
        return [genero, anios].filter(Boolean).join(' · ');
    }

    // Limpiar estado cuando se cierra el modal
    cleanup() {
        this.isInitialized = false;
        // Reset completo para evitar arrastre de estado entre aperturas
        this.resetModalState();
    }
}

// Instancia global (única exportación global necesaria)
const modalIdentificarObra = new ModalIdentificarObra();

// Escuchar cuando se cierra el modal para cleanup
document.addEventListener('modal:closed', (e) => {
    if (e.detail.modalId === 'modal-identificar-obra') {
        modalIdentificarObra.cleanup();
    }
});

// Exponer función global para compatibilidad
window.openIdentificarObraModal = (data) => modalIdentificarObra.open(data);

export default modalIdentificarObra;