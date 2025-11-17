// --- Visionadora inline change for tabla-visionados
function bindVisionadoraSelects(root = document) {
	root.querySelectorAll('.visionadora-select').forEach(select => {
		if (select.dataset.boundVisionadora) return;
		select.dataset.boundVisionadora = '1';
		select.addEventListener('change', async (e) => {
			const isGroup = !!select.getAttribute('data-group-select');
			const rawVal = select.value;
			if (!rawVal) return; // ignore empty separator
			if (rawVal === '__varios__') { return; } // sentinel cannot be chosen
			const userId = parseInt(rawVal, 10);
			if (isNaN(userId)) return;
			select.disabled = true;
			try {
				if (isGroup) {
					// Assign this user to all asignaciones in the group
					const idsAttr = select.getAttribute('data-group-asignaciones') || '';
					const ids = idsAttr.split(',').map(s => parseInt(s,10)).filter(n => !isNaN(n));
					for (const aid of ids) {
						await window.simpleAjax(`/asignaciones/cambiar-visionadora/${aid}`, {
							method: 'POST',
							headers: { 'Content-Type': 'application/json' },
							body: JSON.stringify({ user_id: userId })
						});
						// Update nested DOM state
						const nestedRow = root.querySelector(`.visionado-nested-row[data-asignacion="${aid}"]`);
						if (nestedRow) nestedRow.dataset.userId = String(userId);
						// Cross-view sync: if Emisiones table is present, mark corresponding row assigned
						try {
							const emisionId = nestedRow?.getAttribute('data-emision-id');
							if (emisionId && window.markRowAssignedToUser) {
								window.markRowAssignedToUser(parseInt(emisionId,10), userId);
							}
						} catch {}
					}
					window.showToast?.('Visionadora actualizada en el grupo', 'success');
					// Refresh parent select value based on updated DOM state
					const groupKey = select.getAttribute('data-group-key');
					if (groupKey) {
						const users = Array.from(root.querySelectorAll(`.visionado-nested-row[data-group="${groupKey}"]`))
							.map(r => parseInt(r.dataset.userId||'',10)).filter(n => !isNaN(n));
						const allAssigned = users.length === Array.from(root.querySelectorAll(`.visionado-nested-row[data-group="${groupKey}"]`)).length;
						const unique = Array.from(new Set(users));
						select.value = (allAssigned && unique.length === 1) ? String(unique[0]) : '__varios__';
						// Cross-view sync: recompute Emisiones group button for this groupKey if exists
						try {
							window.recomputeGroupAssignButton?.(groupKey);
						} catch {}
					}
				} else {
					const asignacionId = select.getAttribute('data-asignacion-id');
					if (!asignacionId) return;
					await window.simpleAjax(`/asignaciones/cambiar-visionadora/${asignacionId}`, {
						method: 'POST',
						headers: { 'Content-Type': 'application/json' },
						body: JSON.stringify({ user_id: userId })
					});
					window.showToast?.('Visionadora actualizada', 'success');
					// If this nested belongs to a group, update its parent select to reflect uniform/mixed
					const nestedRow = select.closest('.visionado-nested-row');
					const groupKey = nestedRow?.getAttribute('data-group');
					if (groupKey) {
						// update dataset and recompute
						nestedRow.dataset.userId = String(userId);
						const parentSel = root.querySelector(`select.visionadora-select[data-group-select="1"][data-group-key="${groupKey}"]`);
						if (parentSel) {
							const rows = Array.from(root.querySelectorAll(`.visionado-nested-row[data-group="${groupKey}"]`));
							const users = rows.map(r => parseInt(r.dataset.userId||'',10)).filter(n => !isNaN(n));
							const allAssigned = users.length === rows.length;
							const unique = Array.from(new Set(users));
							parentSel.value = (allAssigned && unique.length === 1) ? String(unique[0]) : '__varios__';
						}
						// Cross-view sync: update Emisiones table for this emission
						try {
							const emisionId = nestedRow?.getAttribute('data-emision-id');
							if (emisionId && window.markRowAssignedToUser) {
								window.markRowAssignedToUser(parseInt(emisionId,10), userId);
							}
							window.recomputeGroupAssignButton?.(groupKey);
						} catch {}
					}
				}
			} catch {
				window.showToast?.('Error al actualizar visionadora', 'error');
			} finally {
				select.disabled = false;
			}
		});
	});
}

document.addEventListener('DOMContentLoaded', () => { bindVisionadoraSelects(); });
document.addEventListener('ajax:swapped', () => { bindVisionadoraSelects(); });
// Importaciones principales
import './bootstrap';
import './typeahead';
import './utils/stateManager';
import './utils/modalManager';
import './utils/helpers';

// Cargar módulos (también registran globals legacy como window.openModalObra, etc.)
import './modules/modalIdentificarObra.js';
import './modules/modalObras.js';
import './modules/seriesWizard.js';
import './modules/modalEmision.js';
import bulkSelection from './modules/bulkSelection.js';
import ModalAsignarEmision from './modules/modalAsignarEmision.js';

// Control fino: preservar o no la selección masiva tras el próximo swap
let preserveBulkOnNextSwap = true;
window.requestBulkResetOnNextSwap = function(){ preserveBulkOnNextSwap = false; };

