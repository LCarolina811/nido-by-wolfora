<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/config/app.php';
require_once __DIR__ . '/../../src/config/database.php';

// Vista: carolina | javier | (vacío = todos)
$vista = in_array($_GET['vista'] ?? '', ['carolina','javier']) ? $_GET['vista'] : '';

$pageTitle = match($vista) {
    'carolina' => 'Gastos — Carolina',
    'javier'   => 'Gastos — Javier',
    default    => 'Gastos',
};
$pageSubtitle = $vista ? 'Gastos personales + compartidos' : 'Todos los gastos del mes';
$activeNav    = $vista ?: 'gastos';

ob_start();
?>

<!-- ── Resumen rápido ──────────────────────────────────────────── -->
<div class="stats-grid stats-grid-4" id="resumenGastos" style="margin-bottom:1.25rem">
  <div class="stat-card danger">
    <div class="stat-header"><span class="stat-label">Total gastos</span><div class="stat-icon danger">💸</div></div>
    <div class="stat-value" id="rTotal">$ —</div>
    <div class="stat-footer" id="rCantidad">— registros</div>
  </div>
  <div class="stat-card warning">
    <div class="stat-header"><span class="stat-label">Pendientes</span><div class="stat-icon warning">⏳</div></div>
    <div class="stat-value" id="rPendientes">$ —</div>
    <div class="stat-footer" id="rCntPendientes">— por pagar</div>
  </div>
  <div class="stat-card success">
    <div class="stat-header"><span class="stat-label">Pagados</span><div class="stat-icon success">✅</div></div>
    <div class="stat-value" id="rPagados">$ —</div>
    <div class="stat-footer" id="rCntPagados">— liquidados</div>
  </div>
  <div class="stat-card primary">
    <div class="stat-header"><span class="stat-label">Vista</span><div class="stat-icon primary"><?= $vista === 'carolina' ? '👩' : ($vista === 'javier' ? '👨' : '👫') ?></div></div>
    <div class="stat-value" style="font-size:1.1rem;text-transform:capitalize"><?= $vista ?: 'General' ?></div>
    <div class="stat-footer">Filtro activo</div>
  </div>
</div>

<!-- ── Barra de filtros ────────────────────────────────────────── -->
<div class="card mb-2">
  <div class="card-body" style="padding:1rem 1.375rem;">
    <div class="filtros-bar">

      <div class="filtro-group">
        <label class="filtro-label">Período</label>
        <select id="filPeriodo" class="filtro-select">
          <option value="">Cargando...</option>
        </select>
      </div>

      <div class="filtro-group">
        <label class="filtro-label">Categoría</label>
        <select id="filCategoria" class="filtro-select">
          <option value="">Todas</option>
        </select>
      </div>

      <div class="filtro-group">
        <label class="filtro-label">Tipo</label>
        <select id="filTipo" class="filtro-select">
          <option value="">Todos</option>
          <option value="fijo">Fijo</option>
          <option value="cuotas">Cuotas</option>
          <option value="variable">Variable</option>
        </select>
      </div>

      <div class="filtro-group">
        <label class="filtro-label">Estado</label>
        <select id="filEstado" class="filtro-select">
          <option value="">Todos</option>
          <option value="pendiente">Pendiente</option>
          <option value="pagado">Pagado</option>
        </select>
      </div>

      <div class="filtro-group">
        <label class="filtro-label">Quincena</label>
        <select id="filQuincena" class="filtro-select">
          <option value="">Ambas</option>
          <option value="primera">Primera</option>
          <option value="segunda">Segunda</option>
        </select>
      </div>

      <div class="filtro-group" style="align-self:flex-end;">
        <button class="btn btn-outline btn-sm" id="btnLimpiar">↺ Limpiar</button>
      </div>

    </div>
  </div>
</div>

