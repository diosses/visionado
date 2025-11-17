/**
 * Simple State Manager para el proyecto Visionado
 * SE ENFOCA EN: Estados de aplicación, no en UI específica
 */

class SimpleStateManager {
    constructor() {
        this.state = {};
        this.listeners = {};
    }

    // Setter de estado con notificación
    setState(key, value) {
        const oldValue = this.state[key];
        this.state[key] = value;
        
        // Notificar a listeners
        if (this.listeners[key]) {
            this.listeners[key].forEach(callback => {
                try {
                    callback(value, oldValue);
                } catch(e) {
                    console.error('Error in state listener:', e);
                }
            });
        }
    }

    // Getter de estado
    getState(key) {
        return this.state[key];
    }

    // Suscribirse a cambios de estado
    subscribe(key, callback) {
        if (!this.listeners[key]) {
            this.listeners[key] = [];
        }
        this.listeners[key].push(callback);
    }

    // Helper para AJAX requests con loading states
    async ajaxRequest(url, options = {}) {
        const requestId = `ajax_${Date.now()}`;
        
        try {
            // Marcar como loading
            this.setState(requestId, { loading: true, error: null });
            
            const response = await fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json',
                    ...options.headers
                },
                ...options
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            
            // Marcar como exitoso
            this.setState(requestId, { loading: false, data, error: null });
            
            return data;
        } catch (error) {
            // Marcar como error
            this.setState(requestId, { loading: false, data: null, error: error.message });
            throw error;
        }
    }

    // Helper para bulk selections (ESTO SÍ ES ESTADO DE APP)
    setBulkSelection(items) {
        this.setState('bulkSelection', items);
        this.setState('bulkCount', items.length);
        
        // Disparar evento para que UI se actualice
        document.dispatchEvent(new CustomEvent('bulk:changed', { 
            detail: { items, count: items.length } 
        }));
    }

    getBulkSelection() {
        return this.getState('bulkSelection') || [];
    }

    // Estados de filtros/búsquedas
    setFilters(filters) {
        this.setState('filters', filters);
    }

    getFilters() {
        return this.getState('filters') || {};
    }

    // Estado de usuario actual
    setCurrentUser(user) {
        this.setState('currentUser', user);
    }

    getCurrentUser() {
        return this.getState('currentUser');
    }
}

// Instancia global
window.StateManager = new SimpleStateManager();

export default window.StateManager;