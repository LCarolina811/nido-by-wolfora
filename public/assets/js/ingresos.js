/**
 * ingresos.js — Módulo de Ingresos.
 * Depende de shared.js (formatCOP, parseNumberCOP, formatNumberCOP,
 *   attachMoneyInput, showToast, escHtml, fmtDate).
 * Variables globales inyectadas desde la vista:
 *   window.APP_URL, window.CURRENT_USER_ID
 */

const API_I           = window.APP_URL + '/api/ingresos.php';
const CURRENT_USER_ID = window.CURRENT_USER_ID || 0;

let periodos    = [];
let categorias  = [];
let miembros    = [];
let ingresosData = [];
let editId      = null;
let deleteId    = null;

// ── Init ─────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', async () => {
  await Promise.all([cargarPeriodos(), cargarCategorias(), cargarMiembros()]);
  await cargarIngresos();
  bindEvents();
  attachMoneyInput(document.getElementById('fValor'));
});

// ── Datos base ───────────────────────────────────────────────────

async function cargarPeriodos() {
  const r = await fetch(API_I + '?action=periodos');
  const j = await r.json();
  periodos = j.data || [];

  const opts = periodos.map(p =>
    `<option value="${p.id}" ${p.activo ? 'selected' : ''}>${p.label}</option>`
  ).join('');

  document.getElementById('filPeriodo').innerHTML = opts || '<option value="">Sin períodos</option>';
  document.getElementById('fPeriodo').innerHTML   = opts || '<option value="">Sin períodos</option>';
}

async function cargarCategorias() {
  const r = await fetch(API_I + '?action=categorias');
  const j = await r.json();
  categorias = j.data || [];

  const opts = categorias.map(c =>
    `<option value="${c.id}">${c.icono} ${escHtml(c.nombre)}</option>`
  ).join('');

  document.getElementById('filCategoria').innerHTML = '<option value="">Todas</option>' + opts;
  document.getElementById('fCategoria').innerHTML   = '<option value="">Selecciona...</option>' + opts;
}

async function cargarMiembros() {
  const r = await fetch(API_I + '?action=miembros');
  const j = await r.json();
  miembros = j.data || [];

  const opts = miembros.map(m =>
    `<option value="${m.id}" ${m.id === CURRENT_USER_ID ? 'selected' : ''}>${escHtml(m.nombre)}</option>`
  ).join('');

  document.getElementById('filMiembro').innerHTML = '<option value="">Todos</option>' + opts;
  document.getElementById('fPropietario').innerHTML = opts;
}

// ── Filtros ──────────────────────────────────────────────────────

function buildParams() {
  const p = new URLSearchParams();
  const pid = document.getElementById('filPeriodo').value;
  if (pid) p.set('periodo_id', pid);

  const cat  = document.getElementById('filCategoria').value;
  const tipo = document.getElementById('filTipo').value;
  const uid  = document.getElementById('filMiembro').value;
  if (cat)  p.set('categoria_id', cat);
  if (tipo) p.set('tipo', tipo);
  if (uid)  p.set('usuario_id', uid);

  return p.toString();
}

// ── Cargar tabla + resumen ────────────────────────────────────────

async function cargarIngresos() {
  const q = buildParams();
  const [rI, rR] = await Promise.all([
    fetch(API_I + '?action=listar&' + q),
    fetch(API_I + '?action=resumen&' + q),
  ]);
  const [jI, jR] = await Promise.all([rI.json(), rR.json()]);

  if (!jI.success) { showToast('Error al cargar ingresos.', 'error'); return; }
  ingresosData = jI.data || [];
  renderTabla(ingresosData);

  if (jR.success) {
    const d = jR.data;
    setText('rTotal',        formatCOP(d.total));
    setText('rCantidad',     d.cantidad + ' registros');
    setText('rFijos',        formatCOP(d.fijos));
    setText('rCntFijos',     d.cnt_fijos + ' ingresos fijos');
    setText('rVariables',    formatCOP(d.variables));
    setText('rCntVariables', d.cnt_variables + ' variables');
  }
}

function setText(id, txt) {
  const el = document.getElementById(id);
  if (el) el.textContent = txt;
}

// ── Render tabla ─────────────────────────────────────────────────

