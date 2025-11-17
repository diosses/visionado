/**
 * Modal Obras - Módulo dedicado
 * Maneja toda la lógica del modal para crear/editar obras
 */

class ModalObras {
    constructor() {
        this.modalId = 'modal-create-obra';
        this.isInitialized = false;
        this._lastParentData = null; // cache of last parent obra JSON used for mapping
    }

    // Método principal para abrir el modal (reemplaza window.openModalObra)
    open(options = {}) {
        // Abrir modal usando ModalManager
        ModalManager.open(this.modalId, options);

        // Configurar modal según opciones
        this.setupModal(options);
        this.populateFields(options);
        this.setupConditionalFields(options);
        this.bindEvents();

        // Focus en primer input
        setTimeout(() => {
            const firstInput = document.querySelector(`#${this.modalId} input[type="text"], select, textarea`);
            if (firstInput && firstInput.focus) firstInput.focus();
        }, 100);
    }

    // Configurar el modal según las opciones
    setupModal(options) {
        const modal = document.getElementById(this.modalId);
        if (!modal) return;

        // Mostrar/ocultar advertencia de grupo
        const advertencia = modal.querySelector('#advertencia-grupo-emisiones');
        const isGroupContext = this.isGroupContext(options);
        
        if (advertencia) {
            // Permitir forzar ocultar advertencia si viene desde el wizard o contexto especial
            if (options.ocultarAdvertencia === true) {
                advertencia.classList.add('hidden');
            } else if (isGroupContext) {
                advertencia.classList.remove('hidden');
            } else {
                advertencia.classList.add('hidden');
            }
        }

        // Mostrar/ocultar checkbox de anidar
        const wrapAnidar = modal.querySelector('#wrap-anidar-capitulo');
        if (wrapAnidar) {
            if (options.mostrarAnidar === true) {
                wrapAnidar.style.display = '';
            } else if (options.mostrarAnidar === false) {
                wrapAnidar.style.display = 'none';
            } else {
                // Default: oculto salvo que haya contexto individual sin bulk
                wrapAnidar.style.display = 'none';
            }
        }
    }

    // Determinar si estamos en contexto de grupo
    isGroupContext(options) {
        try {
            const identModal = document.getElementById('modal-identificar-obra');
            return !!(identModal?.dataset?.bulkContext || identModal?.dataset?.groupTitle);
        } catch (_) {
            return false;
        }
    }

    // Poblar campos del formulario
    async populateFields(options) {
        const modal = document.getElementById(this.modalId);
        if (!modal) return;

        // Título
        if (options.titulo !== undefined) {
            const titleInput = modal.querySelector('input[name="TituloObra"]');
            if (titleInput) {
                let v = options.titulo || '';
                // Si venimos del wizard y falta el sufijo, agregarlo
                const wizardOpen = !!document.getElementById('modal-series-wizard') && !document.getElementById('modal-series-wizard')?.classList.contains('hidden');
                if (wizardOpen && v) {
                    const base = v.replace(/\s*\(OBRA GENERAL\).*$/i, '').trim();
                    v = base ? `${base} (OBRA GENERAL)` : v;
                }
                titleInput.value = v;
            }
        }

        // Contexto de título: inferir de otros modales si no se especifica
        if (!options.titulo) {
            const inferredTitle = this.inferTitleFromContext();
            if (inferredTitle) {
                const titleInput = modal.querySelector('input[name="TituloObra"]');
                if (titleInput) titleInput.value = inferredTitle;
            }
        }

        // Forzar anidar (para crear capítulos)
        if (options.forzarAnidar) {
            const checkbox = modal.querySelector('#chk-anidar-capitulo');
            if (checkbox) {
                checkbox.checked = true;
                checkbox.dispatchEvent(new Event('change'));
            }
        }

        // Pre-seleccionar obra padre
        if (options.padreId && options.padreLabel) {
            this.preseleccionarObraPadre(options.padreId, options.padreLabel);
        }

        // --- NEW: Pre-fill all fields from parentObra except title ---
        const parent = (options.parentObra && typeof options.parentObra === 'object') ? options.parentObra : null;
        let effective = null;
        if (parent || (options.padreId && options.padreLabel)) {
            effective = parent || { NMObra: options.padreId, TituloObra: options.padreLabel };
            // If we only have an ID, try to fetch full details for robust mapping
            if (effective && effective.NMObra && Object.keys(effective).length <= 3 && this.fetchObraDetails) {
                try {
                    const details = await this.fetchObraDetails(effective.NMObra);
                    if (details && typeof details === 'object') {
                        // Preserve existing label if set
                        const keepTitle = effective.TituloObra;
                        effective = { ...details };
                        if (keepTitle) effective.TituloObra = keepTitle;
                    }
                } catch (_) { /* non-fatal */ }
            }
            // Normalize and map to form
            this._lastParentData = this.normalizeObraData(effective);
            this.mapJsonToForm(this._lastParentData);
            // Pre-fill search box for obra general/padre
            const padreInput = modal.querySelector('#input-obra-padre');
            if (padreInput && effective?.TituloObra) {
                padreInput.value = effective.TituloObra;
            }
            const padreIdInput = modal.querySelector('#input-obra-padre-id');
            if (padreIdInput && effective?.NMObra) {
                padreIdInput.value = effective.NMObra;
            }
            // Initialize any typeahead tied to the parent search field
            try { this.setupTypeaheadPadre(); } catch {}
        }
    }

