<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/config/app.php';
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../src/helpers/response_helper.php';
require_once __DIR__ . '/../../src/helpers/date_helper.php';

session_start_safe();
if (!is_logged_in()) json_error('No autorizado.', 401);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

match (true) {
    $method === 'GET'    && $action === 'listar'     => listar_gastos(),
    $method === 'GET'    && $action === 'categorias' => listar_categorias(),
    $method === 'GET'    && $action === 'periodos'   => listar_periodos(),
    $method === 'GET'    && $action === 'resumen'    => resumen_gastos(),
    $method === 'POST'   && $action === 'crear'      => crear_gasto(),
    $method === 'POST'   && $action === 'editar'     => editar_gasto(),
    $method === 'POST'   && $action === 'estado'     => cambiar_estado(),
    $method === 'DELETE' && $action === 'eliminar'   => eliminar_gasto(),
    default => json_error('Acción no válida.', 400),
};

// ── Helpers ──────────────────────────────────────────────────────

function filtros_sql(): array
{
    $where  = ['1=1'];
    $params = [];

    if (!empty($_GET['periodo_id'])) {
        $where[]  = 'g.periodo_id = :periodo_id';
        $params[':periodo_id'] = (int) $_GET['periodo_id'];
    } else {
        $where[]  = 'g.periodo_id = (SELECT id FROM periodos WHERE activo = 1 LIMIT 1)';
    }

    if (!empty($_GET['categoria_id'])) {
        $where[]  = 'g.categoria_id = :categoria_id';
        $params[':categoria_id'] = (int) $_GET['categoria_id'];
    }

    if (!empty($_GET['responsable']) && in_array($_GET['responsable'], ['carolina','javier','compartido'])) {
        // Vista personal: muestra gastos propios + compartidos
        if ($_GET['responsable'] !== 'compartido') {
            $where[]  = "(g.responsable = :responsable OR g.responsable = 'compartido')";
            $params[':responsable'] = $_GET['responsable'];
        } else {
            $where[]  = "g.responsable = 'compartido'";
        }
    }

    if (!empty($_GET['tipo']) && in_array($_GET['tipo'], ['fijo','cuotas','variable'])) {
        $where[]  = 'g.tipo = :tipo';
        $params[':tipo'] = $_GET['tipo'];
    }

    if (!empty($_GET['estado']) && in_array($_GET['estado'], ['pendiente','pagado'])) {
        $where[]  = 'g.estado = :estado';
        $params[':estado'] = $_GET['estado'];
    }

    if (!empty($_GET['quincena']) && in_array($_GET['quincena'], ['primera','segunda'])) {
        $where[]  = 'g.quincena = :quincena';
        $params[':quincena'] = $_GET['quincena'];
    }

    return [implode(' AND ', $where), $params];
}

// ── Listar gastos ────────────────────────────────────────────────

function listar_gastos(): never
{
    [$where, $params] = filtros_sql();

    $sql = "SELECT
                g.id, g.concepto, g.valor, g.responsable, g.tipo,
                g.estado, g.fecha_pago, g.quincena, g.notas,
                g.gasto_padre_id, g.periodo_id,
                c.nombre AS categoria, c.color AS cat_color, c.icono AS cat_icono,
                gc.cuota_numero, gc.total_cuotas, gc.valor_cuota,
                DATEDIFF(g.fecha_pago, CURDATE()) AS dias_venc
            FROM gastos g
            JOIN categorias c ON g.categoria_id = c.id
            LEFT JOIN gastos_cuotas gc ON gc.gasto_id = g.id
            WHERE $where
            ORDER BY g.fecha_pago ASC, g.id ASC";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    json_success($stmt->fetchAll());
}

// ── Resumen rápido del período filtrado ──────────────────────────

