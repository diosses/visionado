/**
 * Series Wizard - Módulo dedicado
 * Maneja toda la lógica del asistente para gestión de series
 */

class SeriesWizard {
    constructor() {
        this.modalId = 'modal-series-wizard';
        this.isInitialized = false;
        this.currentStep = 1; // 1 = selección obra general, 2 = configuración capítulos
        
        // Estado mínimo de búsqueda (typeahead maneja AJAX/UI)
        this.currentSearchTerm = '';
        
        // Estados de selección
        this.selectedObraGeneral = null;
        this.bulkSelection = new Set();
    // Emisiones a procesar en el wizard (todas seleccionadas por defecto)
    this.emisiones = [];
        
        // URLs para AJAX
        this.urls = {
            search: '/emisiones/buscar-obra-general',
            apply: '/series/wizard/apply'
        };
    }

    // Helper: tomar solo la fecha (YYYY-MM-DD) de un string con posible hora
    onlyDate(s) {
        if (!s) return '';
        const str = String(s).trim();
        const m = str.match(/^(\d{4}-\d{2}-\d{2})/);
        if (m) return m[1];
        const parts = str.split(' ');
        return parts[0] || str;
    }

    // Helper: resolver fecha por emisión con múltiples fuentes (objeto y fila DOM)
    resolveFecha(emision) {
        // 1) Horario (dd/mm/yyyy hh:mm - hh:mm)
        const horario = emision?.Horario || emision?.horario || '';
        if (horario) {
            const m = String(horario).trim().match(/(\d{2}\/\d{2}\/\d{4})/);
            if (m) { const [dd, mm, yyyy] = m[1].split('/'); return `${yyyy}-${mm}-${dd}`; }
        }
        // 2) Campos directos potenciales
        const directFields = [
            'FechaEmisionCorta','fecha','Fecha','fecha_emision','FechaEmision','fechaEmision','fechaOriginal'
        ];
        for (const f of directFields) {
            if (emision && emision[f]) return this.onlyDate(emision[f]);
        }
        // 3) Fila DOM
        try {
            const id = emision.NMEmision || emision.id || emision.nmEmision;
            const row = id ? document.querySelector(`[data-nmemision="${id}"]`) : null;
            if (row) {
                const attr = row.getAttribute('data-fecha') || row.getAttribute('data-fecha-emision') || row.getAttribute('data-fechaemision');
                if (attr) return this.onlyDate(attr);
                const cell = row.querySelector('td[data-field="FechaEmisionCorta"], td[data-field="fecha"], td[data-field="Fecha"], td[data-field*="FechaEmision" i]');
                if (cell?.textContent) {
                    const text = cell.textContent.trim();
                    const mDash = text.match(/(\d{4}-\d{2}-\d{2})/);
                    if (mDash) return mDash[1];
                    const mSlash = text.match(/(\d{2}\/\d{2}\/\d{4})/);
                    if (mSlash) { const [dd, mm, yyyy] = mSlash[1].split('/'); return `${yyyy}-${mm}-${dd}`; }
                }
                // Fallback adicional: escanear todas las celdas si aún no encontramos fecha
                const allTds = row.querySelectorAll('td');
                for (const td of allTds) {
                    const txt = (td.textContent || '').trim();
                    if (!txt) continue;
                    let mIso = txt.match(/(\d{4}-\d{2}-\d{2})/);
                    if (mIso) return mIso[1];
                    let mSlash2 = txt.match(/(\d{2}\/\d{2}\/\d{4})/);
                    if (mSlash2) { const [dd, mm, yyyy] = mSlash2[1].split('/'); return `${yyyy}-${mm}-${dd}`; }
                }
            }
        } catch(_) {}
        return '';
    }