    // Inferir título desde contextos de otros modales
    inferTitleFromContext() {
        // 1. Desde wizard de series (prioritario en este caso)
        const wizard = document.getElementById('modal-series-wizard');
        let title2 = '';
        if (wizard && !wizard.classList.contains('hidden')) {
            const selectedTitle = wizard.querySelector('#serie-sel-titulo');
            const searchInput = wizard.querySelector('#serie-search');
            let selectedText = selectedTitle?.textContent?.trim();
            if (selectedText === '-' || selectedText === '—') selectedText = '';
            
            const baseTitle = selectedText || (searchInput?.value?.trim() || '');
            if (baseTitle) {
                title2 = baseTitle.replace(/\s*\(OBRA GENERAL\).*$/i, '').trim() + ' (OBRA GENERAL)';
            }
        }

        // 2. Desde modal identificar obra
        const identModal = document.getElementById('modal-identificar-obra');
        let title1 = identModal?.querySelector('#modal-emision-titulo')?.textContent?.trim();
        if (title1 === '-' || title1 === '—') title1 = '';

        // 3. Desde group title guardado
        let title3 = '';
        try {
            title3 = identModal?.dataset?.groupTitle || '';
        } catch (_) {}

        // Retornar el primero que esté disponible, priorizando el wizard
        return title2 || title1 || title3 || '';
    }

    // Pre-seleccionar obra padre en el typeahead
    preseleccionarObraPadre(padreId, padreLabel) {
        const modal = document.getElementById(this.modalId);
        if (!modal) return;

        const input = modal.querySelector('#input-obra-padre');
        const hiddenInput = modal.querySelector('#input-obra-padre-id');
        if (input) input.value = padreLabel || '';
        if (hiddenInput) hiddenInput.value = padreId || '';
    }

    // Setup de campos condicionales (anidación)
    setupConditionalFields(options) {
        const modal = document.getElementById(this.modalId);
        if (!modal) return;

        const checkbox = modal.querySelector('#chk-anidar-capitulo');
        const panel = modal.querySelector('#panel-anidar-capitulo');
        const chkCrearGeneral = modal.querySelector('#chk-crear-obra-general');
        const panelCrearGeneral = modal.querySelector('#panel-crear-obra-general');
        const wrapBusqueda = modal.querySelector('#wrap-obra-padre-busqueda');
        const inputNuevaGeneral = modal.querySelector('#input-nueva-obra-general');
        const inputPadre = modal.querySelector('#input-obra-padre');
        const inputPadreId = modal.querySelector('#input-obra-padre-id');
        
        if (!checkbox || !panel) return;

        // Bind change event si no está ya bound
        if (!checkbox.dataset.boundAnidar) {
            checkbox.dataset.boundAnidar = '1';
            checkbox.addEventListener('change', () => {
                if (checkbox.checked) {
                    panel.classList.remove('hidden');
                    this.setupTypeaheadPadre();
                } else {
                    panel.classList.add('hidden');
                    this.clearObraPadre();
                    if (chkCrearGeneral) chkCrearGeneral.checked = false;
                    if (panelCrearGeneral) panelCrearGeneral.classList.add('hidden');
                    if (wrapBusqueda) wrapBusqueda.classList.remove('hidden');
                }
            });
        }

        // Si está forzado, disparar el change
        if (options.forzarAnidar && checkbox.checked) {
            checkbox.dispatchEvent(new Event('change'));
        }

        // Crear nueva obra general: alterna búsqueda y prellena título
        if (chkCrearGeneral && !chkCrearGeneral.dataset.boundCrearGeneral) {
            chkCrearGeneral.dataset.boundCrearGeneral = '1';
            chkCrearGeneral.addEventListener('change', () => {
                if (chkCrearGeneral.checked) {
                    panelCrearGeneral?.classList.remove('hidden');
                    wrapBusqueda?.classList.add('hidden');
                    // Prellenar con título + (OBRA GENERAL)
                    const tituloObra = modal.querySelector('input[name="TituloObra"]')?.value?.trim() || '';
                    if (inputNuevaGeneral) inputNuevaGeneral.value = tituloObra ? `${tituloObra} (OBRA GENERAL)` : '';
                    if (inputPadre) inputPadre.value = '';
                    if (inputPadreId) inputPadreId.value = '';
                } else {
                    panelCrearGeneral?.classList.add('hidden');
                    wrapBusqueda?.classList.remove('hidden');
                    if (inputNuevaGeneral) inputNuevaGeneral.value = '';
                }
            });
        }
    }

