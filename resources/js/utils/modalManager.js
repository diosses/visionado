/**
 * Modal Manager - SE ENFOCA SOLO EN: Gestión de modales
 * Separado del StateManager para responsabilidades claras
 */

class ModalManager {
    constructor() {
        this.openModals = new Set();
        this.modalData = new Map();
        this.setupGlobalListeners();
    }

    // Abrir modal con data
    open(modalId, data = {}) {
        const modal = document.getElementById(modalId);
        if (!modal) {
            console.warn(`Modal ${modalId} not found`);
            return;
        }

        // Guardar data del modal
        this.modalData.set(modalId, data);
        
        // Mostrar modal
        modal.classList.remove('hidden');
        modal.removeAttribute('aria-hidden');
        modal.setAttribute('aria-modal', 'true');
        
        // Agregar a lista de modales abiertos
        this.openModals.add(modalId);
        
        // Focus trap básico
        this.setupFocusTrap(modal);
        
        // Disparar evento personalizado
        modal.dispatchEvent(new CustomEvent('modal:opened', { 
            detail: { modalId, data },
            bubbles: true
        }));
    }

    // Cerrar modal
    close(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;

        // Ocultar modal
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
        modal.removeAttribute('aria-modal');
        
        // Remover de lista
        this.openModals.delete(modalId);
        this.modalData.delete(modalId);
        
        // Disparar evento
        modal.dispatchEvent(new CustomEvent('modal:closed', { 
            detail: { modalId },
            bubbles: true
        }));
    }

    // Cerrar todos los modales
    closeAll() {
        Array.from(this.openModals).forEach(modalId => this.close(modalId));
    }

    // Obtener data de un modal
    getData(modalId) {
        return this.modalData.get(modalId) || {};
    }

    // Verificar si modal está abierto
    isOpen(modalId) {
        return this.openModals.has(modalId);
    }

    // Setup de listeners globales
    setupGlobalListeners() {
        // Escape key para cerrar modal superior
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.openModals.size > 0) {
                const lastModal = Array.from(this.openModals).pop();
                this.close(lastModal);
            }
        });

        // Click en botones de cierre (soporta data-modal-close y data-close-modal)
        document.addEventListener('click', (e) => {
            const closeEl = e.target.closest('[data-modal-close], [data-close-modal]');
            if (closeEl) {
                e.preventDefault();
                const modal = closeEl.closest('.modal-component') || closeEl.closest('[id^="modal-"]');
                if (modal && modal.id) {
                    this.close(modal.id);
                }
            }
        });
    }

    // Focus trap básico
    setupFocusTrap(modal) {
        setTimeout(() => {
            const focusableElements = modal.querySelectorAll(
                'input, button, select, textarea, [tabindex]:not([tabindex="-1"])'
            );
            if (focusableElements.length > 0) {
                focusableElements[0].focus();
            }
        }, 100);
    }

    // Helpers específicos para modales comunes del proyecto
    openIdentificarObra(data) {
        this.open('modal-identificar-obra', data);
        
        // Lógica específica para este modal
        if (data.titulo) {
            const titleEl = document.getElementById('modal-emision-titulo');
            if (titleEl) titleEl.textContent = data.titulo;
        }
        
        if (data.sugerencia && window.mostrarSugerenciaAutomatica) {
            // Evitar mostrar sugerencia si la emisión ya tiene obra asignada (panel verde activo)
            const currentObra = document.getElementById('current-obra');
            if (!(currentObra && !currentObra.classList.contains('hidden'))) {
                window.mostrarSugerenciaAutomatica(data.sugerencia);
            }
        }
    }

    openCrearObra(options = {}) {
        this.open('modal-create-obra', options);
        
        // Pre-llenar título si viene
        if (options.titulo) {
            const titleInput = document.querySelector('#modal-create-obra input[name="TituloObra"]');
            if (titleInput) titleInput.value = options.titulo;
        }
    }

    openSeriesWizard(params = {}) {
        // Para este modal especial que se carga via AJAX
        return window.openSeriesWizard?.(params);
    }

    // Confirmación modal reutilizable
    confirm(message, options = {}) {
        return new Promise((resolve) => {
            // Crear modal de confirmación dinámico
            const modalId = 'modal-confirm-' + Date.now();
            const modal = this.createConfirmModal(modalId, message, options);
            document.body.appendChild(modal);
            
            this.open(modalId);
            
            // Setup de botones
            const confirmBtn = modal.querySelector('.confirm-yes');
            const cancelBtn = modal.querySelector('.confirm-no');
            
            const cleanup = () => {
                this.close(modalId);
                setTimeout(() => modal.remove(), 300);
            };
            
            confirmBtn.onclick = () => { cleanup(); resolve(true); };
            cancelBtn.onclick = () => { cleanup(); resolve(false); };
        });
    }

    // Crear modal de confirmación dinámico
    createConfirmModal(modalId, message, options) {
        const modal = document.createElement('div');
        modal.id = modalId;
        modal.className = 'modal-component fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50';
        modal.innerHTML = `
            <div class="bg-white rounded-lg p-6 max-w-md mx-4">
                <h3 class="text-lg font-medium mb-4">${options.title || 'Confirmar'}</h3>
                <p class="text-gray-600 mb-6">${message}</p>
                <div class="flex justify-end gap-3">
                    <button class="confirm-no px-4 py-2 text-gray-600 border rounded hover:bg-gray-50">
                        ${options.cancelText || 'Cancelar'}
                    </button>
                    <button class="confirm-yes px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                        ${options.confirmText || 'Confirmar'}
                    </button>
                </div>
            </div>
        `;
        return modal;
    }
}

// Instancia global
window.ModalManager = new ModalManager();

export default window.ModalManager;