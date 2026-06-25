<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/config/app.php';
require_once __DIR__ . '/../../src/config/database.php';

$pageTitle    = 'Dashboard';
$pageSubtitle = 'Resumen financiero del mes';
$activeNav    = 'dashboard';
$extraCssFile = url('assets/css/dashboard.css');

ob_start();
?>

<!-- ── KPI Cards ──────────────────────────────────────────────── -->
<div class="stats-grid" id="statsGrid">
  <div class="stat-card primary">
    <div class="stat-header">
      <span class="stat-label">Ingresos del mes</span>
      <div class="stat-icon primary">💰</div>
    </div>
    <div class="stat-value skeleton" id="statIngresos">$ —</div>
    <div class="stat-footer" id="statIngresosFooter">Cargando...</div>
  </div>

  <div class="stat-card danger">
    <div class="stat-header">
      <span class="stat-label">Gastos del mes</span>
      <div class="stat-icon danger">💸</div>
    </div>
    <div class="stat-value skeleton" id="statGastos">$ —</div>
    <div class="stat-footer" id="statGastosFooter">Cargando...</div>
  </div>

  <div class="stat-card success">
    <div class="stat-header">
      <span class="stat-label">Balance disponible</span>
      <div class="stat-icon success">📈</div>
    </div>
    <div class="stat-value skeleton" id="statBalance">$ —</div>
    <div class="stat-footer" id="statBalanceFooter">Cargando...</div>
  </div>

  <div class="stat-card warning">
    <div class="stat-header">
      <span class="stat-label">Pendientes</span>
      <div class="stat-icon warning">⏳</div>
    </div>
    <div class="stat-value skeleton" id="statPendientes">$ —</div>
    <div class="stat-footer" id="statPendientesFooter">Cargando...</div>
  </div>

  <div class="stat-card secondary">
    <div class="stat-header">
      <span class="stat-label">Pagados</span>
      <div class="stat-icon secondary">✅</div>
    </div>
    <div class="stat-value skeleton" id="statPagados">$ —</div>
    <div class="stat-footer" id="statPagadosFooter">Cargando...</div>
  </div>

  <div class="stat-card info">
    <div class="stat-header">
      <span class="stat-label">Total ahorrado</span>
      <div class="stat-icon info">🏦</div>
    </div>
    <div class="stat-value skeleton" id="statAhorros">$ —</div>
    <div class="stat-footer" id="statAhorrosFooter">Cargando...</div>
  </div>

  <div class="stat-card primary">
    <div class="stat-header">
      <span class="stat-label">Presupuesto</span>
      <div class="stat-icon primary">🎯</div>
    </div>
    <div class="stat-value skeleton" id="statPresupuesto">— %</div>
    <div class="stat-footer">
      <div class="progress-bar-wrap">
        <div class="progress-bar" id="progressPresupuesto" style="width:0%"></div>
      </div>
    </div>
  </div>

  <div class="stat-card danger">
    <div class="stat-header">
      <span class="stat-label">Por vencer / Vencidos</span>
      <div class="stat-icon danger">🔔</div>
    </div>
    <div class="stat-value skeleton" id="statVencimientos">— / —</div>
    <div class="stat-footer" id="statVencimientosFooter">Cargando...</div>
  </div>
</div>

<!-- ── Fila de gráficos ─────────────────────────────────────────── -->
<div class="dashboard-row" style="display:grid;grid-template-columns:1fr 1.8fr;gap:1.25rem;margin-bottom:1.25rem;">

  <!-- Gastos por categoría -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">🍩 Gastos por categoría</span>
      <span class="text-muted fs-sm" id="chartCatPeriodo"></span>
    </div>
    <div class="card-body" style="display:flex;flex-direction:column;align-items:center;gap:1rem;">
      <div style="position:relative;width:200px;height:200px;">
        <canvas id="chartCategorias"></canvas>
        <div id="chartCatCenter" style="
          position:absolute;inset:0;display:flex;flex-direction:column;
          align-items:center;justify-content:center;pointer-events:none;">
          <span style="font-size:0.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px">Total</span>
          <span id="chartCatTotal" style="font-size:1.125rem;font-weight:800;color:var(--text-primary)">$ —</span>
        </div>
      </div>
      <ul class="chart-legend" id="legendCategorias"></ul>
    </div>
  </div>

  <!-- Evolución mensual -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">📊 Ingresos vs Gastos</span>
      <span class="text-muted fs-sm">Últimos 6 meses</span>
    </div>
    <div class="card-body">
      <canvas id="chartEvolucion" height="200"></canvas>
    </div>
  </div>

</div>

<!-- ── Fila inferior ─────────────────────────────────────────────── -->
<div class="dashboard-row" style="display:grid;grid-template-columns:1.2fr 1fr;gap:1.25rem;">

  <!-- Próximos vencimientos -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">📅 Próximos vencimientos</span>
      <a href="<?= url('views/gastos.php') ?>" class="btn btn-sm btn-outline">Ver todos</a>
    </div>
    <div class="card-body" style="padding:0;">
      <div class="table-responsive">
        <table class="table" id="tablaVencimientos">
          <thead>
            <tr>
              <th>Concepto</th>
              <th>Responsable</th>
              <th>Fecha</th>
              <th>Estado</th>
              <th class="text-right">Valor</th>
            </tr>
          </thead>
          <tbody id="bodyVencimientos">
            <tr><td colspan="5" class="text-center text-muted" style="padding:2rem">Cargando...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Últimos movimientos -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">🕐 Últimos movimientos</span>
    </div>
    <div class="card-body" style="padding:0;">
      <ul class="movimientos-list" id="listaRecientes">
        <li style="padding:2rem;text-align:center;color:var(--text-muted)">Cargando...</li>
      </ul>
    </div>
  </div>

</div>

<?php
$content = ob_get_clean();

// Estilos en assets/css/dashboard.css (cargado via $extraCssFile arriba)

$extraJs = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="' . url('assets/js/dashboard.js') . '"></script>';

require_once __DIR__ . '/layout.php';
?>
