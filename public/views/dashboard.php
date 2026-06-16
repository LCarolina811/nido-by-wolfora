<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/config/app.php';
require_once __DIR__ . '/../../src/config/database.php';

$pageTitle    = 'Dashboard';
$pageSubtitle = 'Resumen financiero del mes';
$activeNav    = 'dashboard';

ob_start();
?>

<div class="stats-grid">
  <!-- Placeholders — se llenarán en Fase 4 -->
  <div class="stat-card primary">
    <div class="stat-header">
      <span class="stat-label">Ingresos del mes</span>
      <div class="stat-icon primary">💰</div>
    </div>
    <div class="stat-value" id="statIngresos">$ —</div>
    <div class="stat-footer">Cargando...</div>
  </div>

  <div class="stat-card danger">
    <div class="stat-header">
      <span class="stat-label">Gastos del mes</span>
      <div class="stat-icon danger">💸</div>
    </div>
    <div class="stat-value" id="statGastos">$ —</div>
    <div class="stat-footer">Cargando...</div>
  </div>

  <div class="stat-card success">
    <div class="stat-header">
      <span class="stat-label">Balance disponible</span>
      <div class="stat-icon success">📈</div>
    </div>
    <div class="stat-value" id="statBalance">$ —</div>
    <div class="stat-footer">Cargando...</div>
  </div>

  <div class="stat-card warning">
    <div class="stat-header">
      <span class="stat-label">Pendientes</span>
      <div class="stat-icon warning">⏳</div>
    </div>
    <div class="stat-value" id="statPendientes">$ —</div>
    <div class="stat-footer">Cargando...</div>
  </div>

  <div class="stat-card secondary">
    <div class="stat-header">
      <span class="stat-label">Pagados</span>
      <div class="stat-icon secondary">✅</div>
    </div>
    <div class="stat-value" id="statPagados">$ —</div>
    <div class="stat-footer">Cargando...</div>
  </div>

  <div class="stat-card info">
    <div class="stat-header">
      <span class="stat-label">Total ahorrado</span>
      <div class="stat-icon info">🏦</div>
    </div>
    <div class="stat-value" id="statAhorros">$ —</div>
    <div class="stat-footer">Cargando...</div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <span class="card-title">🚀 Dashboard en construcción</span>
  </div>
  <div class="card-body">
    <div class="empty-state">
      <div class="empty-icon">🏗️</div>
      <p>Los gráficos y tablas del dashboard se completarán en la Fase 4.</p>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';
?>