// --- Helper AJAX: intercambiar HTML en un elemento destino
// Inject lightweight CSS for loader once
function ensureAjaxSwapStyles(){
	if (document.getElementById('ajax-swap-styles')) return;
	const style = document.createElement('style');
	style.id = 'ajax-swap-styles';
	style.textContent = `
		.ajax-swap-loading{position:relative;}
		.ajax-swap-loading::after{content:"";position:absolute;inset:0;display:block;background:rgba(255,255,255,0.6);backdrop-filter:blur(1px);}
		.ajax-swap-spinner{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:2.75rem;height:2.75rem;border:4px solid #3b82f6;border-right-color:transparent;border-radius:50%;animation:ajaxspin 0.75s linear infinite;z-index:10;}
		@keyframes ajaxspin{to{transform:translate(-50%,-50%) rotate(360deg);}}
	`;
	document.head.appendChild(style);
}

// Enhanced ajaxSwap: supports loader + error toast; non-breaking for existing callers
// Options: { url, target, showLoader=true, toastOnError=true }
window.ajaxSwap = async function({ url, target, showLoader = true, toastOnError = true }) {
	const el = document.querySelector(target);
	let spinner;
	try {
		if (!url || !target) throw new Error('Missing url or target');
		if (showLoader && el) {
			ensureAjaxSwapStyles();
			el.classList.add('ajax-swap-loading');
			el.setAttribute('aria-busy','true');
			spinner = document.createElement('div');
			spinner.className = 'ajax-swap-spinner';
			el.appendChild(spinner);
		}
		const response = await fetch(url, { headers: { 'Accept': 'text/html, application/xhtml+xml', 'X-Requested-With':'XMLHttpRequest' } });
		if (!response.ok) throw new Error('HTTP ' + response.status);
		const html = await response.text();
		if (el) {
			el.innerHTML = html;
			el.dispatchEvent(new CustomEvent('ajax:swapped', { bubbles: true }));
		}
	} catch (error) {
		console.error('Ajax swap error:', error);
		if (toastOnError) { window.showToast?.('Error cargando contenido','error'); }
		throw error; // rethrow so callers can fallback if desired
	} finally {
		if (el) {
			el.classList.remove('ajax-swap-loading');
			el.removeAttribute('aria-busy');
			if (spinner && spinner.parentNode === el) spinner.remove();
		}
	}
};

// Reusable: toggle Asignar/Reasignar button state for a given emission row
window.setAsignarButtonState = function(emisionId, assigned = true) {
	try {
		const btn = document.querySelector(`[data-asignar-emision][data-emision-id="${emisionId}"]`);
		if (!btn) return;
		const span = btn.querySelector('span');
		btn.dataset.asignado = assigned ? '1' : '0';
		// Update classes: green for assign, yellow for reassign
		btn.classList.remove('bg-green-600','hover:bg-green-700','bg-yellow-500','hover:bg-yellow-600');
		if (assigned) {
			btn.classList.add('bg-yellow-500','hover:bg-yellow-600');
			if (span) span.textContent = 'Reasignar';
			btn.title = 'Reasignar a visionadora';
		} else {
			btn.classList.add('bg-green-600','hover:bg-green-700');
			if (span) span.textContent = 'Asignar';
			btn.title = 'Asignar a visionadora';
		}
	} catch (_) {}
};

// Mark a nested row as assigned to a specific user and recompute its group's button state
window.markRowAssignedToUser = function(emisionId, userId) {
	try {
		const row = document.querySelector(`.emision-row[data-nmemision="${emisionId}"]`) || document.querySelector(`.emision-nested-row[data-nmemision="${emisionId}"]`);
		if (!row) return;
		const norm = (val) => {
			if (val === null || val === undefined) return '';
			const n = parseInt(val, 10);
			return isNaN(n) ? String(val) : String(n);
		};
		const normalized = norm(userId);
		if (normalized) {
			row.dataset.userId = normalized;
		} else {
			delete row.dataset.userId;
		}
		// If row has a single button, update it to reassigned visually
		window.setAsignarButtonState?.(parseInt(emisionId,10), !!normalized);
		// If this row belongs to a group, recompute that group's state
		const groupKey = row.getAttribute('data-group');
		if (groupKey) {
			window.recomputeGroupAssignButton?.(groupKey);
		}
	} catch (_) {}
};

// Recompute parent group button state based on nested rows' assigned user
window.recomputeGroupAssignButton = function(groupKey) {
	try {
		const nestedRows = Array.from(document.querySelectorAll(`.emision-nested-row[data-group="${groupKey}"]`));
		if (!nestedRows.length) return;
		const norm = (val) => {
			if (val === null || val === undefined) return '';
			const n = parseInt(val, 10);
			return isNaN(n) ? String(val) : String(n);
		};
		const userIds = nestedRows.map(r => norm(r.dataset.userId)).filter(Boolean);
		const allAssigned = userIds.length === nestedRows.length && nestedRows.length > 0;
		const uniqueUsers = Array.from(new Set(userIds));
		const uniformUser = allAssigned && uniqueUsers.length === 1 ? uniqueUsers[0] : null;
		const parentBtn = document.querySelector(`[data-asignar-emision][data-group-key="${groupKey}"]`);
		if (!parentBtn) return;
		// Toggle visual state
		const span = parentBtn.querySelector('span');
		parentBtn.classList.remove('bg-green-600','hover:bg-green-700','bg-yellow-500','hover:bg-yellow-600');
		if (uniformUser) {
			parentBtn.classList.add('bg-yellow-500','hover:bg-yellow-600');
			parentBtn.setAttribute('data-prefill-user', String(uniformUser));
			parentBtn.title = 'Reasignar a visionadora';
			if (span) span.textContent = 'Reasignar';
		} else {
			parentBtn.classList.add('bg-green-600','hover:bg-green-700');
			parentBtn.removeAttribute('data-prefill-user');
			parentBtn.title = 'Asignar a visionadora';
			if (span) span.textContent = 'Asignar';
		}
	} catch (_) {}
};