function renderTabla(rows) {
  const tbody = document.getElementById('bodyIngresos');
  setText('badgeCount', rows.length);

  if (!rows.length) {
    tbody.innerHTML = `<tr><td colspan="7"><div class="empty-state">
      <div class="empty-icon">📭</div>
      <p>Sin ingresos con los filtros seleccionados</p>
    </div></td></tr>`;
    setText('tablaInfo', 'Sin resultados');
    setText('tablaTotales', '');
    return;
  }

  let totalFijo = 0, totalVar = 0;

  tbody.innerHTML = rows.map(i => {
    if (i.tipo === 'fijo') totalFijo += parseFloat(i.valor);
    else totalVar += parseFloat(i.valor);

    const acciones = i.puede_editar
      ? `<button class="btn btn-icon" data-action="editar" data-id="${i.id}" title="Editar">✏️</button>
         <button class="btn btn-icon btn-danger-outline" data-action="eliminar" data-id="${i.id}" title="Eliminar">🗑</button>`
      : `<span class="quin-chip" title="Solo el propietario puede modificar">🔒</span>`;

    const tipoReplica = i.ingreso_padre_id && i.ingreso_padre_id === i.id
      ? '<span style="font-size:.65rem;color:var(--text-muted);display:block">Replica mensual</span>' : '';

    return `<tr>
      <td>
        <div style="font-weight:600;font-size:.875rem">${escHtml(i.concepto)}</div>
        ${tipoReplica}
        ${i.notas ? `<span style="font-size:.7rem;color:var(--text-muted)" title="${escHtml(i.notas)}">📝 ${escHtml(i.notas)}</span>` : ''}
      </td>
      <td>
        <span style="display:inline-flex;align-items:center;gap:.35rem">
          <span style="width:8px;height:8px;border-radius:50%;background:${i.cat_color};display:inline-block"></span>
          ${escHtml(i.cat_icono)} ${escHtml(i.categoria)}
        </span>
      </td>
      <td>
        <span class="owner-chip">
          <span class="owner-dot" style="background:${i.propietario_color}"></span>
          ${escHtml(i.propietario)}
        </span>
      </td>
      <td><span class="tipo-chip tipo-${i.tipo}">${i.tipo}</span></td>
      <td style="white-space:nowrap;font-size:.8125rem">${fmtDate(i.fecha)}</td>
      <td class="text-right amount positive">${formatCOP(i.valor)}</td>
      <td><div class="acciones">${acciones}</div></td>
    </tr>`;
  }).join('');

  const total = totalFijo + totalVar;
  setText('tablaInfo', `${rows.length} registro${rows.length !== 1 ? 's' : ''}`);
  document.getElementById('tablaTotales').innerHTML =
    `<span style="color:var(--info,#06b6d4)">${formatCOP(totalFijo)} fijos</span> &nbsp;·&nbsp;
     <span class="text-secondary">${formatCOP(totalVar)} variables</span> &nbsp;·&nbsp;
     <strong class="text-success">Total: ${formatCOP(total)}</strong>`;
}

// ── Modal nuevo ───────────────────────────────────────────────────

function abrirNuevo() {
  editId = null;
  document.getElementById('modalTitle').textContent = 'Nuevo ingreso';
  document.getElementById('formIngreso').reset();
  document.getElementById('fId').value  = '';
  document.getElementById('fFecha').value = new Date().toISOString().split('T')[0];
  document.getElementById('fPropietario').value = CURRENT_USER_ID;

  const activo = periodos.find(p => p.activo);
  if (activo) document.getElementById('fPeriodo').value = activo.id;

  actualizarInfoFijo();
  abrirModal('modalIngreso');
}

// ── Modal editar ──────────────────────────────────────────────────

function abrirEditar(id) {
  const ing = ingresosData.find(x => String(x.id) === String(id));
  if (!ing) return;
  if (!ing.puede_editar) { showToast('Solo el propietario puede editar este ingreso.', 'warning'); return; }

  editId = id;
  document.getElementById('modalTitle').textContent = 'Editar ingreso';
  document.getElementById('fId').value             = ing.id;
  document.getElementById('fConcepto').value        = ing.concepto;
  document.getElementById('fCategoria').value       = categorias.find(c => c.nombre === ing.categoria)?.id ?? '';
  document.getElementById('fValor').value           = formatNumberCOP(ing.valor);
  document.getElementById('fPropietario').value     = ing.usuario_id;
  document.getElementById('fTipo').value            = ing.tipo;
  document.getElementById('fFecha').value           = ing.fecha;
  document.getElementById('fPeriodo').value         = ing.periodo_id;
  document.getElementById('fNotas').value           = ing.notas ?? '';

  actualizarInfoFijo();
  abrirModal('modalIngreso');
}

// ── Guardar ───────────────────────────────────────────────────────

