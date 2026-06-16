/**
 * gastos.js — Lógica del módulo de Gastos.
 * Requiere shared.js cargado antes (formatCOP, escHtml, fmtDate, showToast).
 * Requiere que window.APP_URL y window.VISTA estén definidos en la vista.
 */

const API_G = window.APP_URL + '/api/gastos.php';
const VISTA = window.VISTA || '';

let periodos   = [];
let categorias = [];
let gastosData = [];
let editId     = null;
let deleteId   = null;

// ── Inicializar ──────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', async () => {
  await Promise.all([cargarPeriodos(), cargarCategorias()]);
  await cargarGastos();
  bindEvents();
});

// ── Datos base ───────────────────────────────────────────────────

async function cargarPeriodos() {
  const r = await fetch(API_G + '?action=periodos');
  const j = await r.json();
  periodos = j.data || [];

  const opts = periodos.map(p =>
    `<option value="${p.id}" ${p.activo ? 'selected' : ''}>${p.label}</option>`
  ).join('');

  document.getElementById('filPeriodo').innerHTML = opts;
  document.getElementById('fPeriodo').innerHTML   = opts;
}

async function cargarCategorias() {
  const r = await fetch(API_G + '?action=categorias');
  const j = await r.json();
  categorias = j.data || [];

  const opts = categorias.map(c =>
    `<option value="${c.id}">${c.icono} ${escHtml(c.nombre)}</option>`
  ).join('');

  document.getElementById('filCategoria').innerHTML = `<option value="">Todas</option>` + opts;
  document.getElementById('fCategoria').innerHTML   = `<option value="">Selecciona...</option>` + opts;
}

// ── Query params de filtros ───────────────────────────────────────

function buildParams(extra = {}) {
  const p = new URLSearchParams();
  const pid = document.getElementById('filPeriodo').value;
  if (pid)   p.set('periodo_id', pid);
  if (VISTA) p.set('responsable', VISTA);

  const cat  = document.getElementById('filCategoria').value;
  const tipo = document.getElementById('filTipo').value;
  const est  = document.getElementById('filEstado').value;
  const quin = document.getElementById('filQuincena').value;
  if (cat)  p.set('categoria_id', cat);
  if (tipo) p.set('tipo', tipo);
  if (est)  p.set('estado', est);
  if (quin) p.set('quincena', quin);

  Object.entries(extra).forEach(([k, v]) => p.set(k, v));
  return p.toString();
}

// ── Cargar tabla + resumen ─────────────────────────────────────────

async function cargarGastos() {
  const q = buildParams();
  const [rG, rR] = await Promise.all([
    fetch(API_G + '?action=listar&' + q),
    fetch(API_G + '?action=resumen&' + q),
  ]);
  const [jG, jR] = await Promise.all([rG.json(), rR.json()]);

  gastosData = jG.data || [];
  renderTabla(gastosData);

  if (jR.success) {
    const d = jR.data;
    setText('rTotal', formatCOP(d.total));
    setText('rCantidad', d.cantidad + ' registros');
    setText('rPendientes', formatCOP(d.pendientes));
    setText('rCntPendientes', d.cnt_pendientes + ' por pagar');
    setText('rPagados', formatCOP(d.pagados));
    setText('rCntPagados', d.cnt_pagados + ' liquidados');
  }
}

function setText(id, text) {
  const el = document.getElementById(id);
  if (el) el.textContent = text;
}

// ── Render tabla ─────────────────────────────────────────────────

