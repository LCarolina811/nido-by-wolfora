/**
 * shared.js — Utilidades globales reutilizables en todas las vistas.
 * Requiere que window.APP_URL esté definido antes de cargar este archivo.
 */

// ── Formato de moneda COP ──────────────────────────────────────

/** Formatea un número a "$ 1.500.000" (sin decimales, punto como separador de miles). */
function formatCOP(value) {
  const n = Number(value) || 0;
  return '$ ' + Math.round(n).toLocaleString('es-CO', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  });
}

/** Formatea un número plano a "1.500.000" (sin símbolo $), para inputs. */
function formatNumberCOP(value) {
  const digits = String(value).replace(/\D/g, '');
  if (!digits) return '';
  return Number(digits).toLocaleString('es-CO', { maximumFractionDigits: 0 });
}

/** Quita los puntos de un string formateado y devuelve el número plano. */
function parseNumberCOP(formatted) {
  const digits = String(formatted).replace(/\D/g, '');
  return digits ? parseInt(digits, 10) : 0;
}

/**
 * Convierte un <input> de texto en un campo de moneda que se formatea
 * mientras el usuario escribe, manteniendo el cursor en su posición relativa.
 * El valor numérico real se recupera con parseNumberCOP(input.value).
 */
function attachMoneyInput(input) {
  if (!input || input.dataset.moneyBound) return;
  input.dataset.moneyBound = '1';
  input.classList.add('money-input');
  input.setAttribute('inputmode', 'numeric');
  input.setAttribute('autocomplete', 'off');

  input.addEventListener('input', () => {
    const cursorFromEnd = input.value.length - input.selectionStart;
    const raw = parseNumberCOP(input.value);
    input.value = raw ? formatNumberCOP(raw) : '';
    const pos = Math.max(0, input.value.length - cursorFromEnd);
    input.setSelectionRange(pos, pos);
  });
}

/** Aplica attachMoneyInput a todos los inputs que coincidan con el selector. */
function attachMoneyInputsAll(selector) {
  document.querySelectorAll(selector).forEach(attachMoneyInput);
}

// ── Toasts ──────────────────────────────────────────────────────

function showToast(message, type = 'success') {
  const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
  const container = document.getElementById('toastContainer');
  if (!container) return;
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.innerHTML = `<span>${icons[type] ?? 'ℹ️'}</span><span>${message}</span>`;
  container.appendChild(toast);
  setTimeout(() => toast.remove(), 4200);
}

// ── Escape / formato de fechas (compartido entre módulos) ───────

function escHtml(s) {
  return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/'/g, '&#39;');
}

function fmtDate(dateStr) {
  const d = new Date(dateStr + 'T00:00:00');
  return d.toLocaleDateString('es-CO', { day: '2-digit', month: 'short', year: 'numeric' });
}

// ── Layout: sidebar móvil + logout ───────────────────────────────

function initLayout() {
  const sidebar        = document.getElementById('sidebar');
  const sidebarOverlay = document.getElementById('sidebarOverlay');
  const sidebarToggle  = document.getElementById('sidebarToggle');
  const btnLogout      = document.getElementById('btnLogout');

  const openSidebar  = () => { sidebar.classList.add('open');    sidebarOverlay.classList.add('visible'); };
  const closeSidebar = () => { sidebar.classList.remove('open'); sidebarOverlay.classList.remove('visible'); };

  sidebarToggle?.addEventListener('click', openSidebar);
  sidebarOverlay?.addEventListener('click', closeSidebar);

  btnLogout?.addEventListener('click', async () => {
    await fetch(window.APP_URL + '/api/auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'logout' }),
    });
    window.location.href = window.APP_URL + '/views/login.php';
  });
}

document.addEventListener('DOMContentLoaded', initLayout);