async function guardarIngreso() {
  const payload = {
    concepto:     document.getElementById('fConcepto').value.trim(),
    categoria_id: document.getElementById('fCategoria').value,
    valor:        parseNumberCOP(document.getElementById('fValor').value),
    usuario_id:   document.getElementById('fPropietario').value,
    tipo:         document.getElementById('fTipo').value,
    fecha:        document.getElementById('fFecha').value,
    periodo_id:   document.getElementById('fPeriodo').value,
    notas:        document.getElementById('fNotas').value.trim(),
  };
  if (editId) payload.id = editId;

  setGuardando(true);
  const r = await fetch(API_I + '?action=' + (editId ? 'editar' : 'crear'), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
  const j = await r.json();
  setGuardando(false);

  if (j.success) {
    cerrarModal('modalIngreso');
    showToast(j.message, 'success');
    await cargarIngresos();
  } else {
    const msg = Array.isArray(j.errors) ? j.errors.join('\n') : j.message;
    showToast(msg, 'error');
  }
}

// ── Eliminar ──────────────────────────────────────────────────────

function abrirEliminar(id) {
  const ing = ingresosData.find(x => String(x.id) === String(id));
  if (!ing) return;
  if (!ing.puede_editar) { showToast('Solo el propietario puede eliminar este ingreso.', 'warning'); return; }

  deleteId = id;
  document.getElementById('elimConcepto').textContent = ing.concepto;
  abrirModal('modalEliminar');
}

async function confirmarEliminar() {
  if (!deleteId) return;
  const btn = document.getElementById('btnConfirmarEliminar');
  btn.disabled = true;
  try {
    const r = await fetch(API_I + '?action=eliminar&id=' + deleteId, { method: 'DELETE' });
    const j = await r.json();
    if (j.success) {
      cerrarModal('modalEliminar');
      showToast('Ingreso eliminado.', 'success');
      deleteId = null;
      await cargarIngresos();
    } else showToast(j.message || 'Error al eliminar.', 'error');
  } catch { showToast('Error de conexión.', 'error'); }
  finally { btn.disabled = false; }
}

// ── Tipo fijo: info visual ─────────────────────────────────────────

function actualizarInfoFijo() {
  const tipo  = document.getElementById('fTipo').value;
  const info  = document.getElementById('infoFijo');
  info.style.display = tipo === 'fijo' ? 'block' : 'none';
}

// ── CSV ───────────────────────────────────────────────────────────

function exportarCSV() {
  if (!ingresosData.length) return showToast('Sin datos.', 'warning');
  const headers = ['Concepto','Categoría','Propietario','Tipo','Fecha','Valor'];
  const rows = ingresosData.map(i => [
    i.concepto, i.categoria, i.propietario, i.tipo, i.fecha, i.valor,
  ]);
  const csv = [headers, ...rows]
    .map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(','))
    .join('\n');
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }));
  a.download = `ingresos_${new Date().toISOString().slice(0,7)}.csv`;
  a.click();
}

// ── Helpers ──────────────────────────────────────────────────────

function abrirModal(id)  { document.getElementById(id).classList.add('open'); }
function cerrarModal(id) { document.getElementById(id).classList.remove('open'); }
function setGuardando(v) {
  document.getElementById('btnGuardar').disabled = v;
  document.getElementById('spinnerGuardar').style.display = v ? 'block' : 'none';
  document.getElementById('btnGuardarText').textContent   = v ? 'Guardando...' : 'Guardar';
}

// ── Bind eventos ──────────────────────────────────────────────────

function bindEvents() {
  ['filPeriodo','filCategoria','filTipo','filMiembro'].forEach(id =>
    document.getElementById(id).addEventListener('change', cargarIngresos)
  );

  document.getElementById('btnLimpiar').addEventListener('click', () => {
    ['filCategoria','filTipo','filMiembro'].forEach(id => (document.getElementById(id).value = ''));
    const activo = periodos.find(p => p.activo);
    if (activo) document.getElementById('filPeriodo').value = activo.id;
    cargarIngresos();
  });

  document.getElementById('btnNuevo').addEventListener('click', abrirNuevo);
  document.getElementById('modalClose').addEventListener('click', () => cerrarModal('modalIngreso'));
  document.getElementById('btnCancelarModal').addEventListener('click', () => cerrarModal('modalIngreso'));

  document.getElementById('fTipo').addEventListener('change', actualizarInfoFijo);
  document.getElementById('btnGuardar').addEventListener('click', guardarIngreso);

  document.getElementById('modalEliminarClose').addEventListener('click', () => cerrarModal('modalEliminar'));
  document.getElementById('btnCancelarEliminar').addEventListener('click', () => cerrarModal('modalEliminar'));
  document.getElementById('btnConfirmarEliminar').addEventListener('click', confirmarEliminar);

  document.getElementById('btnExportar').addEventListener('click', exportarCSV);

  document.querySelectorAll('.modal-overlay').forEach(el =>
    el.addEventListener('click', e => { if (e.target === el) cerrarModal(el.id); })
  );

  // Delegación en la tabla
  document.getElementById('bodyIngresos').addEventListener('click', e => {
    const ed  = e.target.closest('[data-action="editar"]');
    if (ed)  { abrirEditar(ed.dataset.id); return; }

    const del = e.target.closest('[data-action="eliminar"]');
    if (del) { abrirEliminar(del.dataset.id); }
  });
}
