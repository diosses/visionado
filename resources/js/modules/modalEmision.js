// Modal Emisión: apertura, prefill, capítulos y guardado por AJAX

class ModalEmisionModule {
  constructor() {
    this.modalId = 'modal-emision';
    this.formId = 'form-emision';
    this.bound = false;
    this.init();
  }

  init() {
    // Bind once: form submit and typeahead attach after swaps
    document.addEventListener('DOMContentLoaded', () => {
      this.bindFormSubmit();
      this.bindTypeahead();
    });
    document.addEventListener('ajax:swapped', () => {
      this.bindFormSubmit();
      this.bindTypeahead();
    });
  }

  open(data = null) {
    const modal = document.getElementById(this.modalId);
    const form = document.getElementById(this.formId);
    if (!modal || !form) return;

    // Reset and show
    try { form.reset(); } catch {}
    this.resetCapitulos();
    modal.classList.remove('hidden');

    // Robust detect edit vs create
    const isEdit = !!(data && (data.id || data.emision_id));
    const titleEl = document.getElementById('modal-emision-title');
    if (titleEl) titleEl.textContent = isEdit ? 'Editar Emisión' : 'Crear Emisión';

    if (data) {
      // IDs and basic fields
      this.setValue('emision-id', data.id || data.emision_id || '');
      this.setValue('emision-titulo', data.TituloEmision || data.titulo || '');
      // Normalizar fecha a YYYY-MM-DD para input date si viene como objeto/ISO
      let rawFecha = data.fecha_emision || data.fecha || '';
      if (rawFecha && typeof rawFecha === 'string') {
        // Extraer primario YYYY-MM-DD si viene con tiempo
        const m = rawFecha.match(/^(\d{4}-\d{2}-\d{2})/);
        if (m) rawFecha = m[1];
      } else if (rawFecha && rawFecha instanceof Date) {
        const month = String(rawFecha.getMonth()+1).padStart(2,'0');
        const day = String(rawFecha.getDate()).padStart(2,'0');
        rawFecha = rawFecha.getFullYear() + '-' + month + '-' + day;
      }
      this.setValue('emision-fecha', rawFecha || '');
      // Normalizar horas: aceptar formatos HH:MM o HH:MM:SS y convertir a HH:MM:SS (24h)
      const normalizeTime = (t) => {
        if (!t || typeof t !== 'string') return '';
        // Extraer solo la parte de tiempo si viene con fecha
        const m = t.match(/(\d{2}:\d{2}(?::\d{2})?)/);
        if (!m) return '';
        let core = m[1];
        // Si viene sin segundos, añadir :00
        if (/^\d{2}:\d{2}$/.test(core)) core += ':00';
        // Validar rango básico
        const parts = core.split(':').map(p => parseInt(p,10));
        if (parts.some(isNaN) || parts[0] > 23 || parts[1] > 59 || parts[2] > 59) return '';
        return parts.map((n,i) => String(n).padStart(2,'0')).join(':');
      };
      this.setValue('emision-hora-inicio', normalizeTime(data.hora_inicio || data.horaInicio || ''));
      this.setValue('emision-hora-fin', normalizeTime(data.hora_fin || data.horaFin || ''));

      // Canal select: prefer id, else try matching by name
      const canalSel = document.getElementById('emision-canal');
      if (canalSel) {
        if (data.canal_id) {
          canalSel.value = String(data.canal_id);
        } else if (data.canal && typeof data.canal === 'string') {
          const target = (data.canal || '').toString().trim().toLowerCase();
          const found = Array.from(canalSel.options).find(o => o.textContent.trim().toLowerCase() === target);
          canalSel.value = found ? found.value : '';
        } else if (data.canal && typeof data.canal === 'object' && data.canal.id) {
          canalSel.value = String(data.canal.id);
        } else {
          canalSel.value = '';
        }
      }

      // Obra assignment
      const obraName = (data.obra && typeof data.obra === 'object') ? (data.obra.titulo || data.obra.TituloObra) : (data.obra || '');
      const obraId = data.obra_id || (data.obra && typeof data.obra === 'object' ? (data.obra.id || data.obra.NMObra) : null) || '';
      this.setValue('emision-obra-search', obraName || '');
      this.setValue('emision-obra-id', obraId || '');

      if (obraId) {
        // Try to load capítulos for parent obras
        this.loadCapitulos(obraId);
      }
    }

    // Ensure typeahead is bound (idempotent)
    this.bindTypeahead();
  }

  setValue(inputId, value) {
    const el = document.getElementById(inputId);
    if (el) el.value = value ?? '';
  }

  resetCapitulos() {
    const panel = document.getElementById('emision-obra-capitulos-panel');
    const select = document.getElementById('emision-obra-capitulos-select');
    if (panel) panel.classList.add('hidden');
    if (select) select.innerHTML = '';
  }