// Unified helper to fetch and replace current active tab content via AJAX.
// Centralizes the repeated pattern of: find active tab link -> ajaxSwap -> dispatch event.
window.refreshActiveTab = async function(){
	try {
		const active = document.querySelector('#tabs-nav a.tab-link.bg-gray-100')
			|| document.querySelector('#tabs-nav a[aria-current="page"]')
			|| document.querySelector('#tabs-nav a.tab-link');
		if (active && window.ajaxSwap) {
			await window.ajaxSwap({ url: active.getAttribute('href') + (active.getAttribute('href').includes('?') ? '&' : '?') + 'ajax=1', target: '#tab-content' });
			document.getElementById('tab-content')?.dispatchEvent(new CustomEvent('ajax:swapped', { bubbles: true }));
		}
	} catch (_) {}
};

// Backwards compatibility: legacy code may call ModalObras.refresh
window.ModalObras = window.ModalObras || {};
if (!window.ModalObras.refresh) { window.ModalObras.refresh = window.refreshActiveTab; }

// --- Inicializadores de UI (modales, tabs, disparadores)
function initUIEvents(root = document) {
	// Editar obra por JSON/AJAX (único modal reutilizable)
	root.querySelectorAll('[data-editar-obra]').forEach(btn => {
		if (btn.dataset.boundEditarObra) return;
		btn.dataset.boundEditarObra = '1';
		btn.addEventListener('click', async (e) => {
			e.preventDefault();
			try {
				// Infer NMObra from the closest row or from the attribute
				const row = btn.closest('[data-nmobra]');
				const id = row?.getAttribute('data-nmobra') || (btn.getAttribute('data-editar-obra') || '').replace('modal-edit-obra-','');
				if (!id) return;
				// Open shared create/editar modal shell
				window.ModalManager?.open('modal-create-obra');
				// Fetch obra JSON
				const data = await window.simpleAjax(`/obras/${id}`, { method: 'GET' });
				const obra = data?.data || data;
				if (obra && window.modalObras?.prefillForEdit) {
					window.modalObras.prefillForEdit(obra);
				}
			} catch (err) {
				console.error('Editar obra fetch failed', err);
				window.showToast?.('No se pudo cargar la obra','error');
			}
		});
	});

	// Crear obra directo
	root.querySelectorAll('[data-crear-obra]').forEach(btn => {
		if (btn.dataset.boundCrearObra) return;
		btn.dataset.boundCrearObra = '1';
		btn.addEventListener('click', (e) => {
			e.preventDefault();
			let opts = {};
			try { opts = btn.dataset?.crearObraOpts ? JSON.parse(btn.dataset.crearObraOpts) : {}; } catch(_) {}
			window.openModalObra?.(opts);
		});
	});

	// Identificar obra
	root.querySelectorAll('[data-identificar-obra]').forEach(btn => {
		if (btn.dataset.boundIdentificar) return;
		btn.dataset.boundIdentificar = '1';
		btn.addEventListener('click', async (e) => {
			e.preventDefault();
			if (btn.hasAttribute('data-ajax-swap') && btn.hasAttribute('data-ajax-url')) {
				const url = btn.getAttribute('data-ajax-url');
				try {
					const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
					if (!resp.ok) throw new Error('HTTP ' + resp.status);
					const data = await resp.json();
					window.openIdentificarObraModal?.(data);
				} catch { alert('No se pudo cargar la información de la emisión.'); }
			} else {
				let data = btn.getAttribute('data-emision-info');
				try { data = JSON.parse(data); } catch { data = {}; }
				window.openIdentificarObraModal?.(data);
			}
		});
	});

	// Identificar obra (padre / agrupado)
	root.querySelectorAll('[data-parent-identificar]').forEach(btn => {
		if (btn.dataset.boundParentIdentificar) return;
		btn.dataset.boundParentIdentificar = '1';
		btn.addEventListener('click', (e) => {
			e.preventDefault();
			const idsAttr = btn.getAttribute('data-emision-ids') || '';
			const ids = idsAttr.split(',').map(s => s.trim()).filter(Boolean);
			const title = btn.getAttribute('data-title') || '';
			const serieId = btn.getAttribute('data-serie-id');
			const payload = { group_emision_ids: ids, group_title: title };
			// Sugerencia opcional desde el botón padre
			const sugAttr = btn.getAttribute('data-sugerencia');
			if (sugAttr) {
				try { payload.sugerencia = JSON.parse(sugAttr); } catch { /* noop */ }
			}
			// Opcionalmente, si existe serie preasignada, podría usarse después
			if (serieId) payload.serie_id = serieId;
			window.openIdentificarObraModal?.(payload);
		});
	});

	// Crear Emisión (sin inline JS)
	root.querySelectorAll('[data-crear-emision]').forEach(btn => {
		if (btn.dataset.boundCrearEmision) return;
		btn.dataset.boundCrearEmision = '1';
		btn.addEventListener('click', (e) => {
			e.preventDefault();
			try { window.showEmisionModal?.(); } catch { /* noop */ }
		});
	});

	// Editar Emisión (sin inline JS)
	root.querySelectorAll('[data-editar-emision]').forEach(btn => {
		if (btn.dataset.boundEditarEmision) return;
		btn.dataset.boundEditarEmision = '1';
		btn.addEventListener('click', async (e) => {
			e.preventDefault();
			const ajaxUrl = btn.getAttribute('data-ajax-url');
			if (ajaxUrl) {
				try {
					const res = await fetch(ajaxUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
					if (!res.ok) throw new Error('HTTP ' + res.status);
					const data = await res.json();
					window.showEmisionModal?.(data);
				} catch (err) { console.error(err); window.showToast?.('No se pudo cargar la emisión','error'); }
				return;
			}
			// Fallback: construir payload desde data- attributes
			const payload = {
				id: btn.getAttribute('data-emision-id') || undefined,
				TituloEmision: btn.getAttribute('data-titulo') || '',
				canal: btn.getAttribute('data-canal') || '',
				canal_id: btn.getAttribute('data-canal-id') || undefined,
				fecha_emision: btn.getAttribute('data-fecha') || '',
				hora_inicio: btn.getAttribute('data-hora-inicio') || '',
				hora_fin: btn.getAttribute('data-hora-fin') || '',
				obra: btn.getAttribute('data-obra') || '',
				obra_id: btn.getAttribute('data-obra-id') || undefined
			};
			try { window.showEmisionModal?.(payload); } catch { /* noop */ }
		});
	});

	// Abrir modal Asignar (individual)
	root.querySelectorAll('[data-asignar-emision]')?.forEach(btn => {
		if (btn.dataset.boundAsignar) return; btn.dataset.boundAsignar = '1';
		btn.addEventListener('click', (e) => {
			e.preventDefault();
			const bulkAttr = btn.getAttribute('data-bulk-ids');
			const prefillUser = btn.getAttribute('data-prefill-user');
			if (bulkAttr) {
				const ids = bulkAttr.split(',').map(s => parseInt(s,10)).filter(n => !isNaN(n));
				const items = ids.map(id => {
					const row = document.querySelector(`.emision-row[data-nmemision="${id}"]`);
					const titulo = row?.getAttribute('data-titulo') || row?.querySelector('[data-emision-titulo-display]')?.textContent?.trim() || '';
					return { id, titulo };
				});
				window.openAsignarEmisionModal?.({ emisionIds: ids, items, preselectedUserId: prefillUser ? parseInt(prefillUser,10) : undefined });
				return;
			}
			const id = btn.getAttribute('data-emision-id');
			const titulo = btn.getAttribute('data-emision-titulo') || btn.getAttribute('data-titulo') || '';
			window.openAsignarEmisionModal?.({ emisionId: id ? parseInt(id,10) : null, items: id ? [{ id: id, titulo }] : [] });
		});
	});

	// Abrir modal Asignar (bulk toolbar)
	const bulkActions = root.querySelector('[data-bulk-actions="emisiones"]');
	if (bulkActions && !bulkActions.dataset.boundAsignarBulk) {
		bulkActions.dataset.boundAsignarBulk = '1';
		const btn = bulkActions.querySelector('[data-bulk-action="open-modal"]');
		if (btn) {
			btn.addEventListener('click', (e) => {
				e.preventDefault();
					// Abriremos el modal para asignar; tras confirmar, no queremos preservar selección en el swap
					try { window.requestBulkResetOnNextSwap?.(); } catch {}
				const ids = Array.from(window.BulkSelection?.selectedItems || []);
				const items = [];
				ids.forEach(id => {
					const row = document.querySelector(`.emision-row[data-nmemision="${id}"]`);
					const titulo = row?.getAttribute('data-titulo') || row?.querySelector('[data-emision-titulo-display]')?.textContent?.trim() || '';
					items.push({ id, titulo });
				});
				window.openAsignarEmisionModal?.({ emisionIds: ids, items });
			});
		}
	}

	// Toggle simple por selector/ID (permite múltiples filas/targets)
	// Usado para expandir/collapse en tablas agrupadas (emisiones, obras, visionados usuario)
	root.querySelectorAll('[data-toggle-target]').forEach(btn => {
		if (btn.dataset.boundToggleTarget) return;
		btn.dataset.boundToggleTarget = '1';
		btn.addEventListener('click', (e) => {
			e.preventDefault();
			const sel = btn.getAttribute('data-toggle-target');
			if (!sel) return;
			const targets = Array.from(document.querySelectorAll(sel));
			if (!targets.length) return;
			const allHidden = targets.every(t => t.classList.contains('hidden'));
			targets.forEach(t => t.classList.toggle('hidden', !allHidden));
			// Toggle expand/collapse icons
			const expanded = btn.getAttribute('aria-expanded') === 'true';
			btn.setAttribute('aria-expanded', allHidden); // now expanded if we just showed them
			const iconExpand = btn.querySelector('[data-icon-expand]');
			const iconCollapse = btn.querySelector('[data-icon-collapse]');
			if (iconExpand && iconCollapse) {
				iconExpand.classList.toggle('hidden', allHidden);
				iconCollapse.classList.toggle('hidden', !allHidden);
			}
		});
	});

	// Eliminar obra (reemplazo de inline onclick en modal-obras)
	root.querySelectorAll('[data-obra-delete]').forEach(btn => {
		if (btn.dataset.boundObraDelete) return;
		btn.dataset.boundObraDelete = '1';
		btn.addEventListener('click', async (e) => {
			e.preventDefault();
			const id = btn.getAttribute('data-obra-id');
			const titulo = btn.getAttribute('data-obra-title') || '';
			const count = parseInt(btn.getAttribute('data-obra-related-count') || '0', 10);
			if (!id) return;
			const msg = [
				`Vas a borrar "${titulo}" (ID ${id}).`,
				'Se realizará:',
				'- Las emisiones asociadas NO se eliminarán; se desasignarán de esta obra.',
				'- Los títulos de esas emisiones se restaurarán a su estado original cuando corresponda.',
				'- Se eliminarán los metadatos propios de esta obra (fichas, elenco, etc.).',
				'Esta acción no se puede deshacer. ¿Continuar?'
			].join('\n');
			if (!confirm(msg)) return;
			try {
				const token = document.querySelector('meta[name="csrf-token"]').content;
				const res = await fetch(`/obras/${id}`, { method: 'DELETE', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token } });
				if (!res.ok) throw new Error('HTTP ' + res.status);
				window.showToast?.('Obra eliminada','success');
				// Refrescar pestaña de obras si está presente
				try {
					await (window.refreshActiveTab?.() || Promise.resolve());
				} catch(err){ console.error(err); }
			} catch (err) {
				console.error(err);
				window.showToast?.('Error eliminando obra','error');
			}
		});
	});

	// Navegación por botones con data-nav (reemplaza onclick=navigate)
	root.querySelectorAll('button[data-nav]').forEach(btn => {
		if (btn.dataset.boundNav) return;
		btn.dataset.boundNav = '1';
		btn.addEventListener('click', (e) => {
			e.preventDefault();
			const href = btn.getAttribute('href');
			if (href) window.location.href = href;
		});
	});

	// Logout delegado (reemplaza onclick submit del form)
	root.querySelectorAll('a[data-logout]').forEach(a => {
		if (a.dataset.boundLogout) return;
		a.dataset.boundLogout = '1';
		a.addEventListener('click', (e) => {
			e.preventDefault();
			const form = document.getElementById('logout-form');
			if (form) form.submit();
		});
	});

	// Ocultar por selector (reemplaza onclick document.getElementById(...).classList.add('hidden'))
	root.querySelectorAll('[data-hide]').forEach(el => {
		if (el.dataset.boundHide) return;
		el.dataset.boundHide = '1';
		el.addEventListener('click', (e) => {
			e.preventDefault();
			const sel = el.getAttribute('data-hide');
			if (!sel) return;
			const target = document.querySelector(sel);
			if (target) target.classList.add('hidden');
		});
	});

	// Ver emisiones (mostrar modal estático)
	root.querySelectorAll('[data-ver-emisiones]').forEach(btn => {
		if (btn.dataset.boundVerEmisiones) return;
		btn.dataset.boundVerEmisiones = '1';
		btn.addEventListener('click', (e) => {
			e.preventDefault();
			// Si también tiene data-ajax-swap, dejamos que el manejador de AJAX
			// haga el swap y luego abra el modal para evitar que se cierre al reemplazar el DOM
			if (btn.hasAttribute('data-ajax-swap')) return;
			const modalId = btn.getAttribute('data-ver-emisiones');
			if (window.ModalManager) {
				window.ModalManager.open(modalId);
			}
		});
	});

	// Tabs con AJAX
	// Helper (idempotent definition) for setting active tab styling + aria
	if (!window.setActiveTab) {
		window.setActiveTab = function(activeLink){
			const links = document.querySelectorAll('#tabs-nav .tab-link');
			links.forEach(l => {
				l.classList.remove('bg-gray-100','border-b-2','border-blue-500');
				l.setAttribute('aria-selected','false');
				l.tabIndex = -1;
			});
			if (activeLink) {
				activeLink.classList.add('bg-gray-100','border-b-2','border-blue-500');
				activeLink.setAttribute('aria-selected','true');
				activeLink.tabIndex = 0;
			}
		};
	}

	root.querySelectorAll('.tab-link').forEach(link => {
		if (link.dataset.boundTab) return;
		link.dataset.boundTab = '1';
		// Ensure base ARIA attrs
		if (!link.hasAttribute('role')) link.setAttribute('role','tab');
		if (!link.hasAttribute('aria-selected')) link.setAttribute('aria-selected', link.classList.contains('bg-gray-100') ? 'true':'false');
		if (!link.id) link.id = 'tab-' + (link.getAttribute('data-tab') || Math.random().toString(36).slice(2));
		link.addEventListener('click', async (e) => {
			e.preventDefault();
			const rawUrl = link.getAttribute('href');
			if (!rawUrl) return;
			const hasQuery = rawUrl.includes('?');
			const ajaxUrl = rawUrl.includes('ajax=1') ? rawUrl : rawUrl + (hasQuery ? '&' : '?') + 'ajax=1';
			const tabContent = document.getElementById('tab-content');
			if (tabContent && window.ajaxSwap) {
				window.setActiveTab(link);
				try {
					await window.ajaxSwap({ url: ajaxUrl, target: '#tab-content', showLoader: true });
					try {
						const dataTab = link.getAttribute('data-tab');
						const cleanUrl = rawUrl.replace(/([?&])ajax=1(&|$)/,'$1').replace(/[?&]$/,'');
						history.pushState({ tab: dataTab }, '', cleanUrl);
					} catch {}
				} catch (err) {
					// Fallback if swap failed
					window.location.href = rawUrl;
				}
			} else {
				window.location.href = rawUrl;
			}
		});
	});

	// Popstate: reload tab content without adding new history entry
	if (!window._tabsPopstateBound) {
		window._tabsPopstateBound = true;
		window.addEventListener('popstate', async (ev) => {
			try {
				const stateTab = ev.state?.tab;
				// Derive tab from location if state missing
				const urlParams = new URLSearchParams(window.location.search);
				const paramTab = urlParams.get('tab');
				const targetTab = stateTab || paramTab;
				if (!targetTab) return;
				const link = document.querySelector(`#tabs-nav .tab-link[data-tab="${targetTab}"]`);
				if (!link) return;
				const rawUrl = link.getAttribute('href');
				const ajaxUrl = rawUrl + (rawUrl.includes('?') ? '&' : '?') + 'ajax=1';
				window.setActiveTab(link);
				if (window.ajaxSwap) {
					await window.ajaxSwap({ url: ajaxUrl, target: '#tab-content' });
				}
			} catch (err) { console.error('popstate tab load failed', err); }
		});
	}

	// Botones con data-ajax-swap / data-modal-target
	root.querySelectorAll('[data-modal-target], [data-modal], [data-ajax-swap]').forEach(btn => {
		if (btn.dataset.boundUiEvents) return;
		btn.dataset.boundUiEvents = '1';
		if (btn.hasAttribute('data-modal-target') && !btn.hasAttribute('data-ajax-swap')) {
			btn.addEventListener('click', (e) => {
				e.preventDefault(); e.stopPropagation();
				const modalId = btn.getAttribute('data-modal-target');
				if (window.ModalManager) {
					window.ModalManager.open(modalId);
				}
			});
		}
		if (btn.hasAttribute('data-ajax-swap')) {
			btn.addEventListener('click', async (e) => {
				e.preventDefault();
				const url = btn.getAttribute('data-ajax-url') || btn.getAttribute('href');
					// Permitir también data-ver-emisiones como identificador de modal a abrir tras el swap
					const modalId = btn.getAttribute('data-modal')
						|| btn.getAttribute('data-modal-target')
						|| btn.getAttribute('data-ver-emisiones');
				const targetSel = btn.getAttribute('data-ajax-target');
				if (targetSel && window.ajaxSwap) {
					await window.ajaxSwap({ url, target: targetSel });
					document.querySelector(targetSel)?.dispatchEvent(new CustomEvent('ajax:swapped', { bubbles: true }));
				}
				setTimeout(() => {
					if (modalId && window.ModalManager) {
						window.ModalManager.open(modalId);
					}
				}, 50);
			});
		}
	});

	// Sin inline: actualizar hidden Genero al cambiar CodGenero
	root.querySelectorAll('select[name="CodGenero"]').forEach(sel => {
		if (sel.dataset.boundGeneroSync) return;
		sel.dataset.boundGeneroSync = '1';
		sel.addEventListener('change', () => {
			const form = sel.form;
			if (!form) return;
			const hidden = form.querySelector('input[name="Genero"]');
			if (!hidden) return;
			const text = sel.options[sel.selectedIndex]?.text || '';
			hidden.value = text;
		});
	});

	// Bind genérico para formularios de edición de obra dentro de modales (sin inline)
	root.querySelectorAll('form[data-obra-edit-form]').forEach(form => {
		if (form.dataset.boundObraEdit) return;
		form.dataset.boundObraEdit = '1';
		form.addEventListener('submit', async (e) => {
			e.preventDefault();
			// Delegar al módulo modalObras si corresponde
			try { await (window.modalObras?.handleFormSubmit ? window.modalObras.handleFormSubmit(form) : (async()=>{ /* no-op */ })()); } catch {}
		});
	});
}

