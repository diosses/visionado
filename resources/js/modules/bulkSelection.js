/**
 * Bulk Selection - Módulo dedicado
 * Maneja toda la lógica de selección masiva y acciones en lote
 */

class BulkSelection {
    constructor() {
        this.selectedItems = new Set();
        this.currentContext = null; // 'emisiones', 'obras', etc.
        this.isInitialized = false;
        
        // Configuración por contexto
        this.config = {
            emisiones: {
                containerSelector: '#emisiones-table, [data-emisiones-context]',
                itemSelector: '.emision-nested-row, .emision-row[data-nmemision]:not([data-bulk-emision-group])',
                checkboxSelector: 'input[data-bulk-emision]',
                idAttribute: 'data-nmemision',
                actionsSelector: '#bulk-actions-emisiones, [data-bulk-actions="emisiones"]',
                group: {
                    parentSelector: 'input[data-bulk-emision-group]',
                    childRowSelector: '.emision-nested-row'
                }
            },
            obras: {
                containerSelector: '#obras-table, [data-obras-context]',
                itemSelector: '.obra-nested-row, .obra-row[data-nmobra]:not([data-bulk-obra-group])',
                checkboxSelector: '.obra-checkbox, input[data-bulk-obra]',
                idAttribute: 'data-nmobra',
                actionsSelector: '#bulk-actions-obras, [data-bulk-actions="obras"]',
                group: {
                    parentSelector: 'input[data-bulk-obra-group]',
                    childRowSelector: '.obra-nested-row'
                }
            },
            visionados: {
                containerSelector: '#visionados-table',
                // Incluir filas nested y filas single (no agrupadas). Las filas parent usan checkbox data-bulk-visionado-group
                itemSelector: '.visionado-nested-row, .visionado-row[data-asignacion]:not([data-bulk-visionado-group])',
                checkboxSelector: 'input[data-bulk-visionado]',
                idAttribute: 'data-asignacion',
                actionsSelector: '#bulk-actions-visionados, [data-bulk-actions="visionados"]',
                group: {
                    parentSelector: 'input[data-bulk-visionado-group]',
                    childRowSelector: '.visionado-nested-row'
                }
            }
        };
    }

    // Construye un selector seguro aplicando el filtro [idAttribute="value"]
    // a cada parte cuando itemSelector contiene múltiples selectores separados por coma.
    buildItemSelector(config, itemId) {
        const parts = String(config.itemSelector || '').split(',').map(s => s.trim()).filter(Boolean);
        const filtered = parts.map(p => `${p}[${config.idAttribute}="${itemId}"]`);
        return filtered.join(', ');
    }

    // Inicializar para un contexto específico
    initialize(context = 'emisiones') {
        this.currentContext = context;
        this.selectedItems.clear();
        
        if (!this.config[context]) {
            console.warn(`Bulk context '${context}' not configured`);
            return;
        }
        
        this.setupEventListeners();
        this.updateUI();
        this.isInitialized = true;
    }

    // Setup de event listeners
    setupEventListeners() {
        if (!this.currentContext) return;
        
        const config = this.config[this.currentContext];
        
        // Bind checkboxes individuales
        this.bindIndividualCheckboxes(config);
        
        // Bind checkbox maestro
        this.bindMasterCheckbox(config);
        
        // Bind botones de acción
        this.bindActionButtons(config);
        
        // Escuchar cambios en el DOM
        this.observeChanges(config);
    }

    // Bind checkboxes individuales
    bindIndividualCheckboxes(config) {
        const container = document.querySelector(config.containerSelector);
        if (!container) return;

        // Evitar múltiples bindings en el mismo contenedor
        if (container.dataset.boundBulkChange) return;
        container.dataset.boundBulkChange = '1';

        // Event delegation para checkboxes
        container.addEventListener('change', (e) => {
            const checkbox = e.target;
            
            // Manejar checkboxes de grupo de manera genérica
            if (config.group && checkbox.matches(config.group.parentSelector)) {
                const groupKey = checkbox.getAttribute('data-group');
                if (groupKey) {
                    this.toggleGroup(groupKey, checkbox.checked);
                    this.updateUI();
                }
                return;
            }
            
            if (!checkbox.matches(config.checkboxSelector)) return;
            
            const item = checkbox.closest(config.itemSelector);
            if (!item) return;
            
            const itemId = item.getAttribute(config.idAttribute);
            if (!itemId) return;
            
            if (checkbox.checked) {
                this.selectItem(itemId, item);
            } else {
                this.deselectItem(itemId);
            }
            
            this.updateUI();
        });
    }

