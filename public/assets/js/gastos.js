/**
 * gastos.js — Módulo de Gastos (arquitectura Nido).
 * Depende de shared.js (formatCOP, parseNumberCOP, formatNumberCOP,
 *   attachMoneyInput, showToast, escHtml, fmtDate).
 * Variables globales inyectadas desde la vista:
 *   window.APP_URL, window.VISTA_USUARIO_ID, window.CURRENT_USER_ID
 */

const API_G            = window.APP_URL + '/api/gastos.php';
const VISTA_USUARIO_ID = window.VISTA_USUARIO_ID || null;
const CURRENT_USER_ID  = window.CURRENT_USER_ID  || 0;
const IS_READ_ONLY     = window.IS_READ_ONLY     || false;

let periodos    = [];
let categorias  = [];
let miembros    = [];
let gastosData  = [];
let editId      = null;
let deleteId    = null;
let esCompartido      = false;
let distribucionActual = 'igual'; // 'igual' | 'personalizado'

// ── Init ─────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', async () => {
  await Promise.all([cargarPeriodos(), cargarCategorias(), cargarMiembros()]);
  await cargarGastos();
  bindEvents();
  attachMoneyInput(document.getElementById('fValor'));
});

// ── Datos base ───────────────────────────────────────────────────

async function cargarPeriodos() {
  const r = await fetch(API_G + '?action=periodos');
  const j = await r.json();
  if (!j.success) { console.error('Error cargando períodos:', j.message); return; }
  periodos = j.data || [];

  if (!periodos.length) {
    const msg = '<option value="">Sin períodos disponibles</option>';
    document.getElementById('filPeriodo').innerHTML = msg;
    document.getElementById('fPeriodo').innerHTML   = msg;
    return;
  }

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

  document.getElementById('filCategoria').innerHTML = '<option value="">Todas</option>' + opts;
  document.getElementById('fCategoria').innerHTML   = '<option value="">Selecciona...</option>' + opts;
}

async function cargarMiembros() {
  const r = await fetch(API_G + '?action=miembros');
  const j = await r.json();
  miembros = j.data || [];
  renderMiembrosUI();
}

// ── UI de miembros en el formulario ───────────────────────────────

function renderMiembrosUI() {
  // Partes iguales: preview automático (sin inputs, solo muestra)
  document.getElementById('previewIgual').innerHTML = miembros.map(m => `
    <div class="parte-igual-row" data-uid="${m.id}">
      <span class="miembro-avatar-sm" style="background:${m.avatar_color}">${initials(m.nombre)}</span>
      <span class="miembro-nombre-sm">${escHtml(m.nombre)}</span>
      <span class="miembro-monto-sm" data-igual-monto>$ —</span>
    </div>
  `).join('');

  // Personalizado: un input por miembro
  document.getElementById('montoRows').innerHTML = miembros.map(m => `
    <div class="personalizado-row" data-uid="${m.id}">
      <span class="miembro-avatar-sm" style="background:${m.avatar_color}">${initials(m.nombre)}</span>
      <span class="miembro-nombre-sm">${escHtml(m.nombre)}</span>
      <input type="text" class="monto-inp" data-uid="${m.id}" placeholder="$ 0" />
    </div>
  `).join('');

  document.querySelectorAll('.monto-inp').forEach(inp => {
    attachMoneyInput(inp);
    inp.addEventListener('input', actualizarTotalPersonalizado);
  });
}

function initials(nombre) {
  return String(nombre || '').trim().charAt(0).toUpperCase();
}

// ── Filtros ─────────────────────────────────────────────────────

function buildParams() {
  const p = new URLSearchParams();
  const pid = document.getElementById('filPeriodo').value;
  if (pid) p.set('periodo_id', pid);
  if (VISTA_USUARIO_ID) p.set('vista_usuario', VISTA_USUARIO_ID);

  const cat  = document.getElementById('filCategoria').value;
  const tipo = document.getElementById('filTipo').value;
  const est  = document.getElementById('filEstado').value;
  const quin = document.getElementById('filQuincena').value;
  if (cat)  p.set('categoria_id', cat);
  if (tipo) p.set('tipo', tipo);
  if (est)  p.set('estado', est);
  if (quin) p.set('quincena', quin);

  return p.toString();
}