// Inicializar eventos de UI al cargar y después de swaps AJAX
document.addEventListener('DOMContentLoaded', () => initUIEvents(document));
document.addEventListener('ajax:swapped', () => initUIEvents(document));

// Interceptar paginación dentro de #tab-content para mantener AJAX en tablas user visionados
function initAjaxPagination(root = document) {
	const container = root.getElementById ? root.getElementById('tab-content') : document.getElementById('tab-content');
	const scope = container || root;
	scope.querySelectorAll('.pagination a, nav[role="navigation"] a').forEach(a => {
		if (a.dataset.boundAjaxPage) return;
		const href = a.getAttribute('href');
		if (!href) return;
		// Sólo interceptamos si parece ser parte de dashboard user y ya estamos usando ajax
		if (!href.includes('/dashboard/user')) return;
		a.dataset.boundAjaxPage = '1';
		a.addEventListener('click', async (e) => {
			e.preventDefault();
			try {
				const url = href.includes('ajax=1') ? href : (href + (href.includes('?') ? '&' : '?') + 'ajax=1');
				await window.ajaxSwap?.({ url, target: '#tab-content', showLoader: true });
			} catch(err) { window.location.href = href; }
		});
	});
}

document.addEventListener('DOMContentLoaded', () => initAjaxPagination(document));
document.addEventListener('ajax:swapped', (e) => initAjaxPagination(e.target || document));