<!-- ── Tabla principal ─────────────────────────────────────────── -->
<div class="card">
  <div class="card-header">
    <span class="card-title">
      💸 Gastos
      <span class="badge badge-primary" id="badgeCount">0</span>
    </span>
    <div class="d-flex gap-1">
      <button class="btn btn-sm btn-outline" id="btnExportar" title="Exportar CSV">⬇ CSV</button>
      <button class="btn btn-primary btn-sm" id="btnNuevo">+ Nuevo gasto</button>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table" id="tablaGastos">
      <thead>
        <tr>
          <th>Concepto</th>
          <th>Categoría</th>
          <th>Responsable</th>
          <th>Tipo</th>
          <th>Quincena</th>
          <th>Fecha pago</th>
          <th>Estado</th>
          <th class="text-right">Valor</th>
          <th class="text-center">Acciones</th>
        </tr>
      </thead>
      <tbody id="bodyGastos">
        <tr><td colspan="9" class="text-center text-muted" style="padding:3rem">Cargando...</td></tr>
      </tbody>
    </table>
  </div>

  <div class="card-footer" style="display:flex;justify-content:space-between;align-items:center;">
    <span class="fs-sm text-muted" id="tablaInfo">—</span>
    <span class="fw-bold" id="tablaTotales"></span>
  </div>
</div>

<!-- ── Modal Crear / Editar ────────────────────────────────────── -->
<div class="modal-overlay" id="modalGasto">
  <div class="modal" style="max-width:560px">
    <div class="modal-header">
      <span class="modal-title" id="modalTitle">Nuevo gasto</span>
      <button class="modal-close" id="modalClose">✕</button>
    </div>
    <div class="modal-body">
      <form id="formGasto" novalidate>
        <input type="hidden" id="fId" />

        <div class="form-row-2">
          <div class="form-group">
            <label class="dark" for="fConcepto">Concepto *</label>
            <div class="input-wrap">
              <span class="input-icon dark">📝</span>
              <input type="text" id="fConcepto" class="form-control light" placeholder="Ej: Netflix, Arriendo..." maxlength="200" required />
            </div>
          </div>
          <div class="form-group">
            <label class="dark" for="fCategoria">Categoría *</label>
            <div class="input-wrap">
              <span class="input-icon dark">🏷️</span>
              <select id="fCategoria" class="form-control light" style="padding-left:2.75rem" required>
                <option value="">Selecciona...</option>
              </select>
            </div>
          </div>
        </div>

        <div class="form-row-3">
          <div class="form-group">
            <label class="dark" for="fValor">Valor *</label>
            <div class="input-wrap">
              <span class="input-icon dark">💲</span>
              <input type="number" id="fValor" class="form-control light" placeholder="0" min="1" step="100" required />
            </div>
          </div>
          <div class="form-group">
            <label class="dark" for="fTipo">Tipo *</label>
            <div class="input-wrap">
              <span class="input-icon dark">📌</span>
              <select id="fTipo" class="form-control light" style="padding-left:2.75rem" required>
                <option value="variable">Variable</option>
                <option value="fijo">Fijo</option>
                <option value="cuotas">Cuotas</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="dark" for="fResponsable">Responsable *</label>
            <div class="input-wrap">
              <span class="input-icon dark">👤</span>
              <select id="fResponsable" class="form-control light" style="padding-left:2.75rem" required>
                <option value="compartido">👫 Compartido</option>
                <option value="carolina">👩 Carolina</option>
                <option value="javier">👨 Javier</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Campo cuotas (visible solo si tipo = cuotas) -->
        <div class="form-group" id="grupoCuotas" style="display:none">
          <label class="dark" for="fTotalCuotas">Total de cuotas *</label>
          <div class="input-wrap">
            <span class="input-icon dark">🔢</span>
            <input type="number" id="fTotalCuotas" class="form-control light" placeholder="Ej: 12" min="2" max="60" />
          </div>
          <small class="text-muted" style="font-size:.75rem;margin-top:.25rem;display:block">
            El sistema creará automáticamente la cuota del siguiente mes.
          </small>
        </div>

        <div class="form-row-2">
          <div class="form-group">
            <label class="dark" for="fFecha">Fecha de pago *</label>
            <div class="input-wrap">
              <span class="input-icon dark">📅</span>
              <input type="date" id="fFecha" class="form-control light" required />
            </div>
          </div>
          <div class="form-group">
            <label class="dark" for="fEstado">Estado</label>
            <div class="input-wrap">
              <span class="input-icon dark">🔖</span>
              <select id="fEstado" class="form-control light" style="padding-left:2.75rem">
                <option value="pendiente">⏳ Pendiente</option>
                <option value="pagado">✅ Pagado</option>
              </select>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label class="dark" for="fPeriodo">Período *</label>
          <div class="input-wrap">
            <span class="input-icon dark">📆</span>
            <select id="fPeriodo" class="form-control light" style="padding-left:2.75rem" required>
              <option value="">Selecciona...</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="dark" for="fNotas">Notas</label>
          <textarea id="fNotas" class="form-control light" style="padding-left:1rem;resize:vertical;min-height:72px" placeholder="Observaciones opcionales..."></textarea>
        </div>

        <!-- Resumen cuota (si tipo = cuotas) -->
        <div id="resumenCuota" class="info-box" style="display:none"></div>

      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" id="btnCancelarModal">Cancelar</button>
      <button class="btn btn-primary" id="btnGuardar">
        <span class="spinner" id="spinnerGuardar"></span>
        <span id="btnGuardarText">Guardar</span>
      </button>
    </div>
  </div>