// ── Cargar datos de la tabla ──────────────────────────────────────

async function cargarGastos() {
  const q = buildParams();
  const [rG, rR] = await Promise.all([
    fetch(API_G + '?action=listar&' + q),
    fetch(API_G + '?action=resumen&' + q),
  ]);
  const [jG, jR] = await Promise.all([rG.json(), rR.json()]);

  if (!jG.success) { showToast('Error al cargar gastos.', 'error'); return; }
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

function setText(id, txt) {
  const el = document.getElementById(id);
  if (el) el.textContent = txt;
}

// ── Render de tabla ───────────────────────────────────────────────

function renderTabla(rows) {
  const tbody = document.getElementById('bodyGastos');
  setText('badgeCount', rows.length);

  if (!rows.length) {
    tbody.innerHTML = `<tr><td colspan="9"><div class="empty-state">
      <div class="empty-icon">📭</div><p>Sin gastos con los filtros seleccionados</p>
    </div></td></tr>`;
    setText('tablaInfo', 'Sin resultados');
    setText('tablaTotales', '');
    return;
  }

  let totalPend = 0, totalPag = 0;

  tbody.innerHTML = rows.map(g => {
    const vm = parseFloat(g.valor_mostrado ?? g.valor);
    if (g.estado === 'pendiente') totalPend += vm;
    else totalPag += vm;

    const dias = parseInt(g.dias_venc, 10);

    // Label de cuota: "2/12 · $ 100.000/mes"
    let cuotaLabel = '';
    if (g.cuota_numero) {
      const vc = g.valor_cuota ? ' · ' + formatCOP(g.valor_cuota) + '/mes' : '';
      cuotaLabel = `<span class="quin-chip">${g.cuota_numero}/${g.total_cuotas}${vc}</span>`;
    }

    // En modo lectura nunca se muestran acciones, independientemente del creador
    const acciones = IS_READ_ONLY
      ? ''
      : g.puede_editar
        ? `<button class="btn btn-icon" data-action="editar" data-id="${g.id}" title="Editar">✏️</button>
           <button class="btn btn-icon btn-danger-outline" data-action="eliminar" data-id="${g.id}" title="Eliminar">🗑</button>`
        : `<span class="quin-chip" title="Solo el creador puede editar/eliminar">🔒</span>`;

    return `<tr>
      <td>
        <div style="font-weight:600;font-size:.875rem">${escHtml(g.concepto)}</div>
        ${cuotaLabel}
        ${g.notas ? `<span class="quin-chip" title="${escHtml(g.notas)}">📝</span>` : ''}
      </td>
      <td>
        <span style="display:inline-flex;align-items:center;gap:.35rem">
          <span style="width:8px;height:8px;border-radius:50%;background:${g.cat_color};display:inline-block"></span>
          ${escHtml(g.cat_icono)} ${escHtml(g.categoria)}
        </span>
      </td>
      <td>${renderParticipantes(g.participantes)}</td>
      <td><span class="tipo-chip tipo-${g.tipo}">${g.tipo}</span></td>
      <td><span class="quin-chip">${g.quincena === 'primera' ? '1ª' : '2ª'}</span></td>
      <td style="white-space:nowrap;font-size:.8125rem">${fmtDate(g.fecha_pago)}</td>
      <td>${IS_READ_ONLY ? renderEstadoBadge(g.estado, dias) : renderSwitch(g.id, g.estado, dias)}</td>
      <td class="text-right amount ${g.estado === 'pagado' ? '' : 'negative'}">${formatCOP(vm)}</td>
      <td><div class="acciones">${acciones}</div></td>
    </tr>`;
  }).join('');

  const total = totalPend + totalPag;
  setText('tablaInfo', `${rows.length} registro${rows.length !== 1 ? 's' : ''}`);
  document.getElementById('tablaTotales').innerHTML =
    `<span class="text-danger">${formatCOP(totalPend)} pendiente</span> &nbsp;·&nbsp;
     <span class="text-success">${formatCOP(totalPag)} pagado</span> &nbsp;·&nbsp;
     <strong>Total: ${formatCOP(total)}</strong>`;
}

function renderParticipantes(parts) {
  if (!parts || !parts.length) return '<span class="quin-chip">—</span>';
  return `<div class="participantes-chips">` + parts.map(p =>
    `<span class="participante-chip">
       <span class="participante-dot" style="background:${p.color}"></span>
       ${escHtml(p.nombre)}
     </span>`
  ).join('') + '</div>';
}

/** Badge estático (sin interacción) para modo lectura. */
function renderEstadoBadge(estado, dias) {
  if (estado === 'pagado')  return `<span class="badge badge-success">✅ Pagado</span>`;
  if (dias < 0)             return `<span class="badge badge-danger">🔴 Vencido</span>`;
  if (dias <= 5)            return `<span class="badge badge-warning">🟡 Próximo</span>`;
  return                           `<span class="badge badge-neutral">⏳ Pendiente</span>`;
}

function renderSwitch(id, estado, dias) {
  const isPaid = estado === 'pagado';
  const label = isPaid ? 'Pagado' : (dias < 0 ? 'Vencido' : (dias <= 5 ? 'Próximo' : 'Pendiente'));
  return `<label class="status-switch ${isPaid ? 'is-paid' : ''}"
            data-action="toggle-estado" data-id="${id}" data-estado="${estado}">
    <span class="switch-track"><span class="switch-thumb"></span></span>
    <span class="switch-label">${label}</span>
  </label>`;
}

// ── Toggle estado ────────────────────────────────────────────────

async function toggleEstado(el) {
  const id     = el.dataset.id;
  const actual = el.dataset.estado;
  const nuevo  = actual === 'pagado' ? 'pendiente' : 'pagado';

  el.classList.add('is-loading');
  const r = await fetch(API_G + '?action=estado', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id, estado: nuevo }),
  });
  const j = await r.json();
  el.classList.remove('is-loading');

  if (j.success) { showToast('Estado actualizado', 'success'); await cargarGastos(); }
  else showToast(j.message || 'Error.', 'error');
}

