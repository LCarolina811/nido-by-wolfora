<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/config/app.php';
require_once __DIR__ . '/../../src/config/database.php';

$pageTitle    = 'Dashboard';
$pageSubtitle = 'Resumen financiero del mes';
$activeNav    = 'dashboard';

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

$extraCss = '<style>
/* ── Dashboard extras ───────────────────────────── */
.skeleton { color: var(--border) !important; }

.progress-bar-wrap {
  width: 100%;
  height: 5px;
  background: var(--border);
  border-radius: 10px;
  overflow: hidden;
  margin-top: 0.5rem;
}
.progress-bar {
  height: 100%;
  background: linear-gradient(90deg, var(--primary), #a78bfa);
  border-radius: 10px;
  transition: width 0.8s ease;
}
.progress-bar.over { background: linear-gradient(90deg, var(--warning), var(--danger)); }

/* Leyenda del donut */
.chart-legend {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
  font-size: 0.8125rem;
  max-height: 160px;
  overflow-y: auto;
}
.chart-legend li {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  justify-content: space-between;
}
.chart-legend .leg-dot {
  width: 10px; height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}
.chart-legend .leg-name { flex: 1; color: var(--text-secondary); }
.chart-legend .leg-val  { font-weight: 700; color: var(--text-primary); }

/* Lista movimientos */
.movimientos-list { max-height: 340px; overflow-y: auto; }
.movimientos-list li {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1.25rem;
  border-bottom: 1px solid var(--border);
  transition: background var(--transition);
}
.movimientos-list li:last-child { border-bottom: none; }
.movimientos-list li:hover { background: var(--bg-card-hover); }
.mov-icon {
  width: 36px; height: 36px;
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1rem;
  flex-shrink: 0;
}
.mov-info { flex: 1; min-width: 0; }
.mov-concepto {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--text-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.mov-meta { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.1rem; }
.mov-valor {
  font-size: 0.9rem;
  font-weight: 700;
  white-space: nowrap;
}
.mov-valor.gasto   { color: var(--danger); }
.mov-valor.ingreso { color: var(--success); }

/* Tabla vencimientos */
.venc-chip {
  display: inline-block;
  padding: 0.2rem 0.6rem;
  border-radius: 20px;
  font-size: 0.725rem;
  font-weight: 700;
}

/* Responsive grids del dashboard */
@media (max-width: 900px) {
  .dashboard-row { grid-template-columns: 1fr !important; }
}
</style>';

$extraJs = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// ── Utilidades ─────────────────────────────────────────
const API = APP_URL + \'/api/dashboard.php\';

function fmt(v)  { return formatCOP(v); }
function pct(v)  { return Number(v).toFixed(1) + \'%\'; }

function diasLabel(dias) {
  dias = parseInt(dias);
  if (dias < 0)  return { text: \'Vencido\',    cls: \'badge badge-danger\' };
  if (dias === 0) return { text: \'Hoy\',        cls: \'badge badge-danger\' };
  if (dias <= 5)  return { text: \'En \'+dias+\'d\', cls: \'badge badge-warning\' };
  return            { text: \'En \'+dias+\'d\', cls: \'badge badge-neutral\' };
}

function responsableLabel(r) {
  const map = { carolina: \'👩 Carolina\', javier: \'👨 Javier\', compartido: \'👫 Compartido\' };
  return map[r] ?? r;
}

// ── Cargar KPIs ────────────────────────────────────────
async function cargarResumen() {
  const res  = await fetch(API + \'?action=resumen\');
  const json = await res.json();
  if (!json.success) return;
  const d = json.data;

  document.getElementById(\'statIngresos\').textContent        = fmt(d.ingresos);
  document.getElementById(\'statIngresosFooter\').textContent  = \'Período: \' + d.periodo;

  document.getElementById(\'statGastos\').textContent          = fmt(d.gastos_total);
  document.getElementById(\'statGastosFooter\').textContent    = fmt(d.gastos_pagados) + \' pagados\';

  const balEl = document.getElementById(\'statBalance\');
  balEl.textContent = fmt(d.balance);
  balEl.classList.toggle(\'text-danger\',  d.balance < 0);
  balEl.classList.toggle(\'text-success\', d.balance >= 0);
  document.getElementById(\'statBalanceFooter\').textContent = d.balance >= 0 ? \'Positivo ✅\' : \'Déficit ⚠️\';

  document.getElementById(\'statPendientes\').textContent      = fmt(d.gastos_pendientes);
  document.getElementById(\'statPendientesFooter\').textContent= \'Sin pagar este mes\';

  document.getElementById(\'statPagados\').textContent         = fmt(d.gastos_pagados);
  document.getElementById(\'statPagadosFooter\').textContent   = \'Gastos liquidados\';

  document.getElementById(\'statAhorros\').textContent         = fmt(d.total_ahorrado);
  document.getElementById(\'statAhorrosFooter\').textContent   = \'En todas las metas\';

  document.getElementById(\'statPresupuesto\').textContent     = pct(d.presupuesto_pct);
  const bar = document.getElementById(\'progressPresupuesto\');
  bar.style.width = Math.min(d.presupuesto_pct, 100) + \'%\';
  if (d.presupuesto_pct > 100) bar.classList.add(\'over\');

  document.getElementById(\'statVencimientos\').textContent      = d.proximos_count + \' / \' + d.vencidos_count;
  document.getElementById(\'statVencimientosFooter\').textContent = \'Por vencer (5d) / vencidos\';

  // Quitar skeleton
  document.querySelectorAll(\'.stat-value.skeleton\').forEach(el => el.classList.remove(\'skeleton\'));
}

// ── Gráfico: Gastos por categoría ─────────────────────
let chartCat = null;
async function cargarGraficoCategoria() {
  const res  = await fetch(API + \'?action=gastos_cat\');
  const json = await res.json();
  if (!json.success) return;
  const d = json.data;

  const total = d.data.reduce((a, b) => a + b, 0);
  document.getElementById(\'chartCatTotal\').textContent = fmt(total);

  if (chartCat) chartCat.destroy();
  const ctx = document.getElementById(\'chartCategorias\').getContext(\'2d\');
  chartCat = new Chart(ctx, {
    type: \'doughnut\',
    data: {
      labels: d.labels,
      datasets: [{
        data: d.data,
        backgroundColor: d.colors,
        borderWidth: 2,
        borderColor: \'#ffffff\',
        hoverBorderWidth: 3,
      }]
    },
    options: {
      cutout: \'72%\',
      plugins: { legend: { display: false }, tooltip: {
        callbacks: {
          label: ctx => \' \' + ctx.label + \': \' + fmt(ctx.raw)
        }
      }},
      animation: { animateRotate: true, duration: 800 },
    }
  });

  // Leyenda custom
  const leg = document.getElementById(\'legendCategorias\');
  leg.innerHTML = d.labels.map((label, i) => `
    <li>
      <span class="leg-dot" style="background:${d.colors[i]}"></span>
      <span class="leg-name">${label}</span>
      <span class="leg-val">${fmt(d.data[i])}</span>
    </li>
  `).join(\'\');

  if (total === 0) {
    leg.innerHTML = \'<li style="color:var(--text-muted);padding:.5rem">Sin gastos registrados</li>\';
  }
}

// ── Gráfico: Evolución mensual ─────────────────────────
let chartEv = null;
async function cargarGraficoEvolucion() {
  const res  = await fetch(API + \'?action=evolucion\');
  const json = await res.json();
  if (!json.success) return;
  const d = json.data;

  if (chartEv) chartEv.destroy();
  const ctx = document.getElementById(\'chartEvolucion\').getContext(\'2d\');
  chartEv = new Chart(ctx, {
    type: \'bar\',
    data: {
      labels: d.labels,
      datasets: [
        {
          label: \'Ingresos\',
          data: d.ingresos,
          backgroundColor: \'rgba(16,185,129,0.75)\',
          borderRadius: 6,
          borderSkipped: false,
        },
        {
          label: \'Gastos\',
          data: d.gastos,
          backgroundColor: \'rgba(239,68,68,0.75)\',
          borderRadius: 6,
          borderSkipped: false,
        }
      ]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { position: \'top\', labels: { usePointStyle: true, pointStyle: \'circle\', padding: 16, font: { size: 12 } } },
        tooltip: {
          callbacks: { label: ctx => \' \' + ctx.dataset.label + \': \' + fmt(ctx.raw) }
        }
      },
      scales: {
        x: { grid: { display: false }, ticks: { font: { size: 11 } } },
        y: {
          grid: { color: \'rgba(0,0,0,0.05)\' },
          ticks: {
            font: { size: 11 },
            callback: v => \'$ \' + (v >= 1000000 ? (v/1000000).toFixed(1)+\'M\' : (v/1000).toFixed(0)+\'K\')
          }
        }
      }
    }
  });
}

// ── Tabla: Próximos vencimientos ───────────────────────
async function cargarVencimientos() {
  const res  = await fetch(API + \'?action=vencimientos\');
  const json = await res.json();
  if (!json.success) return;
  const rows = json.data;

  const tbody = document.getElementById(\'bodyVencimientos\');
  if (!rows.length) {
    tbody.innerHTML = \'<tr><td colspan="5"><div class="empty-state"><div class="empty-icon">🎉</div><p>Sin vencimientos pendientes</p></div></td></tr>\';
    return;
  }

  tbody.innerHTML = rows.map(r => {
    const chip = diasLabel(r.dias_restantes);
    return `<tr>
      <td>
        <div style="font-weight:600;font-size:.875rem">${r.cat_icono} ${esc(r.concepto)}</div>
        <div style="font-size:.75rem;color:var(--text-muted)">${esc(r.categoria)}</div>
      </td>
      <td style="font-size:.8125rem">${responsableLabel(r.responsable)}</td>
      <td style="font-size:.8125rem;white-space:nowrap">${formatDate(r.fecha_pago)}</td>
      <td><span class="${chip.cls}">${chip.text}</span></td>
      <td class="text-right amount">${fmt(r.valor)}</td>
    </tr>`;
  }).join(\'\');
}

// ── Lista: Últimos movimientos ─────────────────────────
async function cargarRecientes() {
  const res  = await fetch(API + \'?action=recientes\');
  const json = await res.json();
  if (!json.success) return;
  const { gastos, ingresos } = json.data;

  const lista = document.getElementById(\'listaRecientes\');
  const items = [];

  gastos.forEach(g => {
    items.push({
      icono: g.cat_icono, color: g.cat_color,
      concepto: g.concepto, meta: g.categoria + \' · \' + responsableLabel(g.responsable),
      valor: \'-\' + fmt(g.valor), tipo: \'gasto\'
    });
  });

  ingresos.forEach(i => {
    items.push({
      icono: i.cat_icono, color: i.cat_color,
      concepto: i.concepto, meta: i.categoria + \' · \' + esc(i.usuario),
      valor: \'+\' + fmt(i.valor), tipo: \'ingreso\'
    });
  });

  if (!items.length) {
    lista.innerHTML = \'<li style="padding:2rem;text-align:center;color:var(--text-muted)"><div class="empty-icon">📭</div><p>Sin movimientos este mes</p></li>\';
    return;
  }

  lista.innerHTML = items.map(it => `
    <li>
      <div class="mov-icon" style="background:${it.color}22">${it.icono}</div>
      <div class="mov-info">
        <div class="mov-concepto">${esc(it.concepto)}</div>
        <div class="mov-meta">${it.meta}</div>
      </div>
      <div class="mov-valor ${it.tipo}">${it.valor}</div>
    </li>
  `).join(\'\');
}

// ── Utilidades DOM ─────────────────────────────────────
function esc(s) {
  return String(s).replace(/&/g,\'&amp;\').replace(/</g,\'&lt;\').replace(/>/g,\'&gt;\');
}

function formatDate(dateStr) {
  const d = new Date(dateStr + \'T00:00:00\');
  return d.toLocaleDateString(\'es-CO\', { day: \'2-digit\', month: \'short\', year: \'numeric\' });
}

// ── Inicializar todo ───────────────────────────────────
document.addEventListener(\'DOMContentLoaded\', () => {
  Promise.all([
    cargarResumen(),
    cargarGraficoCategoria(),
    cargarGraficoEvolucion(),
    cargarVencimientos(),
    cargarRecientes(),
  ]).catch(err => console.error(\'Dashboard error:\', err));
});
</script>';

require_once __DIR__ . '/layout.php';
?>