  async loadCapitulos(obraId) {
    const panel = document.getElementById('emision-obra-capitulos-panel');
    const select = document.getElementById('emision-obra-capitulos-select');
    if (!panel || !select || !obraId) return;
    try {
      panel.classList.remove('hidden');
      select.innerHTML = '<option value="" disabled selected>Cargando capítulos…</option>';
      const res = await fetch(`/obras/${obraId}/capitulos`, { headers: { 'Accept': 'application/json' } });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const list = await res.json();
      const caps = Array.isArray(list) ? list : (Array.isArray(list?.data) ? list.data : []);
      if (!caps.length) {
        // Hide panel when there are no children
        panel.classList.add('hidden');
        select.innerHTML = '';
        return;
      }
      const options = ['<option value="" disabled selected>Selecciona un capítulo…</option>'];
      caps.forEach(c => {
        const id = c.NMObra || c.id; const tit = c.TituloObra || c.titulo || c.label || `Capítulo ${id}`;
        options.push(`<option value="${id}">${tit}</option>`);
      });
      select.innerHTML = options.join('');
      select.onchange = () => {
        const chosen = select.value;
        this.setValue('emision-obra-id', chosen || obraId);
      };
    } catch (e) {
      console.warn('Capítulos fetch failed', e);
      panel.classList.add('hidden');
      select.innerHTML = '';
    }
  }

  bindTypeahead() {
    const modal = document.getElementById(this.modalId);
    if (!modal) return;
    const wrapper = modal.querySelector('[data-obra-typeahead]');
    if (!wrapper || wrapper.dataset.boundEmisionTypeahead) return;
    const url = wrapper.getAttribute('data-typeahead-url');
    if (window.Typeahead?.attach && url) {
      try {
        window.Typeahead.attach(wrapper, {
          url,
          onSelect: (item) => {
            const id = item.NMObra || item.id;
            const label = item.label || item.TituloObra || '';
            this.setValue('emision-obra-search', label);
            this.setValue('emision-obra-id', id || '');
            if (id) this.loadCapitulos(id);
          }
        });
        wrapper.dataset.boundEmisionTypeahead = '1';
      } catch {}
    }
  }

  bindFormSubmit() {
    const form = document.getElementById(this.formId);
    if (!form || form.dataset.boundAjax) return;
    form.dataset.boundAjax = '1';
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const submitBtn = form.querySelector('button[type="submit"]');
      if (submitBtn) submitBtn.disabled = true;
      try {
        const normTime = (val) => {
          if (!val) return null;
          // Ya puede venir HH:MM o HH:MM:SS
          if (/^\d{2}:\d{2}$/.test(val)) return val + ':00';
          if (/^\d{2}:\d{2}:\d{2}$/.test(val)) return val;
          return val; // fallback sin alterar
        };
        const payload = {
          id: document.getElementById('emision-id')?.value || null,
          TituloEmision: document.getElementById('emision-titulo')?.value || '',
            canal_id: document.getElementById('emision-canal')?.value || null,
          fecha_emision: document.getElementById('emision-fecha')?.value || null,
          hora_inicio: normTime(document.getElementById('emision-hora-inicio')?.value || null),
          hora_fin: normTime(document.getElementById('emision-hora-fin')?.value || null),
          obra_id: document.getElementById('emision-obra-id')?.value || null,
        };
        let saved = null;
        try {
          if (typeof window.simpleAjax === 'function') {
            saved = await window.simpleAjax('/emisiones/save', { method: 'POST', body: JSON.stringify(payload) });
          } else {
            const headers = { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
            const token = document.querySelector('meta[name="csrf-token"]')?.content; if (token) headers['X-CSRF-TOKEN'] = token;
            const res = await fetch('/emisiones/save', { method: 'POST', headers, body: JSON.stringify(payload) });
            if (!res.ok) {
              let errBody = null; try { errBody = await res.json(); } catch {}
              const msg = errBody?.message || errBody?.error || ('HTTP ' + res.status);
              throw new Error(msg);
            }
            saved = await res.json().catch(()=>({}));
          }
        } catch (e) {
          throw e; // escalate to outer catch for toast
        }
        window.showToast?.(saved?.message || 'Emisión guardada','success');
        // Close modal
        const modal = document.getElementById(this.modalId);
        if (modal) modal.classList.add('hidden');
        // Refresh active tab
        try {
          await (window.refreshActiveTab?.() || Promise.resolve());
        } catch {}
      } catch (err) {
        console.error(err);
        const msg = err?.message || 'Error guardando emisión';
        window.showToast?.(msg,'error');
      } finally {
        if (submitBtn) submitBtn.disabled = false;
      }
    });
  }
}

// Instantiate and expose open() for existing callers in app.js
const ModalEmision = new ModalEmisionModule();
window.showEmisionModal = (data) => ModalEmision.open(data);

export default ModalEmision;
