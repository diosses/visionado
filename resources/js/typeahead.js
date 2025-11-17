/*
 Módulo Typeahead genérico
 Uso:
    - import { initTypeahead, attachTypeahead } from './typeahead';
    - initTypeahead(document, { selector: '[data-obra-typeahead]', url: '/obras/search?q=' });
    - o attachTypeahead(element, { url: '/obras/search?q=' });

    El módulo marcará los wrappers enlazados con data-typeahead-bound para evitar doble binding.
*/

function escHtml(s){ return (s||'').toString().replace(/[&<>"]+/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m]||m)); }

// Registro interno para mapear wrappers a sus funciones select asociadas
const __typeaheadSelectMap = new WeakMap();

export function attachTypeahead(wrapper, opts={}){
    if (!wrapper || wrapper.dataset.typeaheadBound) return;
    wrapper.dataset.typeaheadBound = '1';
    const input = wrapper.querySelector('input');
    const dropdown = wrapper.querySelector('div');
    const min = parseInt(wrapper.getAttribute('data-typeahead-min') || opts.min || 2, 10);
    const delay = parseInt(wrapper.getAttribute('data-typeahead-delay') || opts.delay || 200, 10);
    const urlBase = wrapper.getAttribute('data-typeahead-url') || opts.url || null;
    let timer; let items = []; let active = -1;

    function highlight(){ if (!dropdown) return; [...dropdown.querySelectorAll('[data-idx]')].forEach((el,i)=> el.classList.toggle('bg-indigo-50', i===active)); }

    async function fetchItems(q){
        if (!urlBase) return [];
        try{
            const url = urlBase.includes('?') ? `${urlBase}&q=${encodeURIComponent(q)}` : `${urlBase}?q=${encodeURIComponent(q)}`;
            const res = await fetch(url, { headers: { 'X-Requested-With':'XMLHttpRequest' } });
            if (!res.ok) return [];
            return await res.json();
        }catch(e){ console.error('typeahead fetch', e); return []; }
    }

    input?.addEventListener('input', () => {
        const q = input.value.trim();
        clearTimeout(timer);
        if (q.length < min){ if (dropdown) { dropdown.classList.add('hidden'); dropdown.innerHTML=''; } items=[]; active=-1; return; }
        timer = setTimeout(async () => {
            items = await fetchItems(q) || [];
            if (!dropdown) return;
            dropdown.innerHTML = items.map((it, idx) => `<button type="button" data-idx="${idx}" data-id="${it.NMObra ?? it.NMActor ?? it.id}" class="block w-full text-left px-3 py-2 hover:bg-gray-100" aria-label="${escHtml(it.label)}">${escHtml(it.label)}</button>`).join('');
            dropdown.classList.toggle('hidden', items.length === 0);
            active = items.length ? 0 : -1; highlight();
        }, delay);
    });

    input?.addEventListener('keydown', (ev) => {
        if (!dropdown || dropdown.classList.contains('hidden')) return;
        if (ev.key === 'ArrowDown'){ ev.preventDefault(); active = Math.min(active+1, items.length-1); highlight(); }
        else if (ev.key === 'ArrowUp'){ ev.preventDefault(); active = Math.max(active-1, 0); highlight(); }
    else if (ev.key === 'Enter'){ ev.preventDefault(); if (active >= 0 && items[active]) select(items[active], false); } // false = manual
        else if (ev.key === 'Escape'){ dropdown.classList.add('hidden'); }
    });

    dropdown?.addEventListener('click', ev => {
        const btn = ev.target.closest('button[data-idx]');
    if (!btn) return; ev.preventDefault(); const idx = parseInt(btn.dataset.idx||'-1',10); const it = Number.isInteger(idx) && idx >= 0 ? items[idx] : null; select(it ?? { id: btn.dataset.id, label: btn.textContent.trim() }, false); // false = manual
    });

    document.addEventListener('click', ev => { if (!wrapper.contains(ev.target)) dropdown?.classList.add('hidden'); });

    function select(it, isAutomatic = false){
        if (!it) return;
        const id = it.NMObra ?? it.NMActor ?? it.id;
        wrapper.dataset.selected = id;
        if (input) input.value = it.label || input.value;
        dropdown?.classList.add('hidden');
        const selHint = wrapper.parentElement?.querySelector('[data-obra-selected-label]');
        if (selHint) selHint.textContent = it.label;
        const selectedWrap = wrapper.parentElement?.querySelector('[data-obra-selected]');
        if (selectedWrap) selectedWrap.classList.remove('hidden');
        try { 
            // Incluir información sobre si es selección automática en el evento
            wrapper.dispatchEvent(new CustomEvent('typeahead:select', { 
                detail: { ...it, isAutomatic } 
            })); 
    } catch(e) { /* ignorar navegadores antiguos */ }
    }

    // Registrar select para este wrapper para que código externo pueda dispararlo
    try { __typeaheadSelectMap.set(wrapper, select); } catch(_) {}

    // Exponer select para uso programático (por wrapper)
    if (typeof window !== 'undefined') {
        window.Typeahead = window.Typeahead || {};
        window.Typeahead.select = function(targetWrapper, item, isAutomatic = true) {
            if (!targetWrapper || !item) return;
            const fn = __typeaheadSelectMap.get(targetWrapper);
            if (typeof fn === 'function') {
                fn(item, isAutomatic);
            } else {
                // Fallback: actualización best-effort de estado mínimo y evento
                try {
                    const input = targetWrapper.querySelector('input');
                    if (input) input.value = item.label || item.TituloObra || '';
                    targetWrapper.dataset.selected = item.NMObra ?? item.id ?? '';
                    const selWrap = targetWrapper.parentElement?.querySelector('[data-obra-selected]');
                    if (selWrap) selWrap.classList.remove('hidden');
                    const selHint = targetWrapper.parentElement?.querySelector('[data-obra-selected-label]');
                    if (selHint) selHint.textContent = item.label || item.TituloObra || '';
                    targetWrapper.dispatchEvent(new CustomEvent('typeahead:select', { 
                        detail: { ...item, isAutomatic } 
                    }));
                } catch(_) {}
            }
        };
    }
    const clearBtn = wrapper.parentElement?.querySelector('[data-obra-clear]');
    if (clearBtn) clearBtn.addEventListener('click', () => { delete wrapper.dataset.selected; const selWrap = wrapper.parentElement?.querySelector('[data-obra-selected]'); if (selWrap) selWrap.classList.add('hidden'); });
}

export function initTypeahead(root=document, cfg={}){
    const selector = cfg.selector || '[data-obra-typeahead],[data-elenco-typeahead],[data-typeahead]';
    root.querySelectorAll(selector).forEach(el => attachTypeahead(el, cfg));
}

// Exponer init por defecto en window para scripts inline
if (typeof window !== 'undefined') {
    window.Typeahead = Object.assign(window.Typeahead || {}, { init: initTypeahead, attach: attachTypeahead });
}
