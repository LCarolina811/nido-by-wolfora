/**
 * dashboard.js — Lógica del Dashboard principal.
 * Requiere shared.js cargado antes (formatCOP, escHtml, fmtDate, showToast).
 */

const API_D = window.APP_URL + '/api/dashboard.php';

function pct(v) { return Number(v).toFixed(1) + '%'; }

function diasLabel(dias) {
  dias = parseInt(dias, 10);
  if (dias < 0)   return { text: 'Vencido',      cls: 'badge badge-danger' };
  if (dias === 0) return { text: 'Hoy',          cls: 'badge badge-danger' };
  if (dias <= 5)  return { text: 'En ' + dias + 'd', cls: 'badge badge-warning' };
  return            { text: 'En ' + dias + 'd', cls: 'badge badge-neutral' };
}

function responsableLabel(r) {
  const map = { carolina: '👩 Carolina', javier: '👨 Javier', compartido: '👫 Compartido' };
  return map[r] ?? r;
}

// ── Cargar KPIs ──────────────────────────────────────────────────

async function cargarResumen() {
  const res  = await fetch(API_D + '?action=resumen');
  const json = await res.json();
  if (!json.success) return;
  const d = json.data;

  setText('statIngresos', formatCOP(d.ingresos));
  setText('statIngresosFooter', 'Período: ' + d.periodo);

  setText('statGastos', formatCOP(d.gastos_total));
  setText('statGastosFooter', formatCOP(d.gastos_pagados) + ' pagados');

  const balEl = document.getElementById('statBalance');
  balEl.textContent = formatCOP(d.balance);
  balEl.classList.toggle('text-danger', d.balance < 0);
  balEl.classList.toggle('text-success', d.balance >= 0);
  setText('statBalanceFooter', d.balance >= 0 ? 'Positivo ✅' : 'Déficit ⚠️');

  setText('statPendientes', formatCOP(d.gastos_pendientes));
  setText('statPendientesFooter', 'Sin pagar este mes');

  setText('statPagados', formatCOP(d.gastos_pagados));
  setText('statPagadosFooter', 'Gastos liquidados');

  setText('statAhorros', formatCOP(d.total_ahorrado));
  setText('statAhorrosFooter', 'En todas las metas');

  setText('statPresupuesto', pct(d.presupuesto_pct));
  const bar = document.getElementById('progressPresupuesto');
  bar.style.width = Math.min(d.presupuesto_pct, 100) + '%';
  if (d.presupuesto_pct > 100) bar.classList.add('over');

  setText('statVencimientos', d.proximos_count + ' / ' + d.vencidos_count);
  setText('statVencimientosFooter', 'Por vencer (5d) / vencidos');

  document.querySelectorAll('.stat-value.skeleton').forEach(el => el.classList.remove('skeleton'));
}

function setText(id, text) {
  const el = document.getElementById(id);
  if (el) el.textContent = text;
}

// ── Gráfico: Gastos por categoría ──────────────────────────────────

let chartCat = null;

async function cargarGraficoCategoria() {
  const res  = await fetch(API_D + '?action=gastos_cat');
  const json = await res.json();
  if (!json.success) return;
  const d = json.data;

  const total = d.data.reduce((a, b) => a + b, 0);
  setText('chartCatTotal', formatCOP(total));

  if (chartCat) chartCat.destroy();
  const ctx = document.getElementById('chartCategorias').getContext('2d');
  chartCat = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: d.labels,
      datasets: [{
        data: d.data,
        backgroundColor: d.colors,
        borderWidth: 2,
        borderColor: '#ffffff',
        hoverBorderWidth: 3,
      }],
    },
    options: {
      cutout: '72%',
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: ctx => ' ' + ctx.label + ': ' + formatCOP(ctx.raw) } },
      },
      animation: { animateRotate: true, duration: 800 },
    },
  });

  const leg = document.getElementById('legendCategorias');
  leg.innerHTML = d.labels.map((label, i) => `
    <li>
      <span class="leg-dot" style="background:${d.colors[i]}"></span>
      <span class="leg-name">${label}</span>
      <span class="leg-val">${formatCOP(d.data[i])}</span>
    </li>
  `).join('');

  if (total === 0) {
    leg.innerHTML = '<li style="color:var(--text-muted);padding:.5rem">Sin gastos registrados</li>';
  }
}

// ── Gráfico: Evolución mensual ──────────────────────────────────────

let chartEv = null;

