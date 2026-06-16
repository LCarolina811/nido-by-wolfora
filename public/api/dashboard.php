<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/config/app.php';
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../src/helpers/response_helper.php';
require_once __DIR__ . '/../../src/helpers/date_helper.php';

session_start_safe();

if (!is_logged_in()) {
    json_error('No autorizado.', 401);
}

$action = $_GET['action'] ?? 'resumen';

match ($action) {
    'resumen'      => get_resumen(),
    'gastos_cat'   => get_gastos_por_categoria(),
    'evolucion'    => get_evolucion_mensual(),
    'vencimientos' => get_proximos_vencimientos(),
    'recientes'    => get_recientes(),
    default        => json_error('Acción no reconocida.', 400),
};

// ── Helpers internos ─────────────────────────────────────────────

function periodo_actual(): array
{
    $stmt = db()->query('SELECT id, anio, mes FROM periodos WHERE activo = 1 LIMIT 1');
    return $stmt->fetch() ?: ['id' => 0, 'anio' => date('Y'), 'mes' => date('n')];
}

// ── KPIs principales ─────────────────────────────────────────────

function get_resumen(): never
{
    $p = periodo_actual();
    $pid = $p['id'];
    $db = db();

    // Ingresos del mes
    $stmt = $db->prepare('SELECT COALESCE(SUM(valor), 0) AS total FROM ingresos WHERE periodo_id = :pid');
    $stmt->execute([':pid' => $pid]);
    $ingresos = (float) $stmt->fetchColumn();

    // Gastos totales del mes
    $stmt = $db->prepare('SELECT COALESCE(SUM(valor), 0) AS total FROM gastos WHERE periodo_id = :pid');
    $stmt->execute([':pid' => $pid]);
    $gastos_total = (float) $stmt->fetchColumn();

    // Gastos pagados
    $stmt = $db->prepare("SELECT COALESCE(SUM(valor), 0) AS total FROM gastos WHERE periodo_id = :pid AND estado = 'pagado'");
    $stmt->execute([':pid' => $pid]);
    $gastos_pagados = (float) $stmt->fetchColumn();

    // Gastos pendientes
    $stmt = $db->prepare("SELECT COALESCE(SUM(valor), 0) AS total FROM gastos WHERE periodo_id = :pid AND estado = 'pendiente'");
    $stmt->execute([':pid' => $pid]);
    $gastos_pendientes = (float) $stmt->fetchColumn();

    // Total ahorrado (suma de todos los ahorros activos)
    $stmt = $db->query("SELECT COALESCE(SUM(monto_acumulado), 0) AS total FROM ahorros WHERE activo = 1");
    $total_ahorrado = (float) $stmt->fetchColumn();

    // Presupuesto asignado vs gastado en el mes
    $stmt = $db->prepare('SELECT COALESCE(SUM(monto), 0) AS total FROM presupuestos WHERE periodo_id = :pid');
    $stmt->execute([':pid' => $pid]);
    $presupuesto_total = (float) $stmt->fetchColumn();

    $presupuesto_pct = $presupuesto_total > 0
        ? round(($gastos_total / $presupuesto_total) * 100, 1)
        : 0;

    // Próximos vencimientos (pendientes con fecha <= 5 días)
    $stmt = $db->prepare(
        "SELECT COUNT(*) AS total
           FROM gastos
          WHERE periodo_id = :pid
            AND estado = 'pendiente'
            AND fecha_pago BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 5 DAY)"
    );
    $stmt->execute([':pid' => $pid]);
    $proximos_count = (int) $stmt->fetchColumn();

    // Vencidos (pendientes con fecha < hoy)
    $stmt = $db->prepare(
        "SELECT COUNT(*) AS total
           FROM gastos
          WHERE periodo_id = :pid
            AND estado = 'pendiente'
            AND fecha_pago < CURDATE()"
    );
    $stmt->execute([':pid' => $pid]);
    $vencidos_count = (int) $stmt->fetchColumn();

    json_success([
        'periodo'           => nombre_mes((int)$p['mes']) . ' ' . $p['anio'],
        'ingresos'          => $ingresos,
        'gastos_total'      => $gastos_total,
        'gastos_pagados'    => $gastos_pagados,
        'gastos_pendientes' => $gastos_pendientes,
        'balance'           => $ingresos - $gastos_total,
        'total_ahorrado'    => $total_ahorrado,
        'presupuesto_total' => $presupuesto_total,
        'presupuesto_pct'   => $presupuesto_pct,
        'proximos_count'    => $proximos_count,
        'vencidos_count'    => $vencidos_count,
    ]);
}