    // Bind checkbox maestro (seleccionar todo)
    bindMasterCheckbox(config) {
        const masterCheckbox = document.querySelector(`[data-bulk-master="${this.currentContext}"]`);
        if (!masterCheckbox || masterCheckbox.dataset.boundBulk) return;
        
        masterCheckbox.dataset.boundBulk = '1';
        masterCheckbox.addEventListener('change', () => {
            if (masterCheckbox.checked) {
                this.selectAll();
            } else {
                this.deselectAll();
            }
            this.updateUI();
        });
    }

    // Bind botones de acción
    bindActionButtons(config) {
        const actionsContainer = document.querySelector(config.actionsSelector);
        if (!actionsContainer) return;
        
        // Botón series wizard
        const seriesBtn = actionsContainer.querySelector('[data-bulk-action="series"]');
        if (seriesBtn && !seriesBtn.dataset.boundBulkAction) {
            seriesBtn.dataset.boundBulkAction = '1';
            seriesBtn.addEventListener('click', () => this.openSeriesWizard());
        }
        
        // Botón identificar obra en lote
        const identifyBtn = actionsContainer.querySelector('[data-bulk-action="identify"]');
        if (identifyBtn && !identifyBtn.dataset.boundBulkAction) {
            identifyBtn.dataset.boundBulkAction = '1';
            identifyBtn.addEventListener('click', () => {
                // Lógica legacy: llamar a la función global si existe
                if (typeof window.openIdentificarObraModal === 'function') {
                    // Pasar ids de emisiones seleccionadas
                    const ids = Array.from(this.selectedItems);
                    window.openIdentificarObraModal({ group_emision_ids: ids });
                } else {
                    showToast('Función de identificar en lote no disponible', 'error');
                }
            });
        }

        // Botón eliminar/desasignar
        const deleteBtn = actionsContainer.querySelector('[data-bulk-action="delete"]');
        if (deleteBtn && !deleteBtn.dataset.boundBulkAction) {
            deleteBtn.dataset.boundBulkAction = '1';
            deleteBtn.addEventListener('click', () => this.bulkDelete());
        }

        // Botón exportar
        const exportBtn = actionsContainer.querySelector('[data-bulk-action="export"]');
        if (exportBtn && !exportBtn.dataset.boundBulkAction) {
            exportBtn.dataset.boundBulkAction = '1';
            exportBtn.addEventListener('click', () => this.bulkExport());
        }

        // Botón editar masivo
        const editBtn = actionsContainer.querySelector('[data-bulk-action="edit"]');
        if (editBtn && !editBtn.dataset.boundBulkAction) {
            editBtn.dataset.boundBulkAction = '1';
            editBtn.addEventListener('click', () => this.bulkEdit());
        }
    }

    // Observar cambios en el DOM
    observeChanges(config) {
        const container = document.querySelector(config.containerSelector);
        if (!container) return;

        // Evitar múltiples observers
        if (this.domObserver) {
            try { this.domObserver.disconnect(); } catch {}
            this.domObserver = null;
        }
        if (container.dataset.boundBulkObserver) return;
        container.dataset.boundBulkObserver = '1';
        
        // MutationObserver para detectar nuevos elementos
        const observer = new MutationObserver(() => {
            this.refreshCheckboxStates();
        });
        
        observer.observe(container, {
            childList: true,
            subtree: true
        });
        
        // Guardar referencia para cleanup
        this.domObserver = observer;
    }

    // Seleccionar item
    selectItem(itemId, itemElement = null) {
        this.selectedItems.add(itemId);
        
        // Actualizar estado visual si tenemos el elemento
        if (itemElement) {
            itemElement.classList.add('selected', 'bg-blue-50');
        }
        
        // Disparar evento
        this.dispatchSelectionEvent('item:selected', { itemId, itemElement });
    }

    // Deseleccionar item
    deselectItem(itemId) {
        this.selectedItems.delete(itemId);
        
        // Actualizar estado visual
        const config = this.config[this.currentContext];
        if (config) {
            const item = document.querySelector(this.buildItemSelector(config, itemId));
            if (item) {
                item.classList.remove('selected', 'bg-blue-50');
                const checkbox = item.querySelector(config.checkboxSelector);
                if (checkbox) checkbox.checked = false;
            }
        }
        
        // Disparar evento
        this.dispatchSelectionEvent('item:deselected', { itemId });
    }