    // Generar nombres de capítulos consistentes según configuración seleccionada
    buildChapterNames({ baseTitulo, modoSufijo }) {
        const selectedIds = this.bulkSelection && this.bulkSelection.size ? new Set(this.bulkSelection) : null;
        const source = Array.isArray(this.emisiones) ? this.emisiones : [];
        const items = source.filter(e => !selectedIds || selectedIds.has(e.NMEmision));

        // Pre-resolver fechas para evitar múltiples DOM lookups y permitir conteo
        const resolved = items.map(e => ({ emision: e, fecha: this.resolveFecha(e) }));

        // Conteos por fecha para numerar duplicados cuando modoSufijo === 'fecha'
        const counts = {};
        resolved.forEach(r => { if (r.fecha) counts[r.fecha] = (counts[r.fecha] || 0) + 1; });
        // Índices usados para incrementar correlativo por fecha
        const usedPerDate = {}; // fecha -> aparición actual

        let epIndex = 0;
        return resolved.map(r => {
            let nombre = baseTitulo;
            if (modoSufijo === 'fecha') {
                // Normalizar siempre la fecha y formatear a DD-MM-YYYY (Chile). Si falta, usar 'FECHA-DESCONOCIDA'.
                const raw = r.fecha || '';
                let formatted = 'FECHA-DESCONOCIDA';
                if (raw && /\d{4}-\d{2}-\d{2}/.test(raw)) {
                    const [y, m, d] = raw.split('-');
                    formatted = `${d}-${m}-${y}`; // DD-MM-YYYY
                }
                usedPerDate[raw] = (usedPerDate[raw] || 0) + 1;
                const order = usedPerDate[raw];
                const totalForDate = counts[raw] || 0;
                nombre += ` - ${formatted}`;
                // Si hay más de una emisión con esa fecha válida y es la segunda o posterior, añadir correlativo
                if (raw && totalForDate > 1 && order > 1) {
                    nombre += ` (${order})`;
                }
            } else if (modoSufijo === 'ep') {
                epIndex += 1;
                nombre += ` - EP${epIndex}`;
            } else if (modoSufijo === 'vacio') {
                // sin sufijo
            }
            return { id: parseInt(r.emision.NMEmision, 10), nombre };
        });
    }

    // Método principal para abrir el wizard
    open(emisiones = []) {
        console.log('Emisiones recibidas en wizard:', emisiones);
        // Validar que tengamos emisiones
        if (!Array.isArray(emisiones) || emisiones.length === 0) {
            showToast('No hay emisiones seleccionadas', 'warning');
            return;
        }

    // Resetear estado y UI
    this.resetState();
    this.resetUI();

        // Poblar con emisiones recibidas; si no hay, intentar obtener desde selección global
        let list = Array.isArray(emisiones) ? emisiones : [];
        if (list.length === 0 && window.BulkSelection?.selectedItems?.size) {
            // Intentar extraer datos de la tabla actual como fallback
            try {
                const bs = window.BulkSelection;
                list = Array.from(bs.selectedItems).map(id => {
                    const el = document.querySelector(`[data-nmemision="${id}"]`);
                    const data = {};
                    if (el) {
                        Object.assign(data, el?.dataset || {});
                        data.NMEmision = data.NMEmision || el.getAttribute('data-nmemision') || id;
                        data.Titulo = data.Titulo || el.getAttribute('data-titulo') || '';
                        data.Canal = data.Canal || el.getAttribute('data-canal') || '';
                        data.FechaEmisionCorta = data.FechaEmisionCorta || el.getAttribute('data-fecha') || '';
                        data.HoraEmision = data.HoraEmision || el.getAttribute('data-hora') || '';
                    }
                    return data;
                }).filter(x => x && x.NMEmision);
            } catch(_) {}
        }

        this.populateEmisiones(list);

    // Abrir modal
        ModalManager.open(this.modalId);

        // Restaurar binding del typeahead en el campo de búsqueda de obra general (evita duplicados)
        setTimeout(() => {
            const wrapper = document.querySelector(`#${this.modalId} [data-obra-typeahead]`);
            if (wrapper && window.Typeahead?.attach) {
                if (!wrapper.dataset.boundTypeahead) {
                    wrapper.dataset.boundTypeahead = '1';
                    window.Typeahead.attach(wrapper);
                }
                if (!wrapper.dataset.boundTypeaheadSelect) {
                    wrapper.dataset.boundTypeaheadSelect = '1';
                    wrapper.addEventListener('typeahead:select', (ev) => {
                        const obra = ev.detail || {};
                        this.selectObraGeneral(obra);
                    });
                }
            }
            // Focus en búsqueda
            const searchInput = document.querySelector(`#${this.modalId} #serie-search`);
            if (searchInput && searchInput.focus) searchInput.focus();
        }, 100);

        // Inicializar componentes
        this.initializeComponents();
    }