async function cargarGraficoEvolucion() {
  const res  = await fetch(API_D + '?action=evolucion');
  const json = await res.json();
  if (!json.success) return;
  const d = json.data;

  if (chartEv) chartEv.destroy();
  const ctx = document.getElementById('chartEvolucion').getContext('2d');
  chartEv = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: d.labels,
      datasets: [
        { label: 'Ingresos', data: d.ingresos, backgroundColor: 'rgba(16,185,129,0.75)', borderRadius: 6, borderSkipped: false },
        { label: 'Gastos',   data: d.gastos,   backgroundColor: 'rgba(239,68,68,0.75)',  borderRadius: 6, borderSkipped: false },
      ],
    },
    options: {
      responsive: true,
      plugins: {
        legend: { position: 'top', labels: { usePointStyle: true, pointStyle: 'circle', padding: 16, font: { size: 12 } } },
        tooltip: { callbacks: { label: ctx => ' ' + ctx.dataset.label + ': ' + formatCOP(ctx.raw) } },
      },
      scales: {
        x: { grid: { display: false }, ticks: { font: { size: 11 } } },
        y: {
          grid: { color: 'rgba(0,0,0,0.05)' },
          ticks: {
            font: { size: 11 },
            callback: v => '$ ' + (v >= 1000000 ? (v / 1000000).toFixed(1) + 'M' : (v / 1000).toFixed(0) + 'K'),
          },
        },
      },
    },
  });
}

// ── Tabla: Próximos vencimientos ────────────────────────────────────

async function cargarVencimientos() {
  const res  = await fetch(API_D + '?action=vencimientos');
  const json = await res.json();
  if (!json.success) return;
  const rows = json.data;

  const tbody = document.getElementById('bodyVencimientos');
  if (!rows.length) {
    tbody.innerHTML = '<tr><td colspan="5"><div class="empty-state"><div class="empty-icon">🎉</div><p>Sin vencimientos pendientes</p></div></td></tr>';
    return;
  }

  tbody.innerHTML = rows.map(r => {
    const chip = diasLabel(r.dias_restantes);
    return `<tr>
      <td>
        <div style="font-weight:600;font-size:.875rem">${r.cat_icono} ${escHtml(r.concepto)}</div>
        <div style="font-size:.75rem;color:var(--text-muted)">${escHtml(r.categoria)}</div>
      </td>
      <td style="font-size:.8125rem">${responsableLabel(r.responsable)}</td>
      <td style="font-size:.8125rem;white-space:nowrap">${fmtDate(r.fecha_pago)}</td>
      <td><span class="${chip.cls}">${chip.text}</span></td>
      <td class="text-right amount">${formatCOP(r.valor)}</td>
    </tr>`;
  }).join('');
}

// ── Lista: Últimos movimientos ──────────────────────────────────────

async function cargarRecientes() {
  const res  = await fetch(API_D + '?action=recientes');
  const json = await res.json();
  if (!json.success) return;
  const { gastos, ingresos } = json.data;

  const lista = document.getElementById('listaRecientes');
  const items = [];

  gastos.forEach(g => {
    items.push({
      icono: g.cat_icono, color: g.cat_color,
      concepto: g.concepto, meta: g.categoria + ' · ' + responsableLabel(g.responsable),
      valor: '-' + formatCOP(g.valor), tipo: 'gasto',
    });
  });

  ingresos.forEach(i => {
    items.push({
      icono: i.cat_icono, color: i.cat_color,
      concepto: i.concepto, meta: i.categoria + ' · ' + escHtml(i.usuario),
      valor: '+' + formatCOP(i.valor), tipo: 'ingreso',
    });
  });

  if (!items.length) {
    lista.innerHTML = '<li style="padding:2rem;text-align:center;color:var(--text-muted)"><div class="empty-icon">📭</div><p>Sin movimientos este mes</p></li>';
    return;
  }

  lista.innerHTML = items.map(it => `
    <li>
      <div class="mov-icon" style="background:${it.color}22">${it.icono}</div>
      <div class="mov-info">
        <div class="mov-concepto">${escHtml(it.concepto)}</div>
        <div class="mov-meta">${it.meta}</div>
      </div>
      <div class="mov-valor ${it.tipo}">${it.valor}</div>
    </li>
  `).join('');
}

// ── Inicializar ──────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
  Promise.all([
    cargarResumen(),
    cargarGraficoCategoria(),
    cargarGraficoEvolucion(),
    cargarVencimientos(),
    cargarRecientes(),
  ]).catch(err => console.error('Dashboard error:', err));
});