// ── Lógica cuotas: cálculo automático ────────────────────────────

function actualizarCuotas() {
  const tipo   = document.getElementById('fTipo').value;
  const grupo  = document.getElementById('grupoCuotas');
  const resumen = document.getElementById('resumenCuota');
  const inpVC  = document.getElementById('fValorCuota');

  if (tipo !== 'cuotas') {
    grupo.style.display  = 'none';
    resumen.style.display = 'none';
    return;
  }

  grupo.style.display = 'block';

  const valor  = parseNumberCOP(document.getElementById('fValor').value) || 0;
  const cuotas = parseInt(document.getElementById('fTotalCuotas').value, 10) || 0;

  if (valor > 0 && cuotas >= 2) {
    const vc = Math.round(valor / cuotas);
    inpVC.value = formatNumberCOP(vc);
    resumen.style.display = 'block';
    resumen.innerHTML =
      `💡 <strong>${cuotas} cuotas</strong> de <strong>${formatCOP(vc)}</strong>/mes` +
      ` = <strong>${formatCOP(vc * cuotas)}</strong> total`;
  } else {
    inpVC.value = '';
    resumen.style.display = 'none';
  }
}

// ── Distribución: toggle compartido ───────────────────────────────

function setCompartido(activo) {
  esCompartido = activo;
  const toggle = document.getElementById('compartidoToggle');
  const panel  = document.getElementById('compartidoPanel');
  const sub    = document.getElementById('compartidoSub');

  toggle.classList.toggle('active', activo);
  panel.style.display = activo ? 'block' : 'none';
  sub.textContent = activo ? 'Definí cómo se divide' : 'Solo tú lo asumes';

  if (activo) actualizarDistribucion();
}