    // Resetear estado del wizard
    resetState() {
        this.currentSearchTerm = '';
        this.selectedObraGeneral = null;
        this.bulkSelection.clear();
        this.emisiones = [];
        this.currentStep = 1;
    }

    // Resetear UI del wizard (pasos, selección, inputs)
    resetUI() {
        this.goToStep(1, { skipInit: true });

        // Limpiar selección de obra general
        const panel = document.querySelector(`#${this.modalId} #serie-seleccion`);
        const titulo = document.querySelector(`#${this.modalId} #serie-sel-titulo`);
        const detalles = document.querySelector(`#${this.modalId} #serie-sel-detalles`);
        const hiddenId = document.querySelector(`#${this.modalId} #serie-id`);
        if (panel) panel.classList.add('hidden');
        if (titulo) titulo.textContent = '-';
        if (detalles) detalles.textContent = '-';
        if (hiddenId) hiddenId.value = '';

        // Limpiar búsqueda y selección del typeahead
        const wrapper = document.querySelector(`#${this.modalId} [data-obra-typeahead]`);
        const input = document.querySelector(`#${this.modalId} #serie-search`);
        if (input) input.value = '';
        if (wrapper && wrapper.dataset) delete wrapper.dataset.selected;

        // Limpiar lista y preview
        const list = document.querySelector(`#${this.modalId} #serie-emisiones-list`);
        const preview = document.querySelector(`#${this.modalId} #wizard-preview`);
        if (list) list.innerHTML = '';
        if (preview) preview.innerHTML = '';

        // Reset de campos de paso 2
        const baseTitulo = document.querySelector(`#${this.modalId} #wizard-base-titulo`);
        if (baseTitulo) baseTitulo.value = '';
        const sufijoFecha = document.querySelector(`#${this.modalId} input[name="wizard-sufijo"][value="fecha"]`);
        if (sufijoFecha) sufijoFecha.checked = true;

        // Botones deshabilitados inicialmente
        const continueBtn = document.querySelector(`#${this.modalId} #wizard-continue`);
        const finishBtn = document.querySelector(`#${this.modalId} #wizard-finish`);
        if (continueBtn) continueBtn.disabled = true;
        if (finishBtn) finishBtn.disabled = true;
        const applyBtn = document.querySelector(`#${this.modalId} #btn-serie-apply`);
        if (applyBtn) applyBtn.disabled = true;
    }

    // Centralizar cambio de paso
    goToStep(step, opts = {}) {
        const { skipInit = false } = opts;
        if (step !== 1 && step !== 2) return;
        this.currentStep = step;
        const step1 = document.querySelector(`#${this.modalId} #wizard-step1`);
        const step2 = document.querySelector(`#${this.modalId} #wizard-step2`);
        const actions1 = document.querySelector(`#${this.modalId} #wizard-step1-actions`);
        const actions2 = document.querySelector(`#${this.modalId} #wizard-step2-actions`);
        // Mostrar / ocultar contenedores
        if (step === 1) {
            step1?.classList.remove('hidden'); actions1?.classList.remove('hidden');
            step2?.classList.add('hidden'); actions2?.classList.add('hidden');
        } else {
            step1?.classList.add('hidden'); actions1?.classList.add('hidden');
            step2?.classList.remove('hidden'); actions2?.classList.remove('hidden');
        }
        // Actualizar indicadores visuales
        const indicators = document.querySelectorAll(`#${this.modalId} [data-step-indicator]`);
        indicators.forEach(ind => {
            const n = parseInt(ind.getAttribute('data-step-indicator'), 10);
            const badge = ind.querySelector('[data-step-badge]');
            if (n < step) {
                ind.classList.remove('opacity-50');
                ind.classList.add('text-blue-700');
                badge?.classList.remove('bg-gray-200','text-gray-600','bg-blue-600','text-white');
                badge?.classList.add('bg-blue-600','text-white');
            } else if (n === step) {
                ind.classList.remove('opacity-50');
                badge?.classList.remove('bg-gray-200','text-gray-600');
                badge?.classList.add('bg-blue-600','text-white');
            } else { // futuro
                ind.classList.add('opacity-50');
                badge?.classList.remove('bg-blue-600','text-white');
                if (badge) { badge.classList.add('bg-gray-200','text-gray-600'); }
            }
        });
        // Enfocar campo relevante al entrar a paso 2
        if (step === 2 && !skipInit) {
            const baseTitulo = document.querySelector(`#${this.modalId} #wizard-base-titulo`);
            if (baseTitulo && baseTitulo.focus) setTimeout(()=>baseTitulo.focus(), 30);
        }
        if (step === 2 && !skipInit) this.initStep2();
    }