function resumen_gastos(): never
{
    [$where, $params] = filtros_sql();

    $sql = "SELECT
                COALESCE(SUM(g.valor), 0)                                          AS total,
                COALESCE(SUM(CASE WHEN g.estado='pagado'    THEN g.valor ELSE 0 END), 0) AS pagados,
                COALESCE(SUM(CASE WHEN g.estado='pendiente' THEN g.valor ELSE 0 END), 0) AS pendientes,
                COUNT(*)                                                            AS cantidad,
                COUNT(CASE WHEN g.estado='pagado'    THEN 1 END)                   AS cnt_pagados,
                COUNT(CASE WHEN g.estado='pendiente' THEN 1 END)                   AS cnt_pendientes
            FROM gastos g
            JOIN categorias c ON g.categoria_id = c.id
            WHERE $where";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    json_success($stmt->fetch());
}

// ── Listar categorías de gastos ──────────────────────────────────

function listar_categorias(): never
{
    $stmt = db()->query("SELECT id, nombre, icono, color FROM categorias WHERE tipo='gasto' AND activo=1 ORDER BY nombre");
    json_success($stmt->fetchAll());
}

// ── Listar períodos disponibles ──────────────────────────────────

function listar_periodos(): never
{
    $stmt = db()->query('SELECT id, anio, mes, activo FROM periodos ORDER BY anio DESC, mes DESC');
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['label'] = nombre_mes((int)$r['mes']) . ' ' . $r['anio'];
    }
    json_success($rows);
}

// ── Crear gasto ──────────────────────────────────────────────────

function crear_gasto(): never
{
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    validar_campos($d);

    $quincena = calcular_quincena($d['fecha_pago']);
    $db       = db();

    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            "INSERT INTO gastos
                (concepto, categoria_id, valor, responsable, tipo, estado,
                 fecha_pago, quincena, periodo_id, usuario_creador_id, gasto_padre_id, notas)
             VALUES
                (:concepto, :categoria_id, :valor, :responsable, :tipo, :estado,
                 :fecha_pago, :quincena, :periodo_id, :usuario_id, :padre_id, :notas)"
        );

        $stmt->execute([
            ':concepto'     => trim($d['concepto']),
            ':categoria_id' => (int) $d['categoria_id'],
            ':valor'        => (float) $d['valor'],
            ':responsable'  => $d['responsable'],
            ':tipo'         => $d['tipo'],
            ':estado'       => $d['estado'] ?? 'pendiente',
            ':fecha_pago'   => $d['fecha_pago'],
            ':quincena'     => $quincena,
            ':periodo_id'   => (int) $d['periodo_id'],
            ':usuario_id'   => current_user_id(),
            ':padre_id'     => null,
            ':notas'        => trim($d['notas'] ?? ''),
        ]);

        $gastoId = (int) $db->lastInsertId();

        // Si es fijo: se marca a sí mismo como padre para replicación futura
        if ($d['tipo'] === 'fijo') {
            $db->prepare('UPDATE gastos SET gasto_padre_id = id WHERE id = :id')
               ->execute([':id' => $gastoId]);
        }

        // Si es por cuotas: registrar en gastos_cuotas
        if ($d['tipo'] === 'cuotas') {
            $totalCuotas = max(1, (int)($d['total_cuotas'] ?? 1));
            $db->prepare(
                "INSERT INTO gastos_cuotas
                    (gasto_id, gasto_origen_id, cuota_numero, total_cuotas, valor_cuota)
                 VALUES (:gasto_id, :origen_id, 1, :total, :valor)"
            )->execute([
                ':gasto_id'  => $gastoId,
                ':origen_id' => $gastoId,
                ':total'     => $totalCuotas,
                ':valor'     => (float) $d['valor'],
            ]);
        }

        $db->commit();
        json_success(['id' => $gastoId], 'Gasto creado correctamente.', 201);

    } catch (Throwable $e) {
        $db->rollBack();
        json_error('Error al crear el gasto: ' . $e->getMessage(), 500);
    }
}

// ── Editar gasto ─────────────────────────────────────────────────