function renderTabla(rows) {
  const tbody = document.getElementById('bodyGastos');
  setText('badgeCount', rows.length);

  if (!rows.length) {
    tbody.innerHTML = `<tr><td colspan="9"><div class="empty-state">
      <div class="empty-icon">📭</div>
      <p>Sin gastos con los filtros seleccionados</p>
    </div></td></tr>`;
    setText('tablaInfo', 'Sin resultados');
    setText('tablaTotales', '');
    return;
  }

  let totalPend = 0, totalPag = 0;
  tbody.innerHTML = rows.map(g => {
    if (g.estado === 'pendiente') totalPend += parseFloat(g.valor);
    else totalPag += parseFloat(g.valor);

    const dias = parseInt(g.dias_venc, 10);
    const cuotaLabel = g.cuota_numero
      ? `<div class="quin-chip">${g.cuota_numero}/${g.total_cuotas}</div>` : '';

    return `<tr data-id="${g.id}">
      <td>
        <div style="font-weight:600;font-size:.875rem">${escHtml(g.concepto)}</div>
        ${cuotaLabel}
        ${g.notas ? `<div class="quin-chip" title="${escHtml(g.notas)}">📝</div>` : ''}
      </td>
      <td>
        <span style="display:inline-flex;align-items:center;gap:.35rem">
          <span style="width:8px;height:8px;border-radius:50%;background:${g.cat_color};display:inline-block"></span>
          ${escHtml(g.cat_icono)} ${escHtml(g.categoria)}
        </span>
      </td>
      <td>${responsableChip(g.responsable)}</td>
      <td><span class="tipo-chip tipo-${g.tipo}">${g.tipo}</span></td>
      <td><span class="quin-chip">${g.quincena === 'primera' ? '1ª quincena' : '2ª quincena'}</span></td>
      <td style="white-space:nowrap;font-size:.8125rem">${fmtDate(g.fecha_pago)}</td>
      <td>${renderSwitch(g.id, g.estado, dias)}</td>
      <td class="text-right amount ${g.estado === 'pagado' ? '' : 'negative'}">
        ${formatCOP(g.valor)}
      </td>
      <td>
        <div class="acciones">
          <button class="btn btn-icon" data-action="editar" data-id="${g.id}" title="Editar">✏️</button>
          <button class="btn btn-icon btn-danger-outline" data-action="eliminar" data-id="${g.id}" title="Eliminar">🗑</button>
        </div>
      </td>
    </tr>`;
  }).join('');

  const total = totalPend + totalPag;
  setText('tablaInfo', `${rows.length} registro${rows.length !== 1 ? 's' : ''}`);
  document.getElementById('tablaTotales').innerHTML =
    `<span class="text-danger">${formatCOP(totalPend)} pendiente</span>
     &nbsp;·&nbsp;
     <span class="text-success">${formatCOP(totalPag)} pagado</span>
     &nbsp;·&nbsp;
     <strong>Total: ${formatCOP(total)}</strong>`;
}

// ── Chips visuales ───────────────────────────────────────────────

function renderSwitch(id, estado, dias) {
  const isPaid = estado === 'pagado';
  const label  = isPaid ? 'Pagado' : (dias < 0 ? 'Vencido' : (dias <= 5 ? 'Próximo' : 'Pendiente'));
  return `<label class="status-switch ${isPaid ? 'is-paid' : ''}" data-action="toggle-estado" data-id="${id}" data-estado="${estado}">
    <span class="switch-track"><span class="switch-thumb"></span></span>
    <span class="switch-label">${label}</span>
  </label>`;
}

function responsableChip(r) {
  const map = {
    carolina:   `<span style="color:#ff6b9d;font-size:.8125rem">👩 Carolina</span>`,
    javier:     `<span style="color:#6c63ff;font-size:.8125rem">👨 Javier</span>`,
    compartido: `<span style="color:#10b981;font-size:.8125rem">👫 Compartido</span>`,
  };
  return map[r] ?? r;
}

// ── Toggle estado (switch) ────────────────────────────────────────

async function toggleEstado(switchEl) {
  const id   = switchEl.dataset.id;
  const actual = switchEl.dataset.estado;
  const nuevo  = actual === 'pagado' ? 'pendiente' : 'pagado';

  switchEl.classList.add('is-loading');
  const r = await fetch(API_G + '?action=estado', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id, estado: nuevo }),
  });
  const j = await r.json();
  switchEl.classList.remove('is-loading');

  if (j.success) {
    showToast('Estado actualizado', 'success');
    await cargarGastos();
  } else {
    showToast(j.message || 'No se pudo actualizar el estado.', 'error');
  }
}

// ── Modal crear ──────────────────────────────────────────────────

function abrirNuevo() {
  editId = null;
  document.getElementById('modalTitle').textContent = 'Nuevo gasto';
  document.getElementById('formGasto').reset();
  document.getElementById('fId').value = '';
  document.getElementById('fFecha').value = new Date().toISOString().split('T')[0];

  const activo = periodos.find(p => p.activo);
  if (activo) document.getElementById('fPeriodo').value = activo.id;

  actualizarCamposCuotas();
  abrirModal('modalGasto');
}