    // Poblar emisiones (sin depender de UI específica)
    populateEmisiones(emisiones) {
        const container = document.querySelector(`#${this.modalId} #serie-emisiones-list`);
        this.emisiones = Array.isArray(emisiones) ? emisiones : [];
        this.bulkSelection = new Set(this.emisiones.map(e => e.NMEmision).filter(Boolean));

        // Si existe contenedor visual, refrescarlo; si no, continuar sin UI
        if (container) {
            container.innerHTML = '';
            this.emisiones.forEach(emision => {
                const item = this.createEmisionItem(emision);
                container.appendChild(item);
            });
        }

        // Actualizar UI dependiente
        this.updateEmisionesCounter();
        this.updateActionButtons();
        this.updatePreview();
    }

    // Crear elemento de emisión
    createEmisionItem(emision) {
        const div = document.createElement('div');
        div.className = 'p-3 border border-gray-200 rounded mb-2 emision-item';
        div.dataset.nmEmision = emision.NMEmision || '';
        
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.className = 'mr-2 emision-checkbox';
        checkbox.checked = true;
        checkbox.addEventListener('change', () => this.updateBulkSelection());
        
        const label = document.createElement('label');
        label.className = 'flex items-center cursor-pointer';
        
        const text = document.createElement('span');
        text.textContent = this.formatEmisionLabel(emision);
        
        label.appendChild(checkbox);
        label.appendChild(text);
        div.appendChild(label);
        
        return div;
    }

    // Formatear etiqueta de emisión
    formatEmisionLabel(emision) {
        const parts = [];
        if (emision.Canal) parts.push(emision.Canal);
        if (emision.FechaEmisionCorta) parts.push(emision.FechaEmisionCorta);
        if (emision.HoraEmision) parts.push(emision.HoraEmision);
        const titulo = emision.Titulo || emision.titulo;
        if (titulo && titulo !== '-') parts.push(`"${titulo}"`);
        return parts.join(' - ') || `Emisión ${emision.NMEmision}`;
    }

    // Actualizar selección bulk
    updateBulkSelection() {
        this.bulkSelection.clear();
        const checkboxes = document.querySelectorAll(`#${this.modalId} .emision-checkbox:checked`);
        if (checkboxes.length) {
            checkboxes.forEach(checkbox => {
                const item = checkbox.closest('.emision-item');
                const nmEmision = item?.dataset?.nmEmision;
                if (nmEmision) this.bulkSelection.add(nmEmision);
            });
        } else {
            // Si no hay UI de checkboxes, considerar todas seleccionadas
            this.emisiones.forEach(e => { if (e.NMEmision) this.bulkSelection.add(e.NMEmision); });
        }
        
        this.updateEmisionesCounter();
        this.updateActionButtons();
        this.updatePreview();
    }