function setDistribucion(tipo) {
  distribucionActual = tipo;

  document.querySelectorAll('.dist-tab').forEach(t =>
    t.classList.toggle('active', t.dataset.dist === tipo)
  );
  document.getElementById('panelIgual').style.display         = tipo === 'igual' ? 'block' : 'none';
  document.getElementById('panelPersonalizado').style.display = tipo === 'personalizado' ? 'block' : 'none';

  actualizarDistribucion();
}

function actualizarDistribucion() {
  if (!esCompartido) return;
  if (distribucionActual === 'igual') actualizarPreviewIgual();
  if (distribucionActual === 'personalizado') actualizarTotalPersonalizado();
}

function actualizarPreviewIgual() {
  const valor = parseNumberCOP(document.getElementById('fValor').value) || 0;
  const n     = miembros.length;
  const rows  = document.querySelectorAll('[data-igual-monto]');

  if (valor > 0 && n > 0) {
    const vc = Math.round(valor / n);
    rows.forEach(el => el.textContent = formatCOP(vc));
  } else {
    rows.forEach(el => el.textContent = '$ —');
  }
}

function actualizarTotalPersonalizado() {
  const valor = parseNumberCOP(document.getElementById('fValor').value) || 0;
  const inputs = Array.from(document.querySelectorAll('.monto-inp'));
  const suma   = inputs.reduce((acc, inp) => acc + (parseNumberCOP(inp.value) || 0), 0);

  const el   = document.getElementById('totalPersonalizado');
  const diff = valor - suma;
  const ok   = valor > 0 && Math.abs(diff) < 1;

  el.textContent = formatCOP(suma);
  el.className   = 'total-valor ' + (ok ? 'ok' : (suma > valor ? 'error' : 'pending'));
}

// ── Construir payload de distribución ────────────────────────────

function buildDistribucionPayload() {
  if (!esCompartido) {
    return { distribucion: 'individual', usuario_id: CURRENT_USER_ID };
  }

  if (distribucionActual === 'igual') {
    return {
      distribucion: 'igual',
      participantes: miembros.map(m => String(m.id)),
    };
  }

  // personalizado
  const participantes = [];
  const montos = {};
  document.querySelectorAll('.personalizado-row').forEach(row => {
    const uid   = row.dataset.uid;
    const monto = parseNumberCOP(row.querySelector('.monto-inp').value) || 0;
    if (monto > 0) {
      participantes.push(uid);
      montos[uid] = monto;
    }
  });
  return { distribucion: 'personalizado', participantes, montos };
}

// ── Modal: abrir nuevo ────────────────────────────────────────────

function abrirNuevo() {
  editId = null;
  document.getElementById('modalTitle').textContent = 'Nuevo gasto';
  document.getElementById('formGasto').reset();
  document.getElementById('fId').value   = '';
  document.getElementById('fFecha').value = new Date().toISOString().split('T')[0];
  document.getElementById('fValorCuota').value = '';

  const activo = periodos.find(p => p.activo);
  if (activo) document.getElementById('fPeriodo').value = activo.id;

  // Reset distribución
  setCompartido(false);
  setDistribucion('igual');
  document.querySelectorAll('.monto-inp').forEach(i => i.value = '');
  actualizarCuotas();
  abrirModal('modalGasto');
}

// ── Modal: editar ─────────────────────────────────────────────────

