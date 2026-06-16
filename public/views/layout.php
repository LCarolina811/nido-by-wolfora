<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/config/app.php';
require_once __DIR__ . '/../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../src/helpers/date_helper.php';

require_login();

$pageTitle    = $pageTitle    ?? 'Dashboard';
$pageSubtitle = $pageSubtitle ?? '';
$activeNav    = $activeNav    ?? '';

$userName    = current_user_name();
$userColor   = current_user_color();
$userInitial = strtoupper(mb_substr($userName, 0, 1));

$periodoActivo = '';
try {
    $stmt = db()->query('SELECT anio, mes FROM periodos WHERE activo = 1 LIMIT 1');
    $p = $stmt->fetch();
    if ($p) {
        $periodoActivo = nombre_mes((int)$p['mes']) . ' ' . $p['anio'];
    }
} catch (PDOException) { }

$navItems = [
    ['icon' => '📊', 'label' => 'Dashboard',   'href' => url('views/dashboard.php'),   'key' => 'dashboard'],
    ['icon' => '💸', 'label' => 'Gastos',       'href' => url('views/gastos.php'),       'key' => 'gastos'],
    ['icon' => '💰', 'label' => 'Ingresos',     'href' => url('views/ingresos.php'),     'key' => 'ingresos'],
    ['icon' => '🎯', 'label' => 'Presupuestos', 'href' => url('views/presupuestos.php'), 'key' => 'presupuestos'],
    ['icon' => '🏦', 'label' => 'Ahorros',      'href' => url('views/ahorros.php'),      'key' => 'ahorros'],
    ['icon' => '📅', 'label' => 'Historial',    'href' => url('views/historial.php'),    'key' => 'historial'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($pageTitle) ?> — Nido</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>" />
  <?php if (!empty($extraCss)): ?>
    <?= $extraCss ?>
  <?php endif; ?>
</head>
<body>

<div class="app-layout" id="appLayout">

  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <!-- ── Sidebar ────────────────────────────────────────────── -->
  <aside class="sidebar" id="sidebar">

    <div class="sidebar-brand">
      <div class="brand-logo">🏠</div>
      <div>
        <div class="brand-name">Nido</div>
        <div class="brand-sub">by Wolfora</div>
      </div>
    </div>

    <nav class="sidebar-nav">
      <span class="nav-section-label">Principal</span>

      <?php foreach ($navItems as $item): ?>
        <div class="nav-item">
          <a href="<?= htmlspecialchars($item['href']) ?>"
             class="nav-link <?= $activeNav === $item['key'] ? 'active' : '' ?>">
            <span class="nav-icon"><?= $item['icon'] ?></span>
            <?= htmlspecialchars($item['label']) ?>
          </a>
        </div>
      <?php endforeach; ?>

      <span class="nav-section-label" style="margin-top:1rem">Vistas</span>

      <div class="nav-item">
        <a href="<?= url('views/gastos.php?vista=carolina') ?>"
           class="nav-link <?= $activeNav === 'carolina' ? 'active' : '' ?>">
          <span class="nav-icon">👩</span>
          Carolina
        </a>
      </div>
      <div class="nav-item">
        <a href="<?= url('views/gastos.php?vista=javier') ?>"
           class="nav-link <?= $activeNav === 'javier' ? 'active' : '' ?>">
          <span class="nav-icon">👨</span>
          Javier
        </a>
      </div>
    </nav>

    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="user-avatar" style="background: <?= htmlspecialchars($userColor) ?>">
          <?= htmlspecialchars($userInitial) ?>
        </div>
        <div class="user-info">
          <div class="user-name"><?= htmlspecialchars($userName) ?></div>
          <div class="user-role">Miembro</div>
        </div>
        <button class="btn-logout" id="btnLogout" title="Cerrar sesión">🚪</button>
      </div>
    </div>

  </aside>

  <!-- ── Topbar ──────────────────────────────────────────────── -->
  <header class="topbar">
    <div class="topbar-left">
      <button class="btn-sidebar-toggle" id="sidebarToggle" aria-label="Abrir menú">☰</button>
      <div class="page-title">
        <h2><?= htmlspecialchars($pageTitle) ?></h2>
        <?php if ($pageSubtitle): ?>
          <p><?= htmlspecialchars($pageSubtitle) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <div class="topbar-right">
      <?php if ($periodoActivo): ?>
        <div class="topbar-periodo">
          📅 <span><?= htmlspecialchars($periodoActivo) ?></span>
        </div>
      <?php endif; ?>
      <div class="topbar-avatar" style="background: <?= htmlspecialchars($userColor) ?>"
           title="<?= htmlspecialchars($userName) ?>">
        <?= htmlspecialchars($userInitial) ?>
      </div>
    </div>
  </header>

  <!-- ── Contenido ───────────────────────────────────────────── -->
  <main class="main-content" id="mainContent">
    <?= $content ?? '' ?>
  </main>

</div>

<div class="toast-container" id="toastContainer"></div>

<script>
  // URL base disponible globalmente en JS
  const APP_URL = <?= json_encode(APP_URL) ?>;

  // Sidebar toggle (móvil)
  const sidebar        = document.getElementById('sidebar');
  const sidebarOverlay = document.getElementById('sidebarOverlay');
  const sidebarToggle  = document.getElementById('sidebarToggle');

  function openSidebar()  { sidebar.classList.add('open');    sidebarOverlay.classList.add('visible'); }
  function closeSidebar() { sidebar.classList.remove('open'); sidebarOverlay.classList.remove('visible'); }

  sidebarToggle.addEventListener('click', openSidebar);
  sidebarOverlay.addEventListener('click', closeSidebar);

  // Logout
  document.getElementById('btnLogout').addEventListener('click', async () => {
    await fetch(APP_URL + '/api/auth.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ action: 'logout' }),
    });
    window.location.href = APP_URL + '/views/login.php';
  });

  // Toast utility (global)
  function showToast(message, type = 'success') {
    const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `<span>${icons[type] ?? 'ℹ️'}</span><span>${message}</span>`;
    document.getElementById('toastContainer').appendChild(toast);
    setTimeout(() => toast.remove(), 4200);
  }

  // Formato de moneda (global)
  function formatCOP(value) {
    return '$ ' + Number(value).toLocaleString('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
  }
</script>

<?php if (!empty($extraJs)): ?>
  <?= $extraJs ?>
<?php endif; ?>

</body>
</html>