function editar_gasto(): never
{
    $d  = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($d['id'] ?? 0);
    if (!$id) json_error('ID inválido.');

    validar_campos($d);

    $quincena = calcular_quincena($d['fecha_pago']);

    $stmt = db()->prepare(
        "UPDATE gastos SET
            concepto     = :concepto,
            categoria_id = :categoria_id,
            valor        = :valor,
            responsable  = :responsable,
            tipo         = :tipo,
            estado       = :estado,
            fecha_pago   = :fecha_pago,
            quincena     = :quincena,
            notas        = :notas
         WHERE id = :id"
    );

    $stmt->execute([
        ':concepto'     => trim($d['concepto']),
        ':categoria_id' => (int) $d['categoria_id'],
        ':valor'        => (float) $d['valor'],
        ':responsable'  => $d['responsable'],
        ':tipo'         => $d['tipo'],
        ':estado'       => $d['estado'],
        ':fecha_pago'   => $d['fecha_pago'],
        ':quincena'     => $quincena,
        ':notas'        => trim($d['notas'] ?? ''),
        ':id'           => $id,
    ]);

    // Actualizar valor de cuota si aplica
    if ($d['tipo'] === 'cuotas') {
        $totalCuotas = max(1, (int)($d['total_cuotas'] ?? 1));
        $check = db()->prepare('SELECT id FROM gastos_cuotas WHERE gasto_id = :id');
        $check->execute([':id' => $id]);
        if ($check->fetch()) {
            db()->prepare(
                'UPDATE gastos_cuotas SET total_cuotas = :total, valor_cuota = :valor WHERE gasto_id = :id'
            )->execute([':total' => $totalCuotas, ':valor' => (float)$d['valor'], ':id' => $id]);
        }
    }

    json_success(null, 'Gasto actualizado correctamente.');
}

// ── Cambiar estado (pagado / pendiente) ──────────────────────────

function cambiar_estado(): never
{
    $d      = json_decode(file_get_contents('php://input'), true) ?? [];
    $id     = (int)($d['id'] ?? 0);
    $estado = $d['estado'] ?? '';

    if (!$id || !in_array($estado, ['pendiente','pagado'])) {
        json_error('Datos inválidos.');
    }

    db()->prepare('UPDATE gastos SET estado = :estado WHERE id = :id')
       ->execute([':estado' => $estado, ':id' => $id]);

    json_success(null, 'Estado actualizado.');
}

// ── Eliminar gasto ───────────────────────────────────────────────

function eliminar_gasto(): never
{
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_error('ID inválido.');

    // Verificar que no tenga cuotas replicadas en otros meses
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM gastos_cuotas WHERE gasto_origen_id = :id AND gasto_id != :id'
    );
    $stmt->execute([':id' => $id]);
    if ((int)$stmt->fetchColumn() > 0) {
        json_error('Este gasto tiene cuotas replicadas en otros meses. Elimina las cuotas individualmente.');
    }

    db()->prepare('DELETE FROM gastos WHERE id = :id')->execute([':id' => $id]);
    json_success(null, 'Gasto eliminado.');
}

// ── Validación común ─────────────────────────────────────────────

function validar_campos(array $d): void
{
    $errores = [];

    if (empty(trim($d['concepto'] ?? '')))         $errores[] = 'El concepto es obligatorio.';
    if (empty($d['categoria_id']))                  $errores[] = 'La categoría es obligatoria.';
    if (!isset($d['valor']) || $d['valor'] <= 0)   $errores[] = 'El valor debe ser mayor a cero.';
    if (!in_array($d['responsable'] ?? '', ['carolina','javier','compartido']))
                                                    $errores[] = 'Responsable inválido.';
    if (!in_array($d['tipo'] ?? '', ['fijo','cuotas','variable']))
                                                    $errores[] = 'Tipo de gasto inválido.';
    if (empty($d['fecha_pago']))                    $errores[] = 'La fecha de pago es obligatoria.';
    if (empty($d['periodo_id']))                    $errores[] = 'El período es obligatorio.';
    if ($d['tipo'] === 'cuotas' && (int)($d['total_cuotas'] ?? 0) < 2)
                                                    $errores[] = 'Las cuotas deben ser al menos 2.';

    if ($errores) json_error('Datos inválidos.', 422, $errores);
}