function abrirEditar(id) {
  const g = gastosData.find(x => String(x.id) === String(id));
  if (!g) return;
  if (!g.puede_editar) { showToast('Solo el creador puede editar este gasto.', 'warning'); return; }

  editId = id;
  document.getElementById('modalTitle').textContent = 'Editar gasto';
  document.getElementById('fId').value          = g.id;
  document.getElementById('fConcepto').value     = g.concepto;
  document.getElementById('fCategoria').value    = categorias.find(c => c.nombre === g.categoria)?.id ?? '';
  document.getElementById('fValor').value        = formatNumberCOP(g.valor);
  document.getElementById('fTipo').value         = g.tipo;
  document.getElementById('fFecha').value        = g.fecha_pago;
  document.getElementById('fEstado').value       = g.estado;
  document.getElementById('fPeriodo').value      = g.periodo_id;
  document.getElementById('fNotas').value        = g.notas ?? '';
  if (g.total_cuotas) document.getElementById('fTotalCuotas').value = g.total_cuotas;

  actualizarCuotas();

  // Reconstruir distribución
  const parts = g.participantes || [];
  if (parts.length === 1 && parts[0].usuario_id === CURRENT_USER_ID) {
    setCompartido(false);
  } else {
    const montosIguales = parts.length > 1 &&
      parts.every(p => Math.abs(p.monto - parts[0].monto) < 1);

    setCompartido(true);
    setDistribucion(montosIguales ? 'igual' : 'personalizado');

    if (!montosIguales) {
      document.querySelectorAll('.personalizado-row').forEach(row => {
        const p = parts.find(x => String(x.usuario_id) === row.dataset.uid);
        const inp = row.querySelector('.monto-inp');
        inp.value = p ? formatNumberCOP(p.monto) : '';
      });
      actualizarTotalPersonalizado();
    }
  }

  abrirModal('modalGasto');
}

// ── Guardar ────────────────────────────────────────────────────────

async function guardarGasto() {
  const payload = {
    concepto:     document.getElementById('fConcepto').value.trim(),
    categoria_id: document.getElementById('fCategoria').value,
    valor:        parseNumberCOP(document.getElementById('fValor').value),
    tipo:         document.getElementById('fTipo').value,
    fecha_pago:   document.getElementById('fFecha').value,
    estado:       document.getElementById('fEstado').value,
    periodo_id:   document.getElementById('fPeriodo').value,
    notas:        document.getElementById('fNotas').value.trim(),
    total_cuotas: parseInt(document.getElementById('fTotalCuotas').value, 10) || 0,
    ...buildDistribucionPayload(),
  };
  if (editId) payload.id = editId;

  setGuardando(true);
  const r = await fetch(API_G + '?action=' + (editId ? 'editar' : 'crear'), {
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
    const msg = Array.isArray(j.errors) ? j.errors.join('\n') : j.message;
    showToast(msg, 'error');
  }
}

// ── Eliminar ──────────────────────────────────────────────────────

function abrirEliminar(id) {
  const g = gastosData.find(x => String(x.id) === String(id));
  if (!g) return;
  if (!g.puede_editar) { showToast('Solo el creador puede eliminar este gasto.', 'warning'); return; }

  deleteId = id;
  document.getElementById('elimConcepto').textContent = g.concepto;
  abrirModal('modalEliminar');
}

async function confirmarEliminar() {
  if (!deleteId) return;
  const btn = document.getElementById('btnConfirmarEliminar');
  btn.disabled = true;
  try {
    const r = await fetch(API_G + '?action=eliminar&id=' + deleteId, { method: 'DELETE' });
    const j = await r.json();
    if (j.success) {
      cerrarModal('modalEliminar');
      showToast('Gasto eliminado.', 'success');
      deleteId = null;
      await cargarGastos();
    } else showToast(j.message || 'No se pudo eliminar.', 'error');
  } catch { showToast('Error de conexión.', 'error'); }
  finally { btn.disabled = false; }
}

// ── CSV ───────────────────────────────────────────────────────────

function exportarCSV() {
  if (!gastosData.length) return showToast('Sin datos.', 'warning');
  const headers = ['Concepto','Categoría','Para','Tipo','Fecha','Quincena','Estado','Valor'];
  const rows = gastosData.map(g => [
    g.concepto, g.categoria,
    (g.participantes || []).map(p => p.nombre).join('/'),
    g.tipo, g.fecha_pago, g.quincena, g.estado, g.valor_mostrado ?? g.valor,
  ]);
  const csv = [headers, ...rows]
    .map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(','))
    .join('\n');
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }));
  a.download = `gastos_${new Date().toISOString().slice(0,7)}.csv`;
  a.click();
}