// Importar emisiones (centralizado)
function bindImportEmisiones(root = document) {
	root.querySelectorAll('[data-import-emisiones]').forEach(wrapper => {
		if (wrapper.dataset.boundImport) return;
		wrapper.dataset.boundImport = '1';
		const trigger = wrapper.querySelector('[data-import-trigger]');
		const input = wrapper.querySelector('[data-import-file]');
		const msg = wrapper.querySelector('[data-import-msg]');
		if (trigger && input) {
			trigger.addEventListener('click', () => {
				if (msg) { msg.textContent = ''; msg.className = 'text-sm'; }
				input.click();
			});
			input.addEventListener('change', async () => {
				if (!input.files || !input.files[0]) return;
				const fd = new FormData();
				fd.append('file', input.files[0]);
				try {
					if (msg) { msg.textContent = 'Importando emisiones...'; msg.className = 'text-sm text-blue-600'; }
					const data = await window.simpleAjax?.('/emisiones/import', { method: 'POST', body: fd });
					if (!data || data.ok !== true) throw new Error((data && (data.error || data.message)) || 'Error en la importación');
					if (msg) { msg.textContent = data.message || 'Emisiones importadas correctamente'; msg.className = 'text-sm text-green-600'; }
					window.showToast?.(data.message || 'Importación completada', 'success');
					// Refrescar pestaña material si existe
					try { await (window.refreshActiveTab?.() || Promise.resolve()); } catch {}
					document.dispatchEvent(new CustomEvent('emisiones:imported', { detail: { message: data.message, count: data.count || 0 } }));
				} catch (err) {
					console.error('Import error:', err);
					const text = err?.message || 'Error al importar el archivo';
					if (msg) { msg.textContent = text; msg.className = 'text-sm text-red-600'; }
					window.showToast?.(text, 'error');
				} finally {
					input.value = '';
				}
			});
		}
	});
}
document.addEventListener('DOMContentLoaded', () => bindImportEmisiones(document));
document.addEventListener('ajax:swapped', (e) => bindImportEmisiones(e.target || document));

