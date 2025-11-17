/**
 * Helper functions para simplificar AJAX y UI updates
 * Funciones que puedes copiar/pegar fácilmente con IA
 */

// Helper para mostrar toasts/notificaciones
window.showToast = function(message, type = 'info') {
    // Crear toast si no existe
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'fixed top-4 right-4 z-50 space-y-2';
        document.body.appendChild(toastContainer);
    }

    const toast = document.createElement('div');
    const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        warning: 'bg-yellow-500',
        info: 'bg-blue-500'
    };

    toast.className = `${colors[type] || colors.info} text-white px-4 py-2 rounded shadow-lg opacity-0 transition-opacity duration-300`;
    toast.textContent = message;

    toastContainer.appendChild(toast);

    // Fade in
    setTimeout(() => toast.classList.remove('opacity-0'), 100);

    // Auto remove
    setTimeout(() => {
        toast.classList.add('opacity-0');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
};

// Helper para hacer AJAX calls simples
window.simpleAjax = async function(url, options = {}) {
    try {
        const token = document.querySelector('meta[name="csrf-token"]')?.content;

        const defaultHeaders = {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json, text/plain, */*',
        };
        // Si el caller envía un body string/objeto, por defecto asumimos JSON
        // EXCEPTO cuando es FormData (dejar que el navegador calcule el boundary)
        const isFormData = (typeof FormData !== 'undefined') && (options?.body instanceof FormData);
        const contentType = options?.headers?.['Content-Type'] || options?.headers?.['content-type'] || (options.body && !isFormData ? 'application/json' : undefined);
        if (contentType) defaultHeaders['Content-Type'] = contentType;
        if (token) defaultHeaders['X-CSRF-TOKEN'] = token;

        const response = await fetch(url, {
            ...options,
            headers: {
                ...defaultHeaders,
                ...(options.headers || {}),
            }
        });

        if (!response.ok) {
            // Intentar extraer mensaje de error del servidor si es JSON
            const respCt = response.headers.get('content-type') || '';
            let serverMsg = '';
            try {
                if (respCt.includes('application/json')) {
                    const data = await response.json();
                    serverMsg = data?.message || JSON.stringify(data);
                } else {
                    serverMsg = await response.text();
                }
            } catch {
                /* noop */
            }
            const err = new Error(`HTTP ${response.status}${serverMsg ? ': ' + serverMsg : ''}`);
            throw err;
        }

        // 204/205 no content
        if (response.status === 204 || response.status === 205) return null;

        const respCt = response.headers.get('content-type') || '';
        // Si es JSON, parsear; si no, devolver texto crudo para el caller
        if (respCt.includes('application/json')) {
            try { return await response.json(); } catch { return null; }
        }
        // Non-JSON OK response
        const text = await response.text();
        return { ok: true, raw: text };
    } catch (error) {
        console.error('AJAX Error:', error);
        window.showToast('Error en la solicitud', 'error');
        throw error;
    }
};

// Helper para cargar contenido via AJAX en containers
window.loadContent = async function(url, containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;

    try {
        // Mostrar loading
        container.innerHTML = '<div class="p-4 text-center">Cargando...</div>';
        
        const response = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        
        const html = await response.text();
        container.innerHTML = html;
        
        // Trigger event para re-bind listeners
        container.dispatchEvent(new CustomEvent('content:loaded', { bubbles: true }));
        
    } catch (error) {
        container.innerHTML = `<div class="p-4 text-red-500">Error cargando contenido</div>`;
        console.error('Load content error:', error);
    }
};

// Helper para bulk actions
window.collectBulkIds = function(selector = '[data-bulk="row"]:checked') {
    return Array.from(document.querySelectorAll(selector)).map(el => el.value);
};

// Helper para toggle de UI elements
window.toggleUI = function(elementId, show = null) {
    const element = document.getElementById(elementId);
    if (!element) return;

    if (show === null) {
        element.classList.toggle('hidden');
    } else if (show) {
        element.classList.remove('hidden');
    } else {
        element.classList.add('hidden');
    }
};

// Helper para limpiar forms
window.clearForm = function(formId) {
    const form = document.getElementById(formId);
    if (form && form.reset) form.reset();
};

// Helper para disable/enable buttons durante AJAX
window.setButtonLoading = function(buttonId, loading = true) {
    const button = document.getElementById(buttonId);
    if (!button) return;

    if (loading) {
        button.disabled = true;
        button.dataset.originalText = button.textContent;
        button.textContent = 'Cargando...';
    } else {
        button.disabled = false;
        if (button.dataset.originalText) {
            button.textContent = button.dataset.originalText;
            delete button.dataset.originalText;
        }
    }
};