// Modal para asignar emisiones a usuarios (individual o en lote)
class ModalAsignarEmisionModule {
  constructor() {
    this.modalId = 'modal-asignar-emision';
    this.formId = 'form-asignar-emision';
    document.addEventListener('DOMContentLoaded', () => this.bind());
    document.addEventListener('ajax:swapped', () => this.bind());
  }

  open({ emisionId = null, emisionIds = [], items = [], preselectedUserId } = {}) {
    const modal = document.getElementById(this.modalId);
    const form = document.getElementById(this.formId);
    if (!modal || !form) return;
    try { form.reset(); } catch {}
    // Persist selection
    document.getElementById('asignar-emision-id').value = emisionId || '';
    document.getElementById('asignar-emision-ids').value = (emisionIds && emisionIds.length) ? emisionIds.join(',') : '';
    // Render list
    const ul = modal.querySelector('[data-asignar-lista]');
    if (ul) {
      ul.innerHTML = '';
      const list = items && items.length ? items : (emisionId ? [{ id: emisionId }] : (emisionIds || []).map(id => ({ id })));
      list.forEach(it => {
        const li = document.createElement('li');
        li.textContent = `#${it.id} ${it.titulo || ''}`.trim();
        ul.appendChild(li);
      });
      if (list.length === 0) {
        const li = document.createElement('li');
        li.textContent = '—'; ul.appendChild(li);
      }
    }
    // Prefill: si es una sola emisión, pedir info JSON para traer asignación actual
    const userSelect = document.getElementById('asignar-user');
    const notasInput = document.getElementById('asignar-notas');
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) submitBtn.textContent = 'Asignar';
    // Prefill user for bulk if provided
    if (preselectedUserId && userSelect) {
      userSelect.value = String(preselectedUserId);
      if (submitBtn) submitBtn.textContent = 'Actualizar';
    }
    if (emisionId && userSelect) {
      // Cargar datos actualizados de la emisión
      const doPrefill = async () => {
        try {
          const data = await (window.simpleAjax ? window.simpleAjax(`/emisiones/info/${emisionId}`, { method: 'GET' }) : fetch(`/emisiones/info/${emisionId}`, { headers: { 'Accept': 'application/json' } }).then(r => r.json()));
          const asg = data?.asignacion;
          if (asg && asg.user_id) {
            userSelect.value = String(asg.user_id);
            if (notasInput && typeof asg.notas === 'string') notasInput.value = asg.notas;
            if (submitBtn) submitBtn.textContent = 'Actualizar';
          }
        } catch (e) { /* noop */ }
      };
      doPrefill();
    }
    modal.classList.remove('hidden');
  }

  bind() {
    const form = document.getElementById(this.formId);
    if (!form || form.dataset.boundAsignar) return;
    form.dataset.boundAsignar = '1';
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const submit = form.querySelector('button[type="submit"]');
      if (submit) submit.disabled = true;
      try {
        const token = document.querySelector('meta[name="csrf-token"]').content;
        const headers = { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token };
        const user_id = document.getElementById('asignar-user').value;
        const notas = document.getElementById('asignar-notas').value || null;
        const oneId = document.getElementById('asignar-emision-id').value;
        const many = (document.getElementById('asignar-emision-ids').value || '').split(',').filter(Boolean);
        if (!user_id) { window.showToast?.('Selecciona una visionadora','warning'); return; }

        // Helper para evaluar si la respuesta del backend indica éxito
        const isOk = (data) => {
          if (!data || typeof data !== 'object') return false;
          if (data.ok === true) return true;
          if (typeof data.asignacion_id !== 'undefined') return true;
          if (typeof data.created === 'number' && data.created > 0) return true;
          return false;
        };
        const getErrMsg = (data) => {
          if (!data) return '';
          return data.message || data.error || data.errors || '';
        };

        if (many.length > 1) {
          const payload = { emision_ids: many.map(x => parseInt(x,10)).filter(n=>!isNaN(n)), user_id: parseInt(user_id,10), notas };
          let data;
          if (typeof window.simpleAjax === 'function') {
            data = await window.simpleAjax('/asignaciones/asignar-bulk', { method: 'POST', body: JSON.stringify(payload) });
          } else {
            const res = await fetch('/asignaciones/asignar-bulk', { method: 'POST', headers, body: JSON.stringify(payload) });
            if (!res.ok) throw new Error('HTTP '+res.status);
            data = await res.json().catch(() => ({}));
          }
          if (!isOk(data)) {
            const msg = getErrMsg(data) || 'El servidor no confirmó la creación.';
            throw new Error(msg);
          }
          const upd = Number(data?.count_updated || 0);
          const cre = Number(data?.count || 0) - upd;
          const parts = [];
          if (cre > 0) parts.push(`${cre} asignación${cre===1?'':'es'} creada${cre===1?'':'s'}`);
          if (upd > 0) parts.push(`${upd} reasignación${upd===1?'':'es'} exitosa${upd===1?'':'s'}`);
          const msg = parts.length ? parts.join(', ') : 'Operación completada';
          window.showToast?.(msg,'success');
          // Update buttons in current view
          try {
            const ids = payload.emision_ids || [];
            const uid = parseInt(user_id,10);
            ids.forEach(id => {
              window.setAsignarButtonState?.(id, true);
              window.markRowAssignedToUser?.(id, uid);
            });
            // Recompute group button(s). We can infer groupKeys from DOM if needed; nested updates already call recompute.
          } catch {}
        } else if (oneId || many.length === 1) {
          const emision_id = oneId || many[0];
          const payload = { user_id: parseInt(user_id,10), notas };
          let data;
          if (typeof window.simpleAjax === 'function') {
            data = await window.simpleAjax(`/asignaciones/asignar/${emision_id}`, { method: 'POST', body: JSON.stringify(payload) });
          } else {
            const res = await fetch(`/asignaciones/asignar/${emision_id}`, { method: 'POST', headers, body: JSON.stringify(payload) });
            if (!res.ok) throw new Error('HTTP '+res.status);
            data = await res.json().catch(() => ({}));
          }
          if (!isOk(data)) {
            const msg = getErrMsg(data) || 'El servidor no confirmó la creación.';
            throw new Error(msg);
          }
          const wasUpdate = !!data?.updated;
          window.showToast?.(wasUpdate ? 'Reasignación exitosa' : 'Asignación creada','success');
          // Update button in current view
          try {
            const eid = parseInt(emision_id,10);
            const uid = parseInt(user_id,10);
            window.setAsignarButtonState?.(eid, true);
            window.markRowAssignedToUser?.(eid, uid);
          } catch {}
        } else {
          window.showToast?.('No hay emisiones seleccionadas','warning');
          return;
        }

        document.getElementById(this.modalId)?.classList.add('hidden');

        // Importante: limpiar selección masiva para evitar estados corruptos al tener filas anidadas
        try { window.BulkSelection?.deselectAll(); } catch {}
        // Indicar que NO se preserve la selección en el próximo swap
        try { window.requestBulkResetOnNextSwap?.(); } catch {}

        // Do not change current tab/view; keep user in place
      } catch (err) {
        console.error(err);
        const msg = (err && err.message) ? `Error al asignar: ${err.message}` : 'Error al asignar';
        window.showToast?.(msg,'error');
      } finally {
        if (submit) submit.disabled = false;
      }
    });
  }
}

const ModalAsignarEmision = new ModalAsignarEmisionModule();
window.openAsignarEmisionModal = (data) => ModalAsignarEmision.open(data);
export default ModalAsignarEmision;