// Recompute all group buttons on load and after swaps to reflect current nested assignments
function recomputeAllGroupButtons() {
	try {
		const btns = document.querySelectorAll('[data-asignar-emision][data-group-key]');
		btns.forEach(btn => {
			const key = btn.getAttribute('data-group-key');
			if (key) window.recomputeGroupAssignButton?.(key);
		});
	} catch (_) {}
}

document.addEventListener('DOMContentLoaded', recomputeAllGroupButtons);
document.addEventListener('ajax:swapped', recomputeAllGroupButtons);

// Generic autoload for containers with data-autoload-url
function initAutoloadContainers(root = document) {
	root.querySelectorAll('[data-autoload-url]').forEach(async (el) => {
		if (el.dataset.autoloaded === '1') return;
		el.dataset.autoloaded = '1';
		const url = el.getAttribute('data-autoload-url');
		if (url && window.ajaxSwap) {
			try { await window.ajaxSwap({ url, target: '#' + el.id }); } catch (_) {}
		}
	});
}
document.addEventListener('DOMContentLoaded', () => initAutoloadContainers(document));
document.addEventListener('ajax:swapped', (e) => initAutoloadContainers(e.target || document));

// Edición inline de título de emisión (delegado)
if (!document.body.dataset.boundInlineEmissionEdit) {
	document.body.dataset.boundInlineEmissionEdit = '1';
	document.addEventListener('click', async (e) => {
		const editBtn = e.target.closest('[data-edit-emision-title]');
		if (editBtn) {
			const id = editBtn.getAttribute('data-emision-id');
			if (!id) return;
			const display = document.querySelector(`[data-emision-titulo-display][data-emision-id="${id}"]`);
			const editWrap = document.querySelector(`[data-emision-title-edit="${id}"]`);
			if (display && editWrap) { display.classList.add('hidden'); editWrap.classList.remove('hidden'); const input = editWrap.querySelector(`[data-emision-title-input="${id}"]`); input?.focus(); input?.select?.(); }
			return;
		}
		const saveBtn = e.target.closest('[data-emision-title-save]');
		if (saveBtn) {
			const id = saveBtn.getAttribute('data-emision-title-save');
			const input = document.querySelector(`[data-emision-title-input="${id}"]`);
			const val = input?.value?.trim();
			if (!val) return;
			try {
				const headers = { 'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest' };
				const token = document.querySelector('meta[name="csrf-token"]')?.content; if (token) headers['X-CSRF-TOKEN'] = token;
				const res = await fetch('/emisiones/renombrar', { method:'POST', headers, body: JSON.stringify({ emision_id: id, titulo: val }) });
				if (!res.ok) throw new Error('HTTP '+res.status);
				const display = document.querySelector(`[data-emision-titulo-display][data-emision-id="${id}"]`);
				if (display) display.textContent = val;
				window.showToast?.('Título actualizado','success');
					try { await (window.refreshActiveTab?.() || Promise.resolve()); } catch {}
			} catch { window.showToast?.('Error','error'); }
			finally {
				const editWrap = document.querySelector(`[data-emision-title-edit="${id}"]`);
				const display = document.querySelector(`[data-emision-titulo-display][data-emision-id="${id}"]`);
				if (editWrap && display) { editWrap.classList.add('hidden'); display.classList.remove('hidden'); }
			}
			return;
		}
		const cancelBtn = e.target.closest('[data-emision-title-cancel]');
		if (cancelBtn) {
			const id = cancelBtn.getAttribute('data-emision-title-cancel');
			const editWrap = document.querySelector(`[data-emision-title-edit="${id}"]`);
			const display = document.querySelector(`[data-emision-titulo-display][data-emision-id="${id}"]`);
			if (editWrap && display) { editWrap.classList.add('hidden'); display.classList.remove('hidden'); }
			return;
		}
	});
}