    // Setup del typeahead para obra padre
    setupTypeaheadPadre() {
        // En Blade usamos data-obra-typeahead dentro del panel de anidar
        const wrapper = document.querySelector(`#${this.modalId} #panel-anidar-capitulo [data-obra-typeahead]`);
        if (!wrapper || wrapper.dataset.bound) return;

        // Attach typeahead
        if (window.Typeahead?.attach) {
            try {
                const url = wrapper.getAttribute('data-typeahead-url');
                if (url) {
                    window.Typeahead.attach(wrapper, { url });
                    wrapper.dataset.bound = '1';
                }
            } catch (error) {
                console.error('Error setting up padre typeahead:', error);
            }
        }

        // Bind selection event
        wrapper.addEventListener('typeahead:select', (ev) => {
            const item = ev.detail || {};
            this.selectObraPadre(item);
        });
    }

    // Seleccionar obra padre del typeahead
    selectObraPadre(item) {
        const modal = document.getElementById(this.modalId);
        if (!modal) return;

        const input = modal.querySelector('#input-obra-padre');
        const hiddenInput = modal.querySelector('#input-obra-padre-id');

        if (input) input.value = item.label || item.TituloObra || '';
        if (hiddenInput) hiddenInput.value = item.NMObra || item.id || '';
    }

    // Limpiar selección de obra padre
    clearObraPadre() {
        const modal = document.getElementById(this.modalId);
        if (!modal) return;

        const input = modal.querySelector('#input-obra-padre');
        const hiddenInput = modal.querySelector('#input-obra-padre-id');

        if (input) input.value = '';
        if (hiddenInput) hiddenInput.value = '';
    }

    // Bind eventos del modal
    bindEvents() {
        if (this.isInitialized) return;

        this.bindFormSubmit();
        this.bindClearPadreButton();
        // Re-apply mapping if modal is re-opened with cached parent data (e.g., after ajax swaps)
        document.addEventListener('ajax:swapped', () => {
            const data = ModalManager.getData(this.modalId) || {};
            if (ModalManager.isOpen(this.modalId) && this._lastParentData) {
                // Remap to ensure values persist after DOM changes
                this.mapJsonToForm(this._lastParentData);
            } else if (ModalManager.isOpen(this.modalId) && (data.parentObra || (data.padreId && data.padreLabel))) {
                // If data present in modal data, re-populate
                this.populateFields(data);
            }
        });
        
        this.isInitialized = true;
    }