    // Actualizar contador de emisiones
    updateEmisionesCounter() {
        const counter = document.querySelector(`#${this.modalId} #serie-emisiones-count`);
        if (counter) {
            const total = this.emisiones.length;
            const selected = this.bulkSelection.size || total;
            counter.textContent = `${selected} de ${total} emisiones seleccionadas`;
        }
    }

    // Actualizar estado de botones de acción
    updateActionButtons() {
        const applyBtn = document.querySelector(`#${this.modalId} #btn-serie-apply`);
        const hasSelection = (this.bulkSelection && this.bulkSelection.size > 0) || (this.emisiones?.length > 0);
        const hasObraGeneral = !!this.selectedObraGeneral;
        
        if (applyBtn) {
            applyBtn.disabled = !(hasSelection && hasObraGeneral);
        }
        // Botón finalizar del wizard (paso 2)
        const finishBtn = document.querySelector(`#${this.modalId} #wizard-finish`);
        if (finishBtn) finishBtn.disabled = !(hasSelection && hasObraGeneral);
    }

    // Inicializar componentes del wizard
    initializeComponents() {
        if (this.isInitialized) return;

        this.bindCreateButton();
        this.bindApplyButton();
        this.bindSelectAllToggle();

        // Binding para el botón Continuar (paso 1 → paso 2)
        const continueBtn = document.querySelector(`#${this.modalId} #wizard-continue`);
        if (continueBtn && !continueBtn.dataset.boundWizardContinue) {
            continueBtn.dataset.boundWizardContinue = '1';
            continueBtn.addEventListener('click', () => {
                if (!this.selectedObraGeneral) return;
                this.goToStep(2);
            });
        }

        // Binding para el botón Volver (paso 2 → paso 1)
        const backBtn = document.querySelector(`#${this.modalId} #wizard-back`);
        if (backBtn && !backBtn.dataset.boundWizardBack) {
            backBtn.dataset.boundWizardBack = '1';
            backBtn.addEventListener('click', () => {
                this.goToStep(1);
            });
        }

        // Binding para el botón Confirmar y crear capítulos
        const finishBtn = document.querySelector(`#${this.modalId} #wizard-finish`);
        if (finishBtn && !finishBtn.dataset.boundWizardFinish) {
            finishBtn.dataset.boundWizardFinish = '1';
            finishBtn.addEventListener('click', () => {
                // Aquí va la lógica para guardar capítulos (puedes llamar a applySerieAssignment o similar)
                this.applySerieAssignment();
            });
        }

        // Bindings para campos de paso 2 (nombre base, sufijo, etc.)
        const baseTitulo = document.querySelector(`#${this.modalId} #wizard-base-titulo`);
        const sufijoRadios = document.querySelectorAll(`#${this.modalId} input[name="wizard-sufijo"]`);
        if (baseTitulo && !baseTitulo.dataset.boundWizardBase) {
            baseTitulo.dataset.boundWizardBase = '1';
            baseTitulo.addEventListener('input', () => this.updatePreview());
        }
        sufijoRadios.forEach(radio => {
            if (!radio.dataset.boundWizardSufijo) {
                radio.dataset.boundWizardSufijo = '1';
                radio.addEventListener('change', () => this.updatePreview());
            }
        });

        this.isInitialized = true;
    }

    // Inicializar campos y vista previa de capítulos en el paso 2
    initStep2() {
        // Poner el nombre base igual al título de la obra general seleccionada (sin sufijo)
        const baseTitulo = document.querySelector(`#${this.modalId} #wizard-base-titulo`);
        if (baseTitulo && this.selectedObraGeneral) {
            let titulo = this.selectedObraGeneral.label || this.selectedObraGeneral.TituloObra || '';
            titulo = titulo.replace(/\s*\(OBRA GENERAL\)$/i, '').trim();
            baseTitulo.value = titulo;
        }
        // Actualizar vista previa
        this.updatePreview();
    }