// Nota: la gestión de cierre de modales está delegada a ModalManager (evita duplicidad y estados inconsistentes)

// Formularios rápidos dentro de modales de obra (fallback genérico)
function initModalObrasGlobal(){
	document.querySelectorAll('.modal-component form[data-obra-quick]').forEach(form => {
		if (form.dataset.boundQuickGlobal) return;
		form.dataset.boundQuickGlobal = '1';
		form.addEventListener('submit', async (e) => {
			e.preventDefault(); e.stopPropagation();
			if (form.dataset.submitting === '1') return;
			if (form.checkValidity && !form.checkValidity()) { form.reportValidity?.(); return; }
			form.dataset.submitting = '1';
			const fd = new FormData(form);
			const headers = { 'X-Requested-With': 'XMLHttpRequest' };
			const token = document.querySelector('meta[name="csrf-token"]')?.content; if (token) headers['X-CSRF-TOKEN'] = token;
			const submitBtn = form.querySelector('button[type=submit]');
			if (submitBtn) submitBtn.disabled = true;
			try {
				const res = await fetch(form.action, { method: 'POST', headers, body: fd });
				let data; try { data = await res.clone().json(); } catch { data = null; }
				if (!res.ok) { window.showToast?.('Error al guardar obra','error'); return; }
				window.showToast?.('Obra guardada','success');
					// Cerrar el modal de creación/edición al éxito (fallback)
					try {
						const modalEl = form.closest('.modal-component');
						if (modalEl?.id && window.ModalManager) {
							window.ModalManager.close(modalEl.id);
						} else if (window.ModalManager) {
							window.ModalManager.close('modal-create-obra');
						}
					} catch {}

					// Refrescar la pestaña activa (respetando si el wizard está abierto)
					try {
						const wizard = document.getElementById('modal-series-wizard');
						const wizardOpen = !!(wizard && !wizard.classList.contains('hidden'));
						if (wizardOpen) {
							const onWizardClosed = (ev) => {
								if (ev.detail?.modalId === 'modal-series-wizard') {
									document.removeEventListener('modal:closed', onWizardClosed);
									setTimeout(() => { try { window.ModalObras?.refresh?.(); } catch {} }, 50);
								}
							};
							document.addEventListener('modal:closed', onWizardClosed);
						} else {
							await (window.ModalObras?.refresh?.() || Promise.resolve());
						}
					} catch {}
				if (data && data.NMObra) { document.dispatchEvent(new CustomEvent('obra:creada', { detail: data })); }
			} catch (err) { console.error(err); window.showToast?.('Error al guardar obra','error'); }
			finally { if (submitBtn) submitBtn.disabled = false; delete form.dataset.submitting; }
		});
	});
}
document.addEventListener('DOMContentLoaded', () => { initModalObrasGlobal(); });
document.addEventListener('ajax:swapped', () => { initModalObrasGlobal(); });