// ── Modal editar ─────────────────────────────────────────────────

function abrirEditar(id) {
  const g = gastosData.find(x => String(x.id) === String(id));
  if (!g) return;
  editId = id;

  document.getElementById('modalTitle').textContent = 'Editar gasto';
  document.getElementById('fId').value          = g.id;
  document.getElementById('fConcepto').value     = g.concepto;
  document.getElementById('fCategoria').value    = categorias.find(c => c.nombre === g.categoria)?.id ?? '';
  document.getElementById('fValor').value        = formatNumberCOP(g.valor);
  document.getElementById('fTipo').value         = g.tipo;
  document.getElementById('fResponsable').value  = g.responsable;
  document.getElementById('fFecha').value        = g.fecha_pago;
  document.getElementById('fEstado').value       = g.estado;
  document.getElementById('fPeriodo').value      = g.periodo_id;
  document.getElementById('fNotas').value        = g.notas ?? '';
  if (g.total_cuotas) document.getElementById('fTotalCuotas').value = g.total_cuotas;

  actualizarCamposCuotas();
  abrirModal('modalGasto');
}

// ── Guardar (crear o editar) ───────────────────────────────────────

async function guardarGasto() {
  const payload = {
    concepto:     document.getElementById('fConcepto').value.trim(),
    categoria_id: document.getElementById('fCategoria').value,
    valor:        parseNumberCOP(document.getElementById('fValor').value),
    tipo:         document.getElementById('fTipo').value,
    responsable:  document.getElementById('fResponsable').value,
    fecha_pago:   document.getElementById('fFecha').value,
    estado:       document.getElementById('fEstado').value,
    periodo_id:   document.getElementById('fPeriodo').value,
    notas:        document.getElementById('fNotas').value.trim(),
    total_cuotas: parseInt(document.getElementById('fTotalCuotas').value, 10) || 0,
  };
  if (editId) payload.id = editId;

  setGuardando(true);
  const action = editId ? 'editar' : 'crear';
  const r = await fetch(API_G + '?action=' + action, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
  const j = await r.json();
  setGuardando(false);

  if (j.success) {
    cerrarModal('modalGasto');
    showToast(j.message, 'success');
    await cargarGastos();
  } else {
    const msg = Array.isArray(j.errors) ? j.errors.join('<br>') : j.message;
    showToast(msg, 'error');
  }
}

// ── Eliminar ─────────────────────────────────────────────────────

function abrirEliminar(id, concepto) {
  deleteId = id;
  document.getElementById('elimConcepto').textContent = concepto;
  abrirModal('modalEliminar');
}

async function confirmarEliminar() {
  if (!deleteId) return;

  const btn = document.getElementById('btnConfirmarEliminar');
  btn.disabled = true;

  try {
    const r = await fetch(API_G + '?action=eliminar&id=' + encodeURIComponent(deleteId), {
      method: 'DELETE',
    });
    const j = await r.json();

    if (j.success) {
      cerrarModal('modalEliminar');
      showToast('Gasto eliminado correctamente.', 'success');
      deleteId = null;
      await cargarGastos();
    } else {
      showToast(j.message || 'No se pudo eliminar el gasto.', 'error');
    }
  } catch (err) {
    showToast('Error de conexión al eliminar el gasto.', 'error');
  } finally {
    btn.disabled = false;
  }
}

// ── Campo cuotas dinámico ──────────────────────────────────────────

function actualizarCamposCuotas() {
  const tipo  = document.getElementById('fTipo').value;
  const grupo = document.getElementById('grupoCuotas');
  const resum = document.getElementById('resumenCuota');

  if (tipo === 'cuotas') {
    grupo.style.display = 'block';
    const valor  = parseNumberCOP(document.getElementById('fValor').value) || 0;
    const cuotas = parseInt(document.getElementById('fTotalCuotas').value, 10) || 0;
    if (valor > 0 && cuotas >= 2) {
      resum.style.display = 'block';
      resum.innerHTML = `💡 <strong>${cuotas} cuotas</strong> de <strong>${formatCOP(valor)}</strong> = <strong>${formatCOP(valor * cuotas)}</strong> total`;
    } else {
      resum.style.display = 'none';
    }
  } else {
    grupo.style.display = 'none';
    resum.style.display = 'none';
  }
}

// ── Exportar CSV ───────────────────────────────────────────────────

function exportarCSV() {
  if (!gastosData.length) return showToast('Sin datos para exportar', 'warning');

  const headers = ['Concepto', 'Categoría', 'Responsable', 'Tipo', 'Fecha Pago', 'Quincena', 'Estado', 'Valor', 'Cuota'];
  const rows = gastosData.map(g => [
    g.concepto, g.categoria, g.responsable, g.tipo,
    g.fecha_pago, g.quincena, g.estado, g.valor,
    g.cuota_numero ? `${g.cuota_numero}/${g.total_cuotas}` : '',
  ]);

  const csv = [headers, ...rows]
    .map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(','))
    .join('\n');

  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = `gastos_${new Date().toISOString().slice(0, 7)}.csv`;
  a.click();
  URL.revokeObjectURL(a.href);
}