    // Actualizar la vista previa de capítulos
    updatePreview() {
        const preview = document.querySelector(`#${this.modalId} #wizard-preview`);
        const baseTitulo = document.querySelector(`#${this.modalId} #wizard-base-titulo`);
        const sufijo = document.querySelector(`#${this.modalId} input[name="wizard-sufijo"]:checked`);
        if (!preview || !baseTitulo || !sufijo) return;
        const base = (baseTitulo.value || '').trim();
        const chapters = this.buildChapterNames({ baseTitulo: base, modoSufijo: sufijo.value });
        preview.innerHTML = chapters.map(c => `<li>${c.nombre}</li>`).join('');
    }

    // Seleccionar obra general
    selectObraGeneral(obra) {
        this.selectedObraGeneral = obra;
        
        // Actualizar UI de selección (panel azul)
        const panel = document.querySelector(`#${this.modalId} #serie-seleccion`);
        const titulo = document.querySelector(`#${this.modalId} #serie-sel-titulo`);
        const detalles = document.querySelector(`#${this.modalId} #serie-sel-detalles`);
        const hiddenId = document.querySelector(`#${this.modalId} #serie-id`);
        if (panel) panel.classList.remove('hidden');
        if (titulo) titulo.textContent = obra.label || obra.TituloObra || 'Sin título';
        if (detalles) detalles.textContent = (obra.GeneroNombre ? obra.GeneroNombre + ' · ' : '') + (obra.AnioEstreno || '');
        if (hiddenId && obra.NMObra) hiddenId.value = obra.NMObra;

        // Habilitar botón Continuar
        const continueBtn = document.querySelector(`#${this.modalId} #wizard-continue`);
        if (continueBtn) continueBtn.disabled = false;

        // Actualizar botones de acción generales
        this.updateActionButtons();
    }

    // Crear obra desde búsqueda
    createObraFromSearch() {
        const searchInput = document.querySelector(`#${this.modalId} #serie-search`);
        const searchTerm = searchInput?.value?.trim() || '';
        // Base: término de búsqueda o, si está vacío, el título de la primera emisión seleccionada
        const firstTitle = (Array.isArray(this.emisiones) && this.emisiones.length)
            ? (this.emisiones[0].Titulo || this.emisiones[0].titulo || '').trim()
            : '';
        const base = (searchTerm || firstTitle || '').replace(/\s*\(OBRA GENERAL\).*$/i, '').trim();

        // Abrir modal de crear obra con contexto de serie
        if (window.openModalObra) {
            window.openModalObra({
                titulo: base ? `${base} (OBRA GENERAL)` : '',
                mostrarAnidar: false,
                ocultarAdvertencia: true
            });
        }
    }

    // Bind botón crear obra general
    bindCreateButton() {
        // Soportar ambos IDs para compatibilidad con plantillas existentes
        const selectors = [
            `#${this.modalId} #btn-serie-create`,
            `#${this.modalId} #btn-wizard-crear-obra`
        ];
        selectors.forEach(sel => {
            const btn = document.querySelector(sel);
            if (!btn || btn.dataset.boundSerieCreate) return;
            btn.dataset.boundSerieCreate = '1';
            btn.addEventListener('click', () => {
                this.createObraFromSearch();
            });
        });
    }

    // Bind botón aplicar
    bindApplyButton() {
        const applyBtn = document.querySelector(`#${this.modalId} #btn-serie-apply`);
        if (!applyBtn || applyBtn.dataset.boundSerieApply) return;
        
        applyBtn.dataset.boundSerieApply = '1';
        applyBtn.addEventListener('click', () => {
            this.applySerieAssignment();
        });
    }

    // Bind toggle seleccionar todo
    bindSelectAllToggle() {
        const toggleBtn = document.querySelector(`#${this.modalId} #btn-serie-toggle-all`);
        if (!toggleBtn || toggleBtn.dataset.boundSerieToggle) return;
        
        toggleBtn.dataset.boundSerieToggle = '1';
        toggleBtn.addEventListener('click', () => {
            this.toggleSelectAll();
        });
    }

    // Toggle seleccionar todo
    toggleSelectAll() {
        const checkboxes = document.querySelectorAll(`#${this.modalId} .emision-checkbox`);
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        
        checkboxes.forEach(checkbox => {
            checkbox.checked = !allChecked;
        });
        
        this.updateBulkSelection();
    }

