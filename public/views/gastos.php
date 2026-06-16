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
              <input type="text" id="fValor" class="form-control light" placeholder="0" required />
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

$extraJs = '<script>window.VISTA = ' . json_encode($vista) . ';</script>
<script src="' . url('assets/js/gastos.js') . '"></script>';

require_once __DIR__ . '/layout.php';
?>
