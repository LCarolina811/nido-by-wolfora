<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/config/app.php';
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/helpers/auth_helper.php';

session_start_safe();

$pageTitle    = 'Ingresos';
$pageSubtitle = 'Ingresos del Nido';
$activeNav    = 'ingresos';
$extraCssFile = url('assets/css/ingresos.css');

ob_start();
?>

<!-- ── KPI Cards ──────────────────────────────────────────────── -->
<div class="stats-grid stats-grid-4" style="margin-bottom:1.25rem">
  <div class="stat-card success">
    <div class="stat-header">
      <span class="stat-label">Total ingresos</span>
      <div class="stat-icon success">💰</div>
    </div>
    <div class="stat-value" id="rTotal">$ —</div>
    <div class="stat-footer" id="rCantidad">Cargando...</div>
  </div>
  <div class="stat-card info">
    <div class="stat-header">
      <span class="stat-label">Ingresos fijos</span>
      <div class="stat-icon info">📌</div>
    </div>
    <div class="stat-value" id="rFijos">$ —</div>
    <div class="stat-footer" id="rCntFijos">Cargando...</div>
  </div>
  <div class="stat-card primary">
    <div class="stat-header">
      <span class="stat-label">Ingresos variables</span>
      <div class="stat-icon primary">📈</div>
    </div>
    <div class="stat-value" id="rVariables">$ —</div>
    <div class="stat-footer" id="rCntVariables">Cargando...</div>
  </div>
  <div class="stat-card secondary">
    <div class="stat-header">
      <span class="stat-label">Período activo</span>
      <div class="stat-icon secondary">📅</div>
    </div>
    <div class="stat-value" style="font-size:1rem" id="rPeriodo">—</div>
    <div class="stat-footer">Mes en curso</div>
  </div>
</div>

<!-- ── Filtros ─────────────────────────────────────────────────── -->
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
          <option value="variable">Variable</option>
        </select>
      </div>
      <div class="filtro-group">
        <label class="filtro-label">Miembro</label>
        <select id="filMiembro" class="filtro-select"><option value="">Todos</option></select>
      </div>
      <div class="filtro-group" style="align-self:flex-end;">
        <button class="btn btn-outline btn-sm" id="btnLimpiar">↺ Limpiar</button>
      </div>
    </div>
  </div>
</div>

<!-- ── Tabla ───────────────────────────────────────────────────── -->
<div class="card">
  <div class="card-header">
    <span class="card-title">
      💰 Ingresos
      <span class="badge badge-primary" id="badgeCount">0</span>
    </span>
    <div class="d-flex gap-1">
      <button class="btn btn-sm btn-outline" id="btnExportar" title="Exportar CSV">⬇ CSV</button>
      <button class="btn btn-primary btn-sm" id="btnNuevo">+ Nuevo ingreso</button>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>Concepto</th>
          <th>Categoría</th>
          <th>Propietario</th>
          <th>Tipo</th>
          <th>Fecha</th>
          <th class="text-right">Valor</th>
          <th class="text-center">Acciones</th>
        </tr>
      </thead>
      <tbody id="bodyIngresos">
        <tr><td colspan="7" class="text-center text-muted" style="padding:3rem">Cargando...</td></tr>
      </tbody>
    </table>
  </div>

  <div class="card-footer" style="display:flex;justify-content:space-between;align-items:center;">
    <span class="fs-sm text-muted" id="tablaInfo">—</span>
    <span class="fw-bold" id="tablaTotales"></span>
  </div>
</div>

<!-- ── Modal Crear / Editar ────────────────────────────────────── -->
<div class="modal-overlay" id="modalIngreso">
  <div class="modal" style="max-width:520px">
    <div class="modal-header">
      <span class="modal-title" id="modalTitle">Nuevo ingreso</span>
      <button class="modal-close" id="modalClose">✕</button>
    </div>
    <div class="modal-body">
      <form id="formIngreso" novalidate>
        <input type="hidden" id="fId" />

        <div class="form-row-2">
          <div class="form-group">
            <label class="dark" for="fConcepto">Concepto *</label>
            <div class="input-wrap">
              <span class="input-icon dark">📝</span>
              <input type="text" id="fConcepto" class="form-control light"
                     placeholder="Ej: Salario, Comisión..." maxlength="200" required />
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
                <option value="fijo">Fijo (mensual)</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="dark" for="fPropietario">Propietario *</label>
            <div class="input-wrap">
              <span class="input-icon dark">👤</span>
              <select id="fPropietario" class="form-control light" style="padding-left:2.75rem" required>
              </select>
            </div>
          </div>
        </div>

        <!-- Info fijo -->
        <div class="info-box mb-1" id="infoFijo" style="display:none">
          📌 Los ingresos <strong>fijos</strong> se replican automáticamente
          cada mes. Úsalos para salarios, arriendos recibidos u otros
          ingresos recurrentes.
        </div>

        <div class="form-row-2">
          <div class="form-group">
            <label class="dark" for="fFecha">Fecha *</label>
            <div class="input-wrap">
              <span class="input-icon dark">📅</span>
              <input type="date" id="fFecha" class="form-control light" required />
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

<!-- ── Modal eliminar ──────────────────────────────────────────── -->
<div class="modal-overlay" id="modalEliminar">
  <div class="modal" style="max-width:420px">
    <div class="modal-header">
      <span class="modal-title">⚠️ Confirmar eliminación</span>
      <button class="modal-close" id="modalEliminarClose">✕</button>
    </div>
    <div class="modal-body">
      <p style="color:var(--text-secondary)">
        ¿Eliminar el ingreso <strong id="elimConcepto"></strong>?
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

// Período activo para el stat card
$periodoLabel = '';
try {
    $stmt = db()->prepare('SELECT anio, mes FROM periodos WHERE nido_id = :nido AND activo = 1 LIMIT 1');
    $stmt->execute([':nido' => current_nido_id()]);
    $p = $stmt->fetch();
    if ($p) {
        require_once __DIR__ . '/../../src/helpers/date_helper.php';
        $periodoLabel = nombre_mes((int)$p['mes']) . ' ' . $p['anio'];
    }
} catch (PDOException) {}

$extraJs = '<script>
  window.CURRENT_USER_ID = ' . json_encode(current_user_id()) . ';
</script>
<script src="' . url('assets/js/ingresos.js') . '"></script>
<script>
  document.addEventListener("DOMContentLoaded", () => {
    const rp = document.getElementById("rPeriodo");
    if (rp) rp.textContent = ' . json_encode($periodoLabel ?: '—') . ';
  });
</script>';

require_once __DIR__ . '/layout.php';
?>