    // Seleccionar todo
    selectAll() {
        const config = this.config[this.currentContext];
        if (!config) return;
        
        const container = document.querySelector(config.containerSelector) || document;
        const items = container.querySelectorAll(config.itemSelector);
        items.forEach(item => {
            // Solo considerar elementos que tengan un checkbox válido
            const checkbox = item.querySelector(config.checkboxSelector);
            if (!checkbox) return;

            const itemId = item.getAttribute(config.idAttribute);
            if (itemId) {
                this.selectedItems.add(itemId);
                item.classList.add('selected', 'bg-blue-50');
                checkbox.checked = true;
            }
        });

        // Pase adicional: por si existen checkboxes válidos fuera de itemSelector
        const extraCheckboxes = container.querySelectorAll(config.checkboxSelector);
        extraCheckboxes.forEach(cb => {
            const row = cb.closest(config.itemSelector);
            if (row) return; // ya manejado
            const tr = cb.closest('tr');
            if (tr) {
                const id = tr.getAttribute(config.idAttribute);
                if (id) {
                    this.selectedItems.add(id);
                    tr.classList.add('selected', 'bg-blue-50');
                    cb.checked = true;
                }
            }
        });
        
        this.dispatchSelectionEvent('all:selected', { count: this.selectedItems.size });
        // Forzar sincronización visual de todos los checkboxes tras selección total
        this.refreshCheckboxStates();
    }

    // Deseleccionar todo
    deselectAll() {
        const config = this.config[this.currentContext];
        if (!config) return;

        // Limpiar todos los checkboxes en el contexto dentro de los items conocidos
        const container = document.querySelector(config.containerSelector) || document;
        const items = container.querySelectorAll(config.itemSelector);
        items.forEach(item => {
            item.classList.remove('selected', 'bg-blue-50');
            const checkbox = item.querySelector(config.checkboxSelector);
            if (checkbox) checkbox.checked = false;
        });

        // Pase adicional: asegurar que cualquier checkbox del contexto quede desmarcado
        // (cubre casos en que el markup no coincida exactamente con itemSelector)
        const contextCheckboxes = container.querySelectorAll(config.checkboxSelector);
        contextCheckboxes.forEach(cb => {
            cb.checked = false;
            const tr = cb.closest('tr');
            if (tr) tr.classList.remove('selected', 'bg-blue-50');
        });

        // Limpiar el set de seleccionados
        this.selectedItems.clear();

        // Actualizar checkbox maestro
        const masterCheckbox = document.querySelector(`[data-bulk-master="${this.currentContext}"]`);
        if (masterCheckbox) masterCheckbox.checked = false;

        // Limpiar checkboxes padre (genérico por contexto)
        if (this.config[this.currentContext]?.group?.parentSelector) {
            const parentCheckboxes = container.querySelectorAll(this.config[this.currentContext].group.parentSelector);
            parentCheckboxes.forEach(checkbox => {
                checkbox.checked = false;
                checkbox.indeterminate = false;
            });
        }

        this.dispatchSelectionEvent('all:deselected');
        // Forzar sincronización visual tras limpiar
        this.refreshCheckboxStates();
    }

    // Actualizar UI
    updateUI() {
        this.updateCounter();
        this.updateActionButtons();
        this.updateMasterCheckbox();
        this.updateParentCheckboxes();
    }

    // Actualizar contador
    updateCounter() {
        const counter = document.querySelector(`[data-bulk-counter="${this.currentContext}"]`);
        if (counter) {
            const count = this.selectedItems.size;
            counter.textContent = count > 0 ? `${count} seleccionados` : '';
        }
        
        // Mostrar/ocultar panel de acciones
        const config = this.config[this.currentContext];
        if (config) {
            const actionsPanel = document.querySelector(config.actionsSelector);
            if (actionsPanel) {
                if (this.selectedItems.size > 0) {
                    actionsPanel.classList.remove('hidden');
                } else {
                    actionsPanel.classList.add('hidden');
                }
            }
        }
    }