</div>

<!-- ── Modal confirmar eliminación ────────────────────────────── -->
<div class="modal-overlay" id="modalEliminar">
  <div class="modal" style="max-width:420px">
    <div class="modal-header">
      <span class="modal-title">⚠️ Confirmar eliminación</span>
      <button class="modal-close" id="modalEliminarClose">✕</button>
    </div>
    <div class="modal-body">
      <p style="color:var(--text-secondary)">¿Estás seguro de eliminar el gasto <strong id="elimConcepto"></strong>? Esta acción no se puede deshacer.</p>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" id="btnCancelarEliminar">Cancelar</button>
      <button class="btn" style="background:var(--danger);color:#fff" id="btnConfirmarEliminar">Eliminar</button>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();

$extraCss = '<style>
/* ── Gastos extras ──────────────────────────────────── */
.stats-grid-4 { grid-template-columns: repeat(4, 1fr); }

.filtros-bar {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  align-items: flex-end;
}
.filtro-group  { display: flex; flex-direction: column; gap: 0.3rem; }
.filtro-label  { font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: .5px; }
.filtro-select {
  height: 36px;
  padding: 0 0.75rem;
  border: 1px solid var(--border);
  border-radius: var(--border-radius-sm);
  font-size: 0.875rem;
  color: var(--text-primary);
  background: var(--bg-body);
  cursor: pointer;
  min-width: 130px;
  outline: none;
  transition: border-color var(--transition);
}
.filtro-select:focus { border-color: var(--primary); }

/* Form layout */
.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.875rem; }
.form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.875rem; }

/* Info box cuota */
.info-box {
  background: var(--primary-light);
  border: 1px solid rgba(108,99,255,0.2);
  border-radius: var(--border-radius-sm);
  padding: 0.75rem 1rem;
  font-size: 0.875rem;
  color: var(--primary);
  line-height: 1.6;
}

