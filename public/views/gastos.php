<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/config/app.php';
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/helpers/auth_helper.php';

session_start_safe();
$nidoId = current_nido_id();

// Vista personal: ?vista_usuario=<id de un miembro del Nido>
$vistaUsuarioId = isset($_GET['vista_usuario']) ? (int) $_GET['vista_usuario'] : 0;
$vistaUsuarioNombre = '';
if ($vistaUsuarioId) {
    $stmt = db()->prepare('SELECT nombre FROM usuarios WHERE id = :id AND nido_id = :nido');
    $stmt->execute([':id' => $vistaUsuarioId, ':nido' => $nidoId]);
    $vistaUsuarioNombre = $stmt->fetchColumn() ?: '';
    if (!$vistaUsuarioNombre) $vistaUsuarioId = 0;
}

// Modo lectura: el usuario está viendo gastos de OTRO miembro del Nido
$isReadOnly = $vistaUsuarioId > 0 && $vistaUsuarioId !== current_user_id();

$pageTitle = match(true) {
    $isReadOnly         => "👁 Gastos de {$vistaUsuarioNombre}",
    (bool)$vistaUsuarioId => "Gastos — {$vistaUsuarioNombre}",
    default             => 'Gastos',
};
$pageSubtitle = $isReadOnly
    ? 'Vista de solo lectura — puedes consultar pero no modificar'
    : ($vistaUsuarioId ? 'Tu porción de gastos personales + compartidos' : 'Todos los gastos del Nido');
$activeNav = $vistaUsuarioId ? 'miembro-' . $vistaUsuarioId : 'gastos';

$extraCssFile = url('assets/css/gastos.css');

ob_start();
?>

<?php if ($isReadOnly): ?>
<!-- Banner de solo lectura -->
<div class="readonly-banner" style="
  display:flex; align-items:center; gap:.75rem;
  background:rgba(245,158,11,0.1);
  border:1px solid rgba(245,158,11,0.3);
  border-radius:var(--border-radius-sm);
  padding:.75rem 1.125rem;
  margin-bottom:1.125rem;
  font-size:.875rem;
  color:var(--warning);
">
  <span style="font-size:1.125rem">👁</span>
  <span>
    Estás viendo los gastos de <strong><?= htmlspecialchars($vistaUsuarioNombre) ?></strong>.
    Esta es una vista de <strong>solo lectura</strong> — no puedes crear, editar ni modificar registros ajenos.
  </span>
</div>
<?php endif; ?>

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
    <div class="stat-header"><span class="stat-label">Vista</span><div class="stat-icon primary"><?= $vistaUsuarioId ? '👤' : '👫' ?></div></div>
    <div class="stat-value" style="font-size:1.1rem;text-transform:capitalize"><?= htmlspecialchars($vistaUsuarioNombre ?: 'General') ?></div>
    <div class="stat-footer">Filtro activo</div>
  </div>
</div>