    // Actualizar botones de acción
    updateActionButtons() {
        const config = this.config[this.currentContext];
        if (!config) return;
        
        const actionsContainer = document.querySelector(config.actionsSelector);
        if (!actionsContainer) return;
        
        const hasSelection = this.selectedItems.size > 0;
        const buttons = actionsContainer.querySelectorAll('button[data-bulk-action]');
        
        buttons.forEach(button => {
            button.disabled = !hasSelection;
        });
    }

    // Actualizar checkbox maestro
    updateMasterCheckbox() {
        const masterCheckbox = document.querySelector(`[data-bulk-master="${this.currentContext}"]`);
        if (!masterCheckbox) return;
        
        const config = this.config[this.currentContext];
        if (!config) return;
        const container = document.querySelector(config.containerSelector) || document;
        // Contar sólo elementos realmente seleccionables: los que tienen checkbox de item
        const totalItems = container.querySelectorAll(config.checkboxSelector).length;
        const selectedCount = this.selectedItems.size;
        
        if (selectedCount === 0) {
            masterCheckbox.checked = false;
            masterCheckbox.indeterminate = false;
        } else if (selectedCount === totalItems) {
            masterCheckbox.checked = true;
            masterCheckbox.indeterminate = false;
        } else {
            masterCheckbox.checked = false;
            masterCheckbox.indeterminate = true;
        }
    }

    // Alternar selección de grupo (genérico por contexto)
    toggleGroup(groupKey, checked) {
        if (!groupKey) return;

        const config = this.config[this.currentContext];
        const container = document.querySelector(config.containerSelector) || document;
        if (!config?.group?.childRowSelector) return;

        const rows = container.querySelectorAll(`${config.group.childRowSelector}[data-group="${groupKey}"]`);
        const idAttribute = config.idAttribute;

        rows.forEach(row => {
            const id = row.getAttribute(idAttribute);
            if (!id) return;
            
            const checkbox = row.querySelector(config.checkboxSelector);
            if (checked) {
                this.selectedItems.add(id);
                row.classList.add('selected', 'bg-blue-50');
                if (checkbox) checkbox.checked = true;
            } else {
                this.selectedItems.delete(id);
                row.classList.remove('selected', 'bg-blue-50');
                if (checkbox) checkbox.checked = false;
            }
        });

        // En contexto 'obras', incluir/excluir también el padre para garantizar borrado conjunto
        if (this.currentContext === 'obras') {
            const parentRow = container.querySelector(`.obra-row[${idAttribute}="${groupKey}"]`);
            if (parentRow) {
                const parentCheckbox = parentRow.querySelector(config.group.parentSelector) || parentRow.querySelector(config.checkboxSelector);
                if (checked) {
                    this.selectedItems.add(groupKey);
                    parentRow.classList.add('selected', 'bg-blue-50');
                    if (parentCheckbox) parentCheckbox.checked = true;
                } else {
                    this.selectedItems.delete(groupKey);
                    parentRow.classList.remove('selected', 'bg-blue-50');
                    if (parentCheckbox) {
                        parentCheckbox.checked = false;
                        parentCheckbox.indeterminate = false;
                    }
                }
            }
        }
    }

    // Actualizar estado de checkboxes padre basado en sus hijos (genérico por contexto)
    updateParentCheckboxes() {
        const cfg = this.config[this.currentContext];
        if (!cfg?.group) return;
        const container = document.querySelector(cfg.containerSelector) || document;
        const parentCheckboxes = container.querySelectorAll(cfg.group.parentSelector);
        const childRowClass = cfg.group.childRowSelector;
        const idAttribute = cfg.idAttribute;
        
        parentCheckboxes.forEach(parentCheckbox => {
            const groupKey = parentCheckbox.getAttribute('data-group');
            if (!groupKey) return;
            
            // Encontrar todos los hijos de este grupo
            const childRows = container.querySelectorAll(`${childRowClass}[data-group="${groupKey}"]`);
            const childIds = Array.from(childRows).map(row => row.getAttribute(idAttribute)).filter(Boolean);
            
            if (childIds.length === 0) return;
            
            // Contar cuántos hijos están seleccionados
            const selectedChildIds = childIds.filter(id => this.selectedItems.has(id));
            const selectedCount = selectedChildIds.length;
            
            // Actualizar estado del checkbox padre
            if (selectedCount === 0) {
                // Ningún hijo seleccionado
                parentCheckbox.checked = false;
                parentCheckbox.indeterminate = false;
            } else if (selectedCount === childIds.length) {
                // Todos los hijos seleccionados
                parentCheckbox.checked = true;
                parentCheckbox.indeterminate = false;
            } else {
                // Algunos hijos seleccionados
                parentCheckbox.checked = false;
                parentCheckbox.indeterminate = true;
            }
        });
    }