// ── Modal helpers ─────────────────────────────────────────────────

function abrirModal(id)  { document.getElementById(id).classList.add('open'); }
function cerrarModal(id) { document.getElementById(id).classList.remove('open'); }
function setGuardando(v) {
  document.getElementById('btnGuardar').disabled = v;
  document.getElementById('spinnerGuardar').style.display = v ? 'block' : 'none';
  document.getElementById('btnGuardarText').textContent   = v ? 'Guardando...' : 'Guardar';
}

// ── Bind eventos ──────────────────────────────────────────────────

function bindEvents() {
  // Filtros
  ['filPeriodo','filCategoria','filTipo','filEstado','filQuincena'].forEach(id =>
    document.getElementById(id).addEventListener('change', cargarGastos)
  );
  document.getElementById('btnLimpiar').addEventListener('click', () => {
    ['filCategoria','filTipo','filEstado','filQuincena'].forEach(id =>
      (document.getElementById(id).value = '')
    );
    const activo = periodos.find(p => p.activo);
    if (activo) document.getElementById('filPeriodo').value = activo.id;
    cargarGastos();
  });

  // Modal nuevo / cerrar (el botón Nuevo no existe en modo lectura)
  const btnNuevo = document.getElementById('btnNuevo');
  if (btnNuevo) btnNuevo.addEventListener('click', abrirNuevo);
  document.getElementById('modalClose')?.addEventListener('click', () => cerrarModal('modalGasto'));
  document.getElementById('btnCancelarModal')?.addEventListener('click', () => cerrarModal('modalGasto'));

  // Cuotas
  document.getElementById('fTipo').addEventListener('change', actualizarCuotas);
  document.getElementById('fTotalCuotas').addEventListener('input', actualizarCuotas);
  document.getElementById('fValor').addEventListener('input', () => {
    actualizarCuotas();
    actualizarDistribucion();
  });

  // Toggle compartido
  document.getElementById('compartidoToggle').addEventListener('click', () => setCompartido(!esCompartido));

  // Tabs distribución
  document.querySelectorAll('.dist-tab').forEach(btn =>
    btn.addEventListener('click', () => setDistribucion(btn.dataset.dist))
  );

  // Guardar / Eliminar
  document.getElementById('btnGuardar').addEventListener('click', guardarGasto);
  document.getElementById('modalEliminarClose').addEventListener('click', () => cerrarModal('modalEliminar'));
  document.getElementById('btnCancelarEliminar').addEventListener('click', () => cerrarModal('modalEliminar'));
  document.getElementById('btnConfirmarEliminar').addEventListener('click', confirmarEliminar);

  // Exportar
  document.getElementById('btnExportar').addEventListener('click', exportarCSV);

  // Cerrar modales al click en overlay
  document.querySelectorAll('.modal-overlay').forEach(el =>
    el.addEventListener('click', e => { if (e.target === el) cerrarModal(el.id); })
  );

  // Delegación de eventos en la tabla — en modo lectura se ignoran todas las acciones
  document.getElementById('bodyGastos').addEventListener('click', e => {
    if (IS_READ_ONLY) return;

    const sw  = e.target.closest('[data-action="toggle-estado"]');
    if (sw) { toggleEstado(sw); return; }

    const ed  = e.target.closest('[data-action="editar"]');
    if (ed) { abrirEditar(ed.dataset.id); return; }

    const del = e.target.closest('[data-action="eliminar"]');
    if (del) { abrirEliminar(del.dataset.id); }
  });
}