    // Aplicar asignación de serie
    async applySerieAssignment() {
        if (!this.selectedObraGeneral || this.bulkSelection.size === 0) {
            showToast('Selecciona una obra general y emisiones', 'warning');
            return;
        }
        
        // Manejar estado de botones (aplica para botón de finalización del wizard)
        const applyBtn = document.querySelector(`#${this.modalId} #btn-serie-apply`);
        const finishBtn = document.querySelector(`#${this.modalId} #wizard-finish`);
        if (applyBtn?.dataset.applying === '1' || finishBtn?.dataset.applying === '1') return;
        
        const setApplying = (on) => {
            if (applyBtn) {
                if (on) { applyBtn.dataset.applying = '1'; applyBtn.disabled = true; applyBtn.textContent = 'Aplicando...'; }
                else { delete applyBtn.dataset.applying; applyBtn.disabled = false; applyBtn.textContent = 'Aplicar'; }
            }
            if (finishBtn) {
                if (on) { finishBtn.dataset.applying = '1'; finishBtn.disabled = true; finishBtn.textContent = 'Creando...'; }
                else { delete finishBtn.dataset.applying; finishBtn.disabled = false; finishBtn.textContent = 'Confirmar y crear capítulos'; }
            }
        };
        setApplying(true);
        
        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            const headers = { 
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            };
            if (token) headers['X-CSRF-TOKEN'] = token;
            
            const baseTitulo = document.querySelector(`#${this.modalId} #wizard-base-titulo`);
            const sufijo = document.querySelector(`#${this.modalId} input[name="wizard-sufijo"]:checked`);
            const base = (baseTitulo?.value || '').trim();
            const suf = (sufijo?.value || 'fecha');
            const emisiones = this.buildChapterNames({ baseTitulo: base, modoSufijo: suf });

            const payload = {
                serie_id: parseInt(this.selectedObraGeneral.NMObra, 10),
                base,
                sufijo: suf,
                emisiones,
                keep_originals: false
            };
            
            const response = await fetch(this.urls.apply, {
                method: 'POST',
                headers,
                body: JSON.stringify(payload)
            });
            
            let data;
            try {
                data = await response.clone().json();
            } catch (_) {
                data = null;
            }
            
            if (!response.ok) {
                const errorMsg = data?.message || data?.error || 'Error al aplicar asignación';
                showToast(errorMsg, 'error');
                return;
            }
            
            // Éxito
            const successMsg = data?.message || 'Asignación aplicada exitosamente';
            showToast(successMsg, 'success');
            
            // Cerrar modal
            ModalManager.close(this.modalId);
            
            // Disparar evento para refrescar contenido
            document.dispatchEvent(new CustomEvent('series:applied', { 
                detail: { 
                    obraGeneral: this.selectedObraGeneral,
                    emisiones: emisiones.map(e => e.id),
                    result: data
                }
            }));
            
        } catch (error) {
            console.error('Apply series error:', error);
            showToast('Error al aplicar asignación', 'error');
        } finally {
            setApplying(false);
        }
    }

    // Limpiar estado cuando se cierra el modal
    cleanup() {
        this.resetState();
        this.isInitialized = false;
    }
}

// Instancia global
const seriesWizard = new SeriesWizard();

// Escuchar cuando se cierra el modal para cleanup
document.addEventListener('modal:closed', (e) => {
    if (e.detail.modalId === 'modal-series-wizard') {
        seriesWizard.cleanup();
    }
});

// Escuchar cuando se crea una obra para refrescar búsqueda
document.addEventListener('obra:creada', (e) => {
    if (e.detail?.TituloObra) {
        // Si el wizard está abierto, seleccionar la obra recién creada
        const modal = document.getElementById('modal-series-wizard');
        if (modal && !modal.classList.contains('hidden')) {
            seriesWizard.selectObraGeneral(e.detail);
        }
    }
});

// Exponer función global para compatibilidad
window.openSeriesWizard = (emisiones) => seriesWizard.open(emisiones);

export default seriesWizard;