    // Refrescar estados de checkboxes
    refreshCheckboxStates() {
        const config = this.config[this.currentContext];
        if (!config) return;
        
        this.selectedItems.forEach(itemId => {
            const item = document.querySelector(this.buildItemSelector(config, itemId));
            if (item) {
                const checkbox = item.querySelector(config.checkboxSelector);
                if (checkbox) checkbox.checked = true;
                item.classList.add('selected', 'bg-blue-50');
            }
        });
        
        this.updateUI();
    }

    // Abrir wizard de series
    openSeriesWizard() {
        if (this.currentContext !== 'emisiones') {
            showToast('Acción no disponible para este contexto', 'warning');
            return;
        }
        
        if (this.selectedItems.size === 0) {
            showToast('Selecciona al menos una emisión', 'warning');
            return;
        }
        
        // Obtener datos de emisiones seleccionadas
        const emisiones = this.getSelectedEmisionesData();
        
        // Abrir wizard
        if (window.openSeriesWizard) {
            window.openSeriesWizard(emisiones);
        } else {
            showToast('Wizard de series no disponible', 'error');
        }
    }

    // Obtener datos de emisiones seleccionadas
    getSelectedEmisionesData() {
        const emisiones = [];
        const config = this.config[this.currentContext];
        
        if (!config) return emisiones;
        
        this.selectedItems.forEach(itemId => {
            const item = document.querySelector(this.buildItemSelector(config, itemId));
            if (item) {
                const emisionData = this.extractEmisionData(item);
                if (emisionData) emisiones.push(emisionData);
            }
        });
        
        return emisiones;
    }

    // Extraer datos de emisión del DOM
    extractEmisionData(itemElement) {
        // Intentar múltiples formas de extraer datos
        const data = {};
        
        // 1. Desde dataset
        Object.assign(data, itemElement.dataset);
        
        // 2. Desde celdas de tabla
        const cells = itemElement.querySelectorAll('td[data-field]');
        cells.forEach(cell => {
            const field = cell.getAttribute('data-field');
            if (field) data[field] = cell.textContent.trim();
        });
        
        // 3. Mapeo de campos comunes
        const mappings = {
            'NMEmision': ['data-nmemision', 'data-id'],
            'Canal': ['data-canal'],
            'FechaEmisionCorta': ['data-fecha'],
            'HoraEmision': ['data-hora'],
            'Titulo': ['data-titulo']
        };
        
        Object.entries(mappings).forEach(([field, attributes]) => {
            if (!data[field]) {
                for (const attr of attributes) {
                    const value = itemElement.getAttribute(attr);
                    if (value) {
                        data[field] = value;
                        break;
                    }
                }
            }
        });

        // 4. Normalizar fecha por fila si no vino clara: buscar en celdas o atributo y convertir a yyyy-mm-dd
        if (!data.FechaEmisionCorta || /\s/.test(data.FechaEmisionCorta)) {
            const candidates = [];
            const attrFecha = itemElement.getAttribute('data-fecha');
            if (attrFecha) candidates.push(String(attrFecha));
            cells.forEach(c => { const t = c.textContent || ''; if (t) candidates.push(String(t)); });
            for (const txt of candidates) {
                const text = txt.trim();
                let m = text.match(/(\d{4}-\d{2}-\d{2})/);
                if (m) { data.FechaEmisionCorta = m[1]; break; }
                m = text.match(/(\d{2}\/\d{2}\/\d{4})/);
                if (m) { const [dd, mm, yyyy] = m[1].split('/'); data.FechaEmisionCorta = `${yyyy}-${mm}-${dd}`; break; }
            }
        }
        
        return data.NMEmision ? data : null;
    }