// ── Utilidades modal ────────────────────────────────────────────────

function abrirModal(id)  { document.getElementById(id).classList.add('open'); }
function cerrarModal(id) { document.getElementById(id).classList.remove('open'); }

function setGuardando(v) {
  document.getElementById('btnGuardar').disabled = v;
  document.getElementById('spinnerGuardar').style.display = v ? 'block' : 'none';
  document.getElementById('btnGuardarText').textContent = v ? 'Guardando...' : 'Guardar';
}

// ── Bind de eventos (delegación) ─────────────────────────────────────

function bindEvents() {
  ['filPeriodo', 'filCategoria', 'filTipo', 'filEstado', 'filQuincena'].forEach(id => {
    document.getElementById(id).addEventListener('change', cargarGastos);
  });

  document.getElementById('btnLimpiar').addEventListener('click', () => {
    document.getElementById('filCategoria').value = '';
    document.getElementById('filTipo').value      = '';
    document.getElementById('filEstado').value    = '';
    document.getElementById('filQuincena').value  = '';
    const activo = periodos.find(p => p.activo);
    if (activo) document.getElementById('filPeriodo').value = activo.id;
    cargarGastos();
  });

  document.getElementById('btnNuevo').addEventListener('click', abrirNuevo);
  document.getElementById('modalClose').addEventListener('click', () => cerrarModal('modalGasto'));
  document.getElementById('btnCancelarModal').addEventListener('click', () => cerrarModal('modalGasto'));

  document.getElementById('fTipo').addEventListener('change', actualizarCamposCuotas);
  document.getElementById('fTotalCuotas').addEventListener('input', actualizarCamposCuotas);

  attachMoneyInput(document.getElementById('fValor'));
  document.getElementById('fValor').addEventListener('input', actualizarCamposCuotas);

  document.getElementById('btnGuardar').addEventListener('click', guardarGasto);

  document.getElementById('modalEliminarClose').addEventListener('click', () => cerrarModal('modalEliminar'));
  document.getElementById('btnCancelarEliminar').addEventListener('click', () => cerrarModal('modalEliminar'));
  document.getElementById('btnConfirmarEliminar').addEventListener('click', confirmarEliminar);

  document.getElementById('btnExportar').addEventListener('click', exportarCSV);

  document.querySelectorAll('.modal-overlay').forEach(el => {
    el.addEventListener('click', e => { if (e.target === el) cerrarModal(el.id); });
  });

  // Delegación de eventos para acciones de fila (evita problemas de
  // escape de comillas en onclick inline — causa raíz del bug de eliminar)
  document.getElementById('bodyGastos').addEventListener('click', e => {
    const switchEl = e.target.closest('[data-action="toggle-estado"]');
    if (switchEl) { toggleEstado(switchEl); return; }

    const btnEditar = e.target.closest('[data-action="editar"]');
    if (btnEditar) { abrirEditar(btnEditar.dataset.id); return; }

    const btnEliminar = e.target.closest('[data-action="eliminar"]');
    if (btnEliminar) {
      const row = btnEliminar.closest('tr');
      const concepto = gastosData.find(g => String(g.id) === String(btnEliminar.dataset.id))?.concepto ?? '';
      abrirEliminar(btnEliminar.dataset.id, concepto);
    }
  });
}