<!-- ── Barra de filtros ────────────────────────────────────────── -->
<div class="card mb-2">
  <div class="card-body" style="padding:1rem 1.375rem;">
    <div class="filtros-bar">
      <div class="filtro-group">
        <label class="filtro-label">Período</label>
        <select id="filPeriodo" class="filtro-select"><option value="">Cargando...</option></select>
      </div>
      <div class="filtro-group">
        <label class="filtro-label">Categoría</label>
        <select id="filCategoria" class="filtro-select"><option value="">Todas</option></select>
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
      <?php if (!$isReadOnly): ?>
        <button class="btn btn-primary btn-sm" id="btnNuevo">+ Nuevo gasto</button>
      <?php endif; ?>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table" id="tablaGastos">
      <thead>
        <tr>
          <th>Concepto</th>
          <th>Categoría</th>
          <th>Para</th>
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

        <!-- Concepto y Categoría -->
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

        <!-- Valor y Tipo -->
        <div class="form-row-2">
          <div class="form-group">
            <label class="dark" for="fValor">Valor total *</label>
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
        </div>

        <!-- Sección cuotas (visible solo si tipo = cuotas) -->
        <div id="grupoCuotas" style="display:none">
          <div class="form-row-2">
            <div class="form-group">
              <label class="dark" for="fTotalCuotas">Número de cuotas *</label>
              <div class="input-wrap">
                <span class="input-icon dark">🔢</span>
                <input type="number" id="fTotalCuotas" class="form-control light" placeholder="Ej: 12" min="2" max="60" />
              </div>
            </div>
            <div class="form-group">
              <label class="dark">Valor por cuota</label>
              <div class="input-wrap">
                <span class="input-icon dark">📅</span>
                <input type="text" id="fValorCuota" class="form-control light" placeholder="$ —" readonly
                       style="background:var(--bg-body);cursor:default;font-weight:700;color:var(--primary)" />
              </div>
            </div>
          </div>
          <div class="info-box mb-1" id="resumenCuota" style="display:none"></div>
        </div>

        <!-- Fecha, Estado y Período -->
        <div class="form-row-3">
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
          <div class="form-group">
            <label class="dark" for="fPeriodo">Período *</label>
            <div class="input-wrap">
              <span class="input-icon dark">📆</span>
              <select id="fPeriodo" class="form-control light" style="padding-left:2.75rem" required>
                <option value="">Cargando...</option>
              </select>
            </div>
          </div>
        </div>

        <!-- ── Distribución del gasto ─────────────────────────── -->
        <div class="form-group">

          <!-- Toggle ¿Es un gasto compartido? -->
          <div class="compartido-toggle" id="compartidoToggle">
            <span class="toggle-icon">🤝</span>
            <div class="toggle-info">
              <div class="toggle-label">¿Es un gasto compartido?</div>
              <div class="toggle-sub" id="compartidoSub">Solo tú lo asumes</div>
            </div>
            <div class="toggle-indicator"></div>
          </div>

          <!-- Panel visible solo si compartido = true -->
          <div class="compartido-panel" id="compartidoPanel" style="display:none">
            <div class="dist-tabs">
              <button type="button" class="dist-tab active" data-dist="igual">⚖️ Partes iguales</button>
              <button type="button" class="dist-tab" data-dist="personalizado">✏️ Personalizado</button>
            </div>

            <!-- Partes iguales: preview automático -->
            <div class="dist-panel-content" id="panelIgual">
              <div class="partes-iguales-preview" id="previewIgual">
                <!-- Llenado dinámicamente -->
              </div>
            </div>

            <!-- Personalizado: input por miembro -->
            <div class="dist-panel-content" id="panelPersonalizado" style="display:none">
              <div class="personalizado-rows" id="montoRows">
                <!-- Llenado dinámicamente -->
              </div>
              <div class="total-row">
                <span class="total-label">Total asignado</span>
                <span class="total-valor pending" id="totalPersonalizado">$ —</span>
              </div>
            </div>
          </div>

        </div>

        <!-- Notas -->
        <div class="form-group">
          <label class="dark" for="fNotas">Notas</label>
          <textarea id="fNotas" class="form-control light"
                    style="padding-left:1rem;resize:vertical;min-height:64px"
                    placeholder="Observaciones opcionales..."></textarea>
        </div>

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
      <p style="color:var(--text-secondary)">
        ¿Estás seguro de eliminar el gasto <strong id="elimConcepto"></strong>?
        Esta acción no se puede deshacer.
      </p>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" id="btnCancelarEliminar">Cancelar</button>
      <button class="btn" style="background:var(--danger);color:#fff" id="btnConfirmarEliminar">Eliminar</button>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();

$extraJs = '<script>
  window.VISTA_USUARIO_ID = ' . json_encode($vistaUsuarioId ?: null) . ';
  window.CURRENT_USER_ID  = ' . json_encode(current_user_id()) . ';
  window.IS_READ_ONLY     = ' . json_encode($isReadOnly) . ';
</script>
<script src="' . url('assets/js/gastos.js') . '"></script>';

require_once __DIR__ . '/layout.php';
?>