    // Submit del formulario
    bindFormSubmit() {
        // Compat: bind tanto crear como editar desde este módulo si están dentro del modalId actual
        const createForm = document.querySelector(`#${this.modalId} form[data-obra-quick]`);
        const editForm = document.querySelector(`#${this.modalId} form[data-obra-edit-form]`);
        const forms = [createForm, editForm].filter(Boolean);
        if (!forms.length) return;

        forms.forEach(form => {
            if (form.dataset.boundQuickGlobal) return;
            form.dataset.boundQuickGlobal = '1';
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                await this.handleFormSubmit(form);
            });
        });
    }

    // Manejar submit del formulario
    async handleFormSubmit(form) {
        // Validación nativa
        if (form.checkValidity && !form.checkValidity()) {
            if (form.reportValidity) form.reportValidity();
            return;
        }

        if (form.dataset.submitting === '1') return;
        form.dataset.submitting = '1';

        const submitBtn = form.querySelector('button[type=submit]');
        if (submitBtn) submitBtn.disabled = true;

        try {
            const formData = new FormData(form);
            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            
            const headers = { 'X-Requested-With': 'XMLHttpRequest' };
            if (token) headers['X-CSRF-TOKEN'] = token;

            const response = await fetch(form.action, {
                method: 'POST',
                headers,
                body: formData
            });

            let data;
            try {
                data = await response.clone().json();
            } catch (_) {
                data = null;
            }

            if (!response.ok) {
                const errorMsg = data?.message || data?.error || 'Error al guardar obra';
                showToast(errorMsg, 'error');
                return;
            }

            // Éxito
            showToast('Obra guardada', 'success');
            
            // Reset form si es creación
            if (form.matches('form[data-obra-quick]')) {
                try { form.reset(); } catch(_) {}
            }

            // Cerrar el modal correspondiente (crear o editar) según el formulario
            const modalEl = form.closest('.modal-component');
            if (modalEl?.id) {
                ModalManager.close(modalEl.id);
            } else {
                // Fallback al de creación
                ModalManager.close(this.modalId);
            }

            // Refresh contenido: si el wizard está abierto, difiere el refresh para después de que se cierre
            const wizardOpen = !!document.getElementById('modal-series-wizard') && !document.getElementById('modal-series-wizard')?.classList.contains('hidden');
            if (window.ModalObras?.refresh) {
                if (wizardOpen) {
                    // Escucha una sola vez el cierre del wizard para refrescar
                    const onWizardClosed = (ev) => {
                        if (ev.detail?.modalId === 'modal-series-wizard') {
                            document.removeEventListener('modal:closed', onWizardClosed);
                            setTimeout(() => { window.ModalObras.refresh(); }, 50);
                        }
                    };
                    document.addEventListener('modal:closed', onWizardClosed);
                } else {
                    await window.ModalObras.refresh();
                }
            }

            // Disparar evento para otros componentes
            if (data?.NMObra && data?.TituloObra) {
                document.dispatchEvent(new CustomEvent('obra:creada', { detail: data }));
            }

        } catch (error) {
            console.error('Form submit error:', error);
            showToast('Error al guardar obra', 'error');
        } finally {
            if (submitBtn) submitBtn.disabled = false;
            delete form.dataset.submitting;
        }
    }

    // Bind botón limpiar obra padre
    bindClearPadreButton() {
        const button = document.querySelector(`#${this.modalId} [data-clear-padre]`);
        if (!button || button.dataset.boundClear) return;

        button.dataset.boundClear = '1';
        button.addEventListener('click', () => {
            this.clearObraPadre();
        });
    }

    // Limpiar estado cuando se cierra el modal
    cleanup() {
        this.isInitialized = false;
        this._lastParentData = null;
        const modal = document.getElementById(this.modalId);
        if (!modal) return;
        modal.querySelectorAll('input[type="text"], input[type="hidden"], select, textarea').forEach(input => {
            if (input.type === 'checkbox' || input.type === 'radio') { input.checked = false; } else { input.value = ''; }
        });
        const advertencia = modal.querySelector('#advertencia-grupo-emisiones');
        if (advertencia) advertencia.classList.add('hidden');
        const wrapAnidar = modal.querySelector('#wrap-anidar-capitulo');
        if (wrapAnidar) wrapAnidar.style.display = 'none';
        const panelAnidar = modal.querySelector('#panel-anidar-capitulo');
        if (panelAnidar) panelAnidar.classList.add('hidden');
        modal.querySelectorAll('.is-invalid, .invalid-feedback').forEach(el => { el.classList.remove('is-invalid'); if (el.classList.contains('invalid-feedback')) el.textContent = ''; });
        // Revert to CREATE mode if we were editing
        try {
            const form = modal.querySelector('form');
            if (form && this._createAction) form.setAttribute('action', this._createAction);
            const methodInput = form ? form.querySelector('input[name="_method"]') : null; if (methodInput) methodInput.remove();
            const header = modal.querySelector('h3'); if (header && this._createHeaderText) header.textContent = this._createHeaderText;
            const submitBtn = form ? form.querySelector('button[type="submit"]') : null; if (submitBtn && this._createSubmitText) submitBtn.textContent = this._createSubmitText;
            delete modal.dataset.editing;
        } catch(_) {}
    }

    // --- NEW: Fetch obra details by ID (returns JSON)
    async fetchObraDetails(id) {
        try {
            // Try API endpoint; accept both numeric and string ids
            const data = await simpleAjax(`/obras/${id}`, { method: 'GET' });
            // If JSON OK
            if (data && typeof data === 'object' && !('raw' in data)) {
                return data.data || data; // unwrap if present
            }
            // HTML fallback: parse edit form to extract values
            if (data && typeof data === 'object' && typeof data.raw === 'string') {
                const parsed = this.parseObraDetailsFromHtml(data.raw);
                if (parsed) return parsed;
            }
            return null;
        } catch (e) {
            console.warn('fetchObraDetails failed', e);
            return null;
        }
    }

    // Fallback: parse obra edit form HTML and extract field values
    parseObraDetailsFromHtml(html) {
        try {
            const container = document.createElement('div');
            container.innerHTML = html;
            // Try to find a form with data-obra-edit-form
            let form = container.querySelector('form[data-obra-edit-form]');
            // If not found, try by common fields to scope down to likely form
            if (!form) {
                form = container.querySelector('form[action*="/obras/"]');
            }
            if (!form) return null;
            const getVal = (sel) => form.querySelector(sel)?.value?.trim() || '';
            const getSelectedText = (sel) => {
                const el = form.querySelector(sel);
                if (!el) return '';
                const opt = el.options?.[el.selectedIndex];
                return (opt?.textContent || '').trim();
            };
            const details = {
                NMObra: '',
                TituloObra: getVal('input[name="TituloObra"]') || getSelectedText('input[name="TituloObra"]'),
                CodGenero: getVal('select[name="CodGenero"]'),
                Genero: getSelectedText('select[name="CodGenero"]'),
                PaisOrigen: getVal('select[name="PaisOrigen"]'),
                Director: getVal('input[name="Director"]'),
                Duracion: getVal('input[name="Duracion"]'),
                AnioProduccion: getVal('input[name="AnioProduccion"]'),
                Idioma: getVal('select[name="Idioma"]'),
                Guionista: getVal('input[name="Guionista"]'),
                TipoObra: getVal('select[name="TipoObra"]') || getSelectedText('select[name="TipoObra"]')
            };
            // Try to infer NMObra from form action (/obras/{id})
            const action = form.getAttribute('action') || '';
            const m = action.match(/\/obras\/(\d+)/);
            if (m) details.NMObra = m[1];
            return this.normalizeObraData(details);
        } catch (e) {
            console.warn('parseObraDetailsFromHtml failed', e);
            return null;
        }
    }

    // --- NEW: Normalize obra JSON keys to expected field names
    normalizeObraData(raw) {
        if (!raw || typeof raw !== 'object') return {};
        const out = { ...raw };
        // Aliases
        out.NMObra = raw.NMObra || raw.id || raw.obra_id || raw.nmobra || out.NMObra;
        out.TituloObra = raw.TituloObra || raw.titulo || raw.label || out.TituloObra;
        out.Genero = raw.Genero || raw.genero || out.Genero;
        out.CodGenero = raw.CodGenero || raw.cod_genero || raw.codigo_genero || out.CodGenero;
        out.PaisOrigen = raw.PaisOrigen || raw.pais || raw.pais_origen || out.PaisOrigen;
        out.AnioProduccion = raw.AnioProduccion || raw.anio_produccion || raw.anio_ini || out.AnioProduccion;
        out.Idioma = raw.Idioma || raw.idioma || out.Idioma;
        out.Director = raw.Director || raw.director || out.Director;
        out.Duracion = raw.Duracion || raw.duracion || out.Duracion;
        out.Guionista = raw.Guionista || raw.guionista || out.Guionista;
        out.TipoObra = raw.TipoObra || raw.tipo_obra || out.TipoObra;
        return out;
    }

    // --- NEW: Map obra JSON fields to form inputs (excluding title)
    mapJsonToForm(obra) {
        const modal = document.getElementById(this.modalId);
        if (!modal || !obra) return;
        const mapping = {
            PaisOrigen: 'select[name="PaisOrigen"]',
            AnioProduccion: 'input[name="AnioProduccion"]',
            Idioma: 'select[name="Idioma"]',
            Director: 'input[name="Director"]',
            Duracion: 'input[name="Duracion"]',
            Guionista: 'input[name="Guionista"]',
            TipoObra: 'select[name="TipoObra"]'
        };
        // Special handling for Genero: target CodGenero select using code or match by option text
        (function mapGenero(){
            const sel = modal.querySelector('select[name="CodGenero"]');
            if (!sel) return;
            let matched = false;
            if (obra.CodGenero) {
                Array.from(sel.options).forEach(opt => {
                    if (String(opt.value) === String(obra.CodGenero)) { opt.selected = true; matched = true; }
                });
            }
            if (!matched && obra.Genero) {
                const needle = String(obra.Genero).trim();
                Array.from(sel.options).forEach(opt => {
                    if (opt.textContent && opt.textContent.trim() === needle) { opt.selected = true; matched = true; }
                });
            }
            if (matched) { try { sel.dispatchEvent(new Event('change', { bubbles: true })); } catch(_) {} }
        })();

        Object.entries(mapping).forEach(([key, selector]) => {
            const input = modal.querySelector(selector);
            if (!input) return;
            const val = obra[key];
            if (val === undefined || val === null || val === '') return;
            if (input.tagName === 'SELECT') {
                let matched = false;
                const targetVal = String(val).toUpperCase();
                Array.from(input.options).forEach(opt => {
                    const optVal = String(opt.value).toUpperCase();
                    const optText = (opt.textContent || '').trim();
                    const codeInText = optText.toUpperCase().includes(`(${targetVal})`);
                    const sameText = optText === String(val);
                    if (optVal === targetVal || codeInText || sameText) { opt.selected = true; matched = true; }
                });
                if (!matched) { try { input.value = val; } catch(_) {} }
            } else {
                input.value = val;
            }
        });
    }

    // --- NEW: Prefill for edit mode (includes title and all mapped fields)
    prefillForEdit(obraJson) {
        const data = this.normalizeObraData(obraJson);
        const modal = document.getElementById(this.modalId);
        if (!modal) return;
        // Title (edit fills from obra JSON)
        const titleInput = modal.querySelector('input[name="TituloObra"]');
        if (titleInput && data.TituloObra) {
            titleInput.value = data.TituloObra;
        }
        // Map the rest
        this.mapJsonToForm(data);
        // Runtime switch to EDIT mode using same modal shell
        try {
            const form = modal.querySelector('form');
            if (form && data.NMObra) {
                if (!this._createAction) this._createAction = form.getAttribute('action');
                if (!this._createHeaderText) {
                    const h = modal.querySelector('h3');
                    this._createHeaderText = h ? h.textContent : '';
                }
                if (!this._createSubmitText) {
                    const submitBtn0 = form.querySelector('button[type="submit"]');
                    this._createSubmitText = submitBtn0 ? submitBtn0.textContent : '';
                }
                form.setAttribute('action', `/obras/${data.NMObra}`);
                let methodInput = form.querySelector('input[name="_method"]');
                if (!methodInput) {
                    methodInput = document.createElement('input');
                    methodInput.type = 'hidden';
                    methodInput.name = '_method';
                    methodInput.value = 'PUT';
                    form.appendChild(methodInput);
                } else { methodInput.value = 'PUT'; }
                const header = modal.querySelector('h3');
                if (header && data.TituloObra) header.textContent = `Editar obra — ${data.TituloObra}`;
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.textContent = 'Guardar';
                modal.dataset.editing = '1';
            }
        } catch(_) { /* ignore */ }
    }
}

// Instancia global
const modalObras = new ModalObras();
// Exponer instancia para orquestador
window.modalObras = modalObras;
// Also expose prefill for edit explicitly
window.modalObras.prefillForEdit = window.modalObras.prefillForEdit?.bind(window.modalObras) || modalObras.prefillForEdit.bind(modalObras);

// Escuchar cuando se cierra el modal para cleanup
document.addEventListener('modal:closed', (e) => {
    if (e.detail.modalId === 'modal-create-obra') {
        modalObras.cleanup();
    }
});

// Exponer función global para compatibilidad
window.openModalObra = (options) => modalObras.open(options);

export default modalObras;