    // Eliminar/desasignar seleccionados
    async bulkDelete() {
        if (this.selectedItems.size === 0) {
            showToast('No hay elementos seleccionados', 'warning');
            return;
        }
        
        const confirm = await this.showConfirmDialog(
            `¿Estás seguro de eliminar ${this.selectedItems.size} elementos?`,
            'Esta acción no se puede deshacer.'
        );
        
        if (!confirm) return;
        
        try {
            const result = await this.performBulkAction('delete');
            
            if (result.success) {
                showToast(result.message || 'Elementos eliminados', 'success');
                // Capturar selección actual antes de limpiarla para que el evento lleve los IDs correctos
                const deletedIds = Array.from(this.selectedItems);
                // Disparar evento (se registrará como 'bulk:deleted') con los IDs reales eliminados
                this.dispatchSelectionEvent('deleted', { items: deletedIds });
                
                // Eliminación inmediata de filas en el DOM (feedback instantáneo)
                const config = this.config[this.currentContext];
                if (config && this.currentContext === 'obras') {
                    const parentIdsToRemove = new Set();
                    deletedIds.forEach(id => {
                        const selector = this.buildItemSelector(config, id);
                        document.querySelectorAll(selector).forEach(row => {
                            if (row.classList.contains('obra-nested-row')) {
                                const parentId = row.getAttribute('data-group');
                                if (parentId) parentIdsToRemove.add(parentId);
                            }
                            row.remove();
                        });
                    });
                    // Remover padres asociados a capítulos eliminados
                    parentIdsToRemove.forEach(pid => {
                        const parentRow = document.querySelector(`.obra-row[${config.idAttribute}="${pid}"]`);
                        if (parentRow) parentRow.remove();
                    });
                } else if (config) {
                    // Contextos distintos: eliminar filas coincidentes si existen
                    deletedIds.forEach(id => {
                        const selector = this.buildItemSelector(config, id);
                        document.querySelectorAll(selector).forEach(row => row.remove());
                    });
                }

                // Limpiar selección y actualizar UI
                this.deselectAll();
            } else {
                showToast(result.message || 'Error al eliminar', 'error');
            }
        } catch (error) {
            console.error('Bulk delete error:', error);
            showToast('Error al eliminar elementos', 'error');
        }
    }

    // Exportar seleccionados
    async bulkExport() {
        if (this.selectedItems.size === 0) {
            showToast('No hay elementos seleccionados', 'warning');
            return;
        }
        
        try {
            const result = await this.performBulkAction('export');
            
            if (result.success && result.url) {
                // Descargar archivo
                const link = document.createElement('a');
                link.href = result.url;
                link.download = result.filename || 'export.xlsx';
                link.click();
                
                showToast('Exportación completada', 'success');
            } else {
                showToast(result.message || 'Error al exportar', 'error');
            }
        } catch (error) {
            console.error('Bulk export error:', error);
            showToast('Error al exportar elementos', 'error');
        }
    }

    // Editar masivo
    bulkEdit() {
        if (this.selectedItems.size === 0) {
            showToast('No hay elementos seleccionados', 'warning');
            return;
        }
        
        // Abrir modal de edición masiva (implementar según necesidades)
        showToast('Función de edición masiva en desarrollo', 'info');
    }

    // Realizar acción masiva genérica
    async performBulkAction(action) {
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        const headers = { 
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/json'
        };
        if (token) headers['X-CSRF-TOKEN'] = token;
        
        const payload = {
            action,
            context: this.currentContext,
            items: Array.from(this.selectedItems)
        };
        
        const response = await fetch('/bulk-actions', {
            method: 'POST',
            headers,
            body: JSON.stringify(payload)
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        return await response.json();
    }

    // Mostrar diálogo de confirmación
    showConfirmDialog(title, message) {
        return new Promise((resolve) => {
            // Implementación básica - se puede mejorar con modal personalizado
            const result = confirm(`${title}\n\n${message}`);
            resolve(result);
        });
    }

    // Disparar evento de selección
    dispatchSelectionEvent(eventType, detail = {}) {
        document.dispatchEvent(new CustomEvent(`bulk:${eventType}`, {
            detail: {
                context: this.currentContext,
                selectedItems: Array.from(this.selectedItems),
                count: this.selectedItems.size,
                ...detail
            }
        }));
    }

    cleanup() {
        // Evitar emitir eventos múltiples durante cleanup
        try {
            this.deselectAll();
        } catch(_) {}
        
        if (this.domObserver) {
            this.domObserver.disconnect();
            this.domObserver = null;
        }
        
        this.isInitialized = false;
    }
}

// Instancia global
const bulkSelection = new BulkSelection();

// Nota: la inicialización se realiza desde app.js (orquestador) para evitar dobles bindings

// Exponer funciones globales para compatibilidad
window.BulkSelection = bulkSelection;
window.initBulkSelection = (context) => bulkSelection.initialize(context);

export default bulkSelection;