// Inicializar selección masiva según contexto (usa el módulo bulkSelection)
function detectBulkContext() {
	if (document.querySelector('[data-emisiones-context], #emisiones-table')) return 'emisiones';
	if (document.querySelector('[data-obras-context], #obras-table')) return 'obras';
	if (document.querySelector('#visionados-table')) return 'visionados';
	return null;
}

function reinitBulkSelection(preserveSelection = true) {
	const context = detectBulkContext();
	if (!context) return;

	let saved = null;
	// Solo preservar selección si es el mismo contexto que antes
	if (preserveSelection && bulkSelection.selectedItems && bulkSelection.currentContext === context) {
		saved = new Set(bulkSelection.selectedItems);
	}

	// Reiniciar para re-vincular listeners a nuevo DOM
	try { bulkSelection.cleanup(); } catch {}
	bulkSelection.initialize(context);

	// Restaurar selección si corresponde; si no se preserva, limpiar explícitamente
	if (preserveSelection && saved && saved.size) {
		bulkSelection.selectedItems = saved;
		try { bulkSelection.refreshCheckboxStates(); } catch {}
	} else {
		try { bulkSelection.deselectAll(); } catch {}
	}
}

document.addEventListener('DOMContentLoaded', () => {
	reinitBulkSelection(false);
});

document.addEventListener('ajax:swapped', () => {
	reinitBulkSelection(preserveBulkOnNextSwap);
	// Consumir el flag: volver a preservar para futuros swaps salvo que se indique lo contrario
	preserveBulkOnNextSwap = true;
});

// Refrescar pestaña activa después de aplicar el asistente de series
document.addEventListener('series:applied', async () => {
	try {
		// Tras aplicar el asistente, NO preservar la selección anterior
		window.requestBulkResetOnNextSwap?.();
		const active = document.querySelector('#tabs-nav a.tab-link.bg-gray-100')
			|| document.querySelector('#tabs-nav a[aria-current="page"]')
			|| document.querySelector('#tabs-nav a.tab-link');
		if (active && window.ajaxSwap) {
			await window.ajaxSwap({ url: active.getAttribute('href') + '&ajax=1', target: '#tab-content' });
		}
	} catch (_) {}
});