/* Badges tipo */
.tipo-chip {
  display: inline-block;
  padding: 0.2rem 0.55rem;
  border-radius: 20px;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .3px;
}
.tipo-fijo     { background: rgba(6,182,212,0.12);  color: #06b6d4; }
.tipo-cuotas   { background: rgba(245,158,11,0.12); color: var(--warning); }
.tipo-variable { background: var(--neutral-light);  color: var(--neutral); }

/* Estado toggle */
.estado-btn {
  border: none; background: none; cursor: pointer;
  padding: 0; line-height: 1;
}

/* Acciones */
.acciones { display: flex; gap: 0.4rem; justify-content: center; }

/* Quincena label */
.quin-chip {
  font-size: 0.7rem;
  font-weight: 600;
  color: var(--text-muted);
}

@media (max-width: 900px) {
  .stats-grid-4 { grid-template-columns: repeat(2, 1fr); }
  .form-row-2, .form-row-3 { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
  .stats-grid-4 { grid-template-columns: 1fr; }
}
</style>';

$extraJs = '<script>
const API_G = APP_URL + \'/api/gastos.php\';
const VISTA = ' . json_encode($vista) . ';

let periodos    = [];
let categorias  = [];
let gastosData  = [];
let editId      = null;
let deleteId    = null;

// ── Inicializar ────────────────────────────────────────
document.addEventListener("DOMContentLoaded", async () => {
  await Promise.all([cargarPeriodos(), cargarCategorias()]);
  await cargarGastos();
  bindEvents();
});

// ── Datos base ─────────────────────────────────────────
async function cargarPeriodos() {
  const r = await fetch(API_G + "?action=periodos");
  const j = await r.json();
  periodos = j.data || [];

  const opts = periodos.map(p =>
    `<option value="${p.id}" ${p.activo ? "selected" : ""}>${p.label}</option>`
  ).join("");

  document.getElementById("filPeriodo").innerHTML = opts;
  document.getElementById("fPeriodo").innerHTML   = opts;
}

async function cargarCategorias() {
  const r = await fetch(API_G + "?action=categorias");
  const j = await r.json();
  categorias = j.data || [];

  const opts = categorias.map(c =>
    `<option value="${c.id}">${c.icono} ${esc(c.nombre)}</option>`
  ).join("");

  document.getElementById("filCategoria").innerHTML = `<option value="">Todas</option>` + opts;
  document.getElementById("fCategoria").innerHTML   = `<option value="">Selecciona...</option>` + opts;
}

// ── Construir query params ──────────────────────────────
function buildParams(extra = {}) {
  const p = new URLSearchParams();
  const pid = document.getElementById("filPeriodo").value;
  if (pid)          p.set("periodo_id",  pid);
  if (VISTA)        p.set("responsable", VISTA);
  const cat = document.getElementById("filCategoria").value;
  const tipo = document.getElementById("filTipo").value;
  const est  = document.getElementById("filEstado").value;
  const quin = document.getElementById("filQuincena").value;
  if (cat)  p.set("categoria_id", cat);
  if (tipo) p.set("tipo",         tipo);
  if (est)  p.set("estado",       est);
  if (quin) p.set("quincena",     quin);
  Object.entries(extra).forEach(([k,v]) => p.set(k, v));
  return p.toString();
}

// ── Cargar tabla + resumen ──────────────────────────────
async function cargarGastos() {
  const q = buildParams();
  const [rG, rR] = await Promise.all([
    fetch(API_G + "?action=listar&"  + q),
    fetch(API_G + "?action=resumen&" + q),
  ]);
  const [jG, jR] = await Promise.all([rG.json(), rR.json()]);

  gastosData = jG.data || [];
  renderTabla(gastosData);

  if (jR.success) {
    const d = jR.data;
    document.getElementById("rTotal").textContent        = fmt(d.total);
    document.getElementById("rCantidad").textContent     = d.cantidad + " registros";
    document.getElementById("rPendientes").textContent   = fmt(d.pendientes);
    document.getElementById("rCntPendientes").textContent= d.cnt_pendientes + " por pagar";
    document.getElementById("rPagados").textContent      = fmt(d.pagados);
    document.getElementById("rCntPagados").textContent   = d.cnt_pagados + " liquidados";
  }
}

// ── Render tabla ───────────────────────────────────────
function renderTabla(rows) {
  const tbody = document.getElementById("bodyGastos");
  document.getElementById("badgeCount").textContent = rows.length;

  if (!rows.length) {
    tbody.innerHTML = `<tr><td colspan="9"><div class="empty-state">
      <div class="empty-icon">📭</div>
      <p>Sin gastos con los filtros seleccionados</p>
    </div></td></tr>`;
    document.getElementById("tablaInfo").textContent = "Sin resultados";
    document.getElementById("tablaTotales").textContent = "";
    return;
  }

  let totalPend = 0, totalPag = 0;
  tbody.innerHTML = rows.map(g => {
    if (g.estado === "pendiente") totalPend += parseFloat(g.valor);
    else totalPag += parseFloat(g.valor);

    const dias = parseInt(g.dias_venc);
    const estadoChip = estadoBadge(g.estado, dias);
    const cuotaLabel = g.cuota_numero
      ? `<div class="quin-chip">${g.cuota_numero}/${g.total_cuotas}</div>` : "";

    return `<tr data-id="${g.id}">
      <td>
        <div style="font-weight:600;font-size:.875rem">${esc(g.concepto)}</div>
        ${cuotaLabel}
        ${g.notas ? `<div class="quin-chip" title="${esc(g.notas)}">📝</div>` : ""}
      </td>
      <td>
        <span style="display:inline-flex;align-items:center;gap:.35rem">
          <span style="width:8px;height:8px;border-radius:50%;background:${g.cat_color};display:inline-block"></span>
          ${esc(g.cat_icono)} ${esc(g.categoria)}
        </span>
      </td>
      <td>${responsableChip(g.responsable)}</td>
      <td><span class="tipo-chip tipo-${g.tipo}">${g.tipo}</span></td>
      <td><span class="quin-chip">${g.quincena === "primera" ? "1ª quincena" : "2ª quincena"}</span></td>
      <td style="white-space:nowrap;font-size:.8125rem">${fmtDate(g.fecha_pago)}</td>
      <td>
        <button class="estado-btn" onclick="toggleEstado(${g.id}, ${g.estado})" title="Cambiar estado">
          ${estadoChip}
        </button>
      </td>
      <td class="text-right amount ${g.estado === "pagado" ? "" : "negative"}">
        ${fmt(g.valor)}
      </td>
      <td>
        <div class="acciones">
          <button class="btn btn-icon" onclick="abrirEditar(${g.id})" title="Editar">✏️</button>
          <button class="btn btn-icon btn-danger-outline" onclick="abrirEliminar(${g.id}, ${esc(g.concepto)})" title="Eliminar">🗑</button>
        </div>
      </td>
    </tr>`;
  }).join("");

  const total = totalPend + totalPag;
  document.getElementById("tablaInfo").textContent =
    `${rows.length} registro${rows.length !== 1 ? "s" : ""}`;
  document.getElementById("tablaTotales").innerHTML =
    `<span class="text-danger">${fmt(totalPend)} pendiente</span>
     &nbsp;·&nbsp;
     <span class="text-success">${fmt(totalPag)} pagado</span>
     &nbsp;·&nbsp;
     <strong>Total: ${fmt(total)}</strong>`;
}

// ── Chips visuales ─────────────────────────────────────
function estadoBadge(estado, dias) {
  if (estado === "pagado")  return `<span class="badge badge-success">✅ Pagado</span>`;
  if (dias < 0)             return `<span class="badge badge-danger">🔴 Vencido</span>`;
  if (dias <= 5)            return `<span class="badge badge-warning">🟡 Próximo</span>`;
  return                           `<span class="badge badge-neutral">⏳ Pendiente</span>`;
}

function responsableChip(r) {
  const map = {
    carolina:   `<span style="color:#ff6b9d;font-size:.8125rem">👩 Carolina</span>`,
    javier:     `<span style="color:#6c63ff;font-size:.8125rem">👨 Javier</span>`,
    compartido: `<span style="color:#10b981;font-size:.8125rem">👫 Compartido</span>`,
  };
  return map[r] ?? r;
}

// ── Toggle estado directo ──────────────────────────────
async function toggleEstado(id, estadoActual) {
  const nuevo = estadoActual === "pagado" ? "pendiente" : "pagado";
  const r = await fetch(API_G + "?action=estado", {
    method: "POST",
    headers: {"Content-Type": "application/json"},
    body: JSON.stringify({ id, estado: nuevo }),
  });
  const j = await r.json();
  if (j.success) { showToast("Estado actualizado"); await cargarGastos(); }
  else showToast(j.message, "error");
}

// ── Modal crear ────────────────────────────────────────
function abrirNuevo() {
  editId = null;
  document.getElementById("modalTitle").textContent = "Nuevo gasto";
  document.getElementById("formGasto").reset();
  document.getElementById("fId").value = "";
  // Fecha por defecto: hoy
  document.getElementById("fFecha").value = new Date().toISOString().split("T")[0];
  // Período activo por defecto
  const activo = periodos.find(p => p.activo);
  if (activo) document.getElementById("fPeriodo").value = activo.id;
  actualizarCamposCuotas();
  abrirModal("modalGasto");
}

// ── Modal editar ───────────────────────────────────────
function abrirEditar(id) {
  const g = gastosData.find(x => x.id == id);
  if (!g) return;
  editId = id;

  document.getElementById("modalTitle").textContent = "Editar gasto";
  document.getElementById("fId").value         = g.id;
  document.getElementById("fConcepto").value   = g.concepto;
  document.getElementById("fCategoria").value  = categorias.find(c => c.nombre === g.categoria)?.id ?? "";
  document.getElementById("fValor").value      = g.valor;
  document.getElementById("fTipo").value       = g.tipo;
  document.getElementById("fResponsable").value= g.responsable;
  document.getElementById("fFecha").value      = g.fecha_pago;
  document.getElementById("fEstado").value     = g.estado;
  document.getElementById("fPeriodo").value    = g.periodo_id;
  document.getElementById("fNotas").value      = g.notas ?? "";
  if (g.total_cuotas) document.getElementById("fTotalCuotas").value = g.total_cuotas;

  actualizarCamposCuotas();
  abrirModal("modalGasto");
}

// ── Guardar (crear o editar) ───────────────────────────
async function guardarGasto() {
  const payload = {
    concepto:     document.getElementById("fConcepto").value.trim(),
    categoria_id: document.getElementById("fCategoria").value,
    valor:        parseFloat(document.getElementById("fValor").value),
    tipo:         document.getElementById("fTipo").value,
    responsable:  document.getElementById("fResponsable").value,
    fecha_pago:   document.getElementById("fFecha").value,
    estado:       document.getElementById("fEstado").value,
    periodo_id:   document.getElementById("fPeriodo").value,
    notas:        document.getElementById("fNotas").value.trim(),
    total_cuotas: parseInt(document.getElementById("fTotalCuotas").value) || 0,
  };
  if (editId) payload.id = editId;

  setGuardando(true);
  const action = editId ? "editar" : "crear";
  const r = await fetch(API_G + "?action=" + action, {
    method: "POST",
    headers: {"Content-Type": "application/json"},
    body: JSON.stringify(payload),
  });
  const j = await r.json();
  setGuardando(false);

  if (j.success) {
    cerrarModal("modalGasto");
    showToast(j.message, "success");
    await cargarGastos();
  } else {
    const msg = Array.isArray(j.errors) ? j.errors.join("<br>") : j.message;
    showToast(msg, "error");
  }
}

// ── Eliminar ───────────────────────────────────────────
function abrirEliminar(id, concepto) {
  deleteId = id;
  document.getElementById("elimConcepto").textContent = concepto;
  abrirModal("modalEliminar");
}

async function confirmarEliminar() {
  if (!deleteId) return;
  const r = await fetch(API_G + "?action=eliminar&id=" + deleteId, { method: "DELETE" });
  const j = await r.json();
  if (j.success) {
    cerrarModal("modalEliminar");
    showToast("Gasto eliminado", "success");
    await cargarGastos();
  } else {
    showToast(j.message, "error");
  }
}

// ── Campo cuotas dinámico ──────────────────────────────
function actualizarCamposCuotas() {
  const tipo  = document.getElementById("fTipo").value;
  const grupo = document.getElementById("grupoCuotas");
  const resum = document.getElementById("resumenCuota");

  if (tipo === "cuotas") {
    grupo.style.display = "block";
    const valor  = parseFloat(document.getElementById("fValor").value) || 0;
    const cuotas = parseInt(document.getElementById("fTotalCuotas").value) || 0;
    if (valor > 0 && cuotas >= 2) {
      resum.style.display = "block";
      resum.innerHTML = `💡 <strong>${cuotas} cuotas</strong> de <strong>${fmt(valor)}</strong> = <strong>${fmt(valor * cuotas)}</strong> total`;
    } else {
      resum.style.display = "none";
    }
  } else {
    grupo.style.display = "none";
    resum.style.display = "none";
  }
}

// ── Exportar CSV ───────────────────────────────────────
function exportarCSV() {
  if (!gastosData.length) return showToast("Sin datos para exportar", "warning");

  const headers = ["Concepto","Categoría","Responsable","Tipo","Fecha Pago","Quincena","Estado","Valor","Cuota"];
  const rows = gastosData.map(g => [
    g.concepto, g.categoria, g.responsable, g.tipo,
    g.fecha_pago, g.quincena, g.estado, g.valor,
    g.cuota_numero ? `${g.cuota_numero}/${g.total_cuotas}` : "",
  ]);

  const csv = [headers, ...rows].map(r => r.map(c => `"${String(c).replace(/"/g,"\\"")}"`).join(",")).join("\\n");
  const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
  const a = document.createElement("a");
  a.href = URL.createObjectURL(blob);
  a.download = `gastos_${new Date().toISOString().slice(0,7)}.csv`;
  a.click();
}

// ── Utilidades modal ───────────────────────────────────
function abrirModal(id)  { document.getElementById(id).classList.add("open"); }
function cerrarModal(id) { document.getElementById(id).classList.remove("open"); }

function setGuardando(v) {
  document.getElementById("btnGuardar").disabled      = v;
  document.getElementById("spinnerGuardar").style.display = v ? "block" : "none";
  document.getElementById("btnGuardarText").textContent   = v ? "Guardando..." : "Guardar";
}

// ── Utilidades ─────────────────────────────────────────
function fmt(v)    { return formatCOP(v); }
function esc(s)    { return String(s).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;"); }
function fmtDate(d){ const dt = new Date(d+"T00:00:00"); return dt.toLocaleDateString("es-CO",{day:"2-digit",month:"short",year:"numeric"}); }

// ── Bind de eventos ────────────────────────────────────
function bindEvents() {
  // Filtros
  ["filPeriodo","filCategoria","filTipo","filEstado","filQuincena"].forEach(id => {
    document.getElementById(id).addEventListener("change", cargarGastos);
  });

  document.getElementById("btnLimpiar").addEventListener("click", () => {
    document.getElementById("filCategoria").value = "";
    document.getElementById("filTipo").value      = "";
    document.getElementById("filEstado").value    = "";
    document.getElementById("filQuincena").value  = "";
    const activo = periodos.find(p => p.activo);
    if (activo) document.getElementById("filPeriodo").value = activo.id;
    cargarGastos();
  });

  // Modal nuevo
  document.getElementById("btnNuevo").addEventListener("click", abrirNuevo);
  document.getElementById("modalClose").addEventListener("click", () => cerrarModal("modalGasto"));
  document.getElementById("btnCancelarModal").addEventListener("click", () => cerrarModal("modalGasto"));

  // Cuotas dinámico
  document.getElementById("fTipo").addEventListener("change", actualizarCamposCuotas);
  document.getElementById("fValor").addEventListener("input", actualizarCamposCuotas);
  document.getElementById("fTotalCuotas").addEventListener("input", actualizarCamposCuotas);

  // Guardar
  document.getElementById("btnGuardar").addEventListener("click", guardarGasto);

  // Eliminar
  document.getElementById("modalEliminarClose").addEventListener("click", () => cerrarModal("modalEliminar"));
  document.getElementById("btnCancelarEliminar").addEventListener("click", () => cerrarModal("modalEliminar"));
  document.getElementById("btnConfirmarEliminar").addEventListener("click", confirmarEliminar);

  // Exportar
  document.getElementById("btnExportar").addEventListener("click", exportarCSV);

  // Cerrar modal al click en overlay
  document.querySelectorAll(".modal-overlay").forEach(el => {
    el.addEventListener("click", e => { if (e.target === el) cerrarModal(el.id); });
  });
}
</script>';

require_once __DIR__ . '/layout.php';
?>