// ── Gastos por categoría (para doughnut chart) ───────────────────

function get_gastos_por_categoria(): never
{
    $p   = periodo_actual();
    $stmt = db()->prepare(
        "SELECT c.nombre, c.color, COALESCE(SUM(g.valor), 0) AS total
           FROM gastos g
           JOIN categorias c ON g.categoria_id = c.id
          WHERE g.periodo_id = :pid
          GROUP BY c.id, c.nombre, c.color
         HAVING total > 0
          ORDER BY total DESC
          LIMIT 8"
    );
    $stmt->execute([':pid' => $p['id']]);
    $rows = $stmt->fetchAll();

    json_success([
        'labels' => array_column($rows, 'nombre'),
        'data'   => array_map(fn($r) => (float)$r['total'], $rows),
        'colors' => array_column($rows, 'color'),
    ]);
}

// ── Evolución ingresos vs gastos (últimos 6 meses) ───────────────

function get_evolucion_mensual(): never
{
    $db = db();

    $stmt = $db->query(
        "SELECT p.anio, p.mes,
                COALESCE(SUM(g.valor), 0)  AS gastos,
                COALESCE(
                    (SELECT SUM(i.valor)
                       FROM ingresos i
                      WHERE i.periodo_id = p.id), 0
                ) AS ingresos
           FROM periodos p
           LEFT JOIN gastos g ON g.periodo_id = p.id
          GROUP BY p.id, p.anio, p.mes
          ORDER BY p.anio DESC, p.mes DESC
          LIMIT 6"
    );
    $rows = array_reverse($stmt->fetchAll());

    $labels   = [];
    $gastos   = [];
    $ingresos = [];

    foreach ($rows as $r) {
        $labels[]   = substr(nombre_mes((int)$r['mes']), 0, 3) . ' ' . $r['anio'];
        $gastos[]   = (float) $r['gastos'];
        $ingresos[] = (float) $r['ingresos'];
    }

    json_success(compact('labels', 'gastos', 'ingresos'));
}

// ── Próximos vencimientos ────────────────────────────────────────

function get_proximos_vencimientos(): never
{
    $p    = periodo_actual();
    $stmt = db()->prepare(
        "SELECT g.concepto, g.valor, g.fecha_pago, g.responsable, g.estado,
                c.nombre AS categoria, c.color AS cat_color, c.icono AS cat_icono,
                DATEDIFF(g.fecha_pago, CURDATE()) AS dias_restantes
           FROM gastos g
           JOIN categorias c ON g.categoria_id = c.id
          WHERE g.periodo_id = :pid
            AND g.estado = 'pendiente'
          ORDER BY g.fecha_pago ASC
          LIMIT 10"
    );
    $stmt->execute([':pid' => $p['id']]);
    json_success($stmt->fetchAll());
}

// ── Últimas transacciones ────────────────────────────────────────

function get_recientes(): never
{
    $p    = periodo_actual();
    $stmt = db()->prepare(
        "SELECT g.concepto, g.valor, g.fecha_pago, g.responsable, g.estado, g.tipo,
                c.nombre AS categoria, c.color AS cat_color, c.icono AS cat_icono
           FROM gastos g
           JOIN categorias c ON g.categoria_id = c.id
          WHERE g.periodo_id = :pid
          ORDER BY g.created_at DESC
          LIMIT 8"
    );
    $stmt->execute([':pid' => $p['id']]);
    $gastos = $stmt->fetchAll();

    $stmt2 = db()->prepare(
        "SELECT i.concepto, i.valor, i.fecha, i.tipo,
                u.nombre AS usuario,
                c.nombre AS categoria, c.color AS cat_color, c.icono AS cat_icono
           FROM ingresos i
           JOIN categorias c ON i.categoria_id = c.id
           JOIN usuarios   u ON i.usuario_id   = u.id
          WHERE i.periodo_id = :pid
          ORDER BY i.created_at DESC
          LIMIT 5"
    );
    $stmt2->execute([':pid' => $p['id']]);
    $ingresos = $stmt2->fetchAll();

    json_success(compact('gastos', 'ingresos'));
}
