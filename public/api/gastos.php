<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/config/app.php';
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../src/helpers/response_helper.php';
require_once __DIR__ . '/../../src/helpers/date_helper.php';

session_start_safe();
if (!is_logged_in()) json_error('No autorizado.', 401);

$nidoId = current_nido_id();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

match (true) {
    $method === 'GET'    && $action === 'listar'     => listar_gastos($nidoId),
    $method === 'GET'    && $action === 'categorias' => listar_categorias($nidoId),
    $method === 'GET'    && $action === 'periodos'   => listar_periodos($nidoId),
    $method === 'GET'    && $action === 'miembros'   => listar_miembros($nidoId),
    $method === 'GET'    && $action === 'resumen'    => resumen_gastos($nidoId),
    $method === 'POST'   && $action === 'crear'      => crear_gasto($nidoId),
    $method === 'POST'   && $action === 'editar'     => editar_gasto($nidoId),
    $method === 'POST'   && $action === 'estado'     => cambiar_estado($nidoId),
    $method === 'DELETE' && $action === 'eliminar'   => eliminar_gasto($nidoId),
    default => json_error('Acción no válida.', 400),
};

// ── Helpers ──────────────────────────────────────────────────────

/**
 * Vista personal: si ?vista_usuario=<id> está presente, filtra solo
 * los gastos donde ese usuario tiene una participación, y el "valor"
 * mostrado será su porción, no el total del gasto.
 */
function vista_usuario_id(): ?int
{
    $v = $_GET['vista_usuario'] ?? null;
    return $v ? (int) $v : null;
}

function filtros_sql(int $nidoId): array
{
    $where  = ['g.nido_id = :nido_id', 'g.deleted_at IS NULL'];
    $params = [':nido_id' => $nidoId];

    if (!empty($_GET['periodo_id'])) {
        $where[]  = 'g.periodo_id = :periodo_id';
        $params[':periodo_id'] = (int) $_GET['periodo_id'];
    } else {
        $where[]  = 'g.periodo_id = (SELECT id FROM periodos WHERE nido_id = :nido_id2 AND activo = 1 LIMIT 1)';
        $params[':nido_id2'] = $nidoId;
    }

    if (!empty($_GET['categoria_id'])) {
        $where[]  = 'g.categoria_id = :categoria_id';
        $params[':categoria_id'] = (int) $_GET['categoria_id'];
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

    $vistaUsuario = vista_usuario_id();
    if ($vistaUsuario) {
        $where[] = 'EXISTS (SELECT 1 FROM gasto_participaciones gp2 WHERE gp2.gasto_id = g.id AND gp2.usuario_id = :vista_usuario)';
        $params[':vista_usuario'] = $vistaUsuario;
    }

    return [implode(' AND ', $where), $params];
}

// ── Listar gastos ────────────────────────────────────────────────

function listar_gastos(int $nidoId): never
{
    [$where, $params] = filtros_sql($nidoId);
    $vistaUsuario = vista_usuario_id();

    $sql = "SELECT
                g.id, g.concepto, g.valor, g.tipo,
                g.estado, g.fecha_pago, g.quincena, g.notas,
                g.gasto_padre_id, g.periodo_id, g.usuario_creador_id,
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
    $rows = $stmt->fetchAll();

    if (!$rows) json_success([]);

    // Adjuntar participaciones de cada gasto
    $ids = array_column($rows, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $pStmt = db()->prepare(
        "SELECT gp.gasto_id, gp.usuario_id, gp.monto, u.nombre, u.avatar_color
           FROM gasto_participaciones gp
           JOIN usuarios u ON gp.usuario_id = u.id
          WHERE gp.gasto_id IN ($placeholders)"
    );
    $pStmt->execute($ids);
    $participaciones = [];
    foreach ($pStmt->fetchAll() as $p) {
        $participaciones[$p['gasto_id']][] = $p;
    }

    foreach ($rows as &$g) {
        $parts = $participaciones[$g['id']] ?? [];
        $g['participantes'] = array_map(fn($p) => [
            'usuario_id' => (int) $p['usuario_id'],
            'nombre'     => $p['nombre'],
            'color'      => $p['avatar_color'],
            'monto'      => (float) $p['monto'],
        ], $parts);

        // En vista personal, el valor mostrado es la porción del usuario
        if ($vistaUsuario) {
            $mia = array_values(array_filter($parts, fn($p) => (int)$p['usuario_id'] === $vistaUsuario));
            $g['valor_mostrado'] = $mia ? (float) $mia[0]['monto'] : 0.0;
        } else {
            $g['valor_mostrado'] = (float) $g['valor'];
        }

        $g['puede_editar'] = (int) $g['usuario_creador_id'] === current_user_id();
    }

    json_success($rows);
}

// ── Resumen rápido del período filtrado ──────────────────────────

function resumen_gastos(int $nidoId): never
{
    [$where, $params] = filtros_sql($nidoId);
    $vistaUsuario = vista_usuario_id();

    if ($vistaUsuario) {
        // Sumar solo la porción del usuario en la vista personal
        $sql = "SELECT
                    COALESCE(SUM(gp.monto), 0) AS total,
                    COALESCE(SUM(CASE WHEN g.estado='pagado'    THEN gp.monto ELSE 0 END), 0) AS pagados,
                    COALESCE(SUM(CASE WHEN g.estado='pendiente' THEN gp.monto ELSE 0 END), 0) AS pendientes,
                    COUNT(*) AS cantidad,
                    COUNT(CASE WHEN g.estado='pagado'    THEN 1 END) AS cnt_pagados,
                    COUNT(CASE WHEN g.estado='pendiente' THEN 1 END) AS cnt_pendientes
                FROM gastos g
                JOIN categorias c ON g.categoria_id = c.id
                JOIN gasto_participaciones gp ON gp.gasto_id = g.id AND gp.usuario_id = :vu
                WHERE $where";
        $params[':vu'] = $vistaUsuario;
    } else {
        $sql = "SELECT
                    COALESCE(SUM(g.valor), 0) AS total,
                    COALESCE(SUM(CASE WHEN g.estado='pagado'    THEN g.valor ELSE 0 END), 0) AS pagados,
                    COALESCE(SUM(CASE WHEN g.estado='pendiente' THEN g.valor ELSE 0 END), 0) AS pendientes,
                    COUNT(*) AS cantidad,
                    COUNT(CASE WHEN g.estado='pagado'    THEN 1 END) AS cnt_pagados,
                    COUNT(CASE WHEN g.estado='pendiente' THEN 1 END) AS cnt_pendientes
                FROM gastos g
                JOIN categorias c ON g.categoria_id = c.id
                WHERE $where";
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    json_success($stmt->fetch());
}

// ── Listar categorías (globales + propias del Nido) ──────────────

function listar_categorias(int $nidoId): never
{
    $stmt = db()->prepare(
        "SELECT id, nombre, icono, color FROM categorias
          WHERE tipo='gasto' AND activo=1 AND (nido_id IS NULL OR nido_id = :nido)
          ORDER BY nombre"
    );
    $stmt->execute([':nido' => $nidoId]);
    json_success($stmt->fetchAll());
}

// ── Listar períodos del Nido ──────────────────────────────────────

function listar_periodos(int $nidoId): never
{
    $stmt = db()->prepare(
        'SELECT id, anio, mes, activo FROM periodos WHERE nido_id = :nido ORDER BY anio DESC, mes DESC'
    );
    $stmt->execute([':nido' => $nidoId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['label'] = nombre_mes((int)$r['mes']) . ' ' . $r['anio'];
    }
    json_success($rows);
}

// ── Listar miembros activos del Nido ──────────────────────────────

function listar_miembros(int $nidoId): never
{
    $stmt = db()->prepare(
        'SELECT id, nombre, avatar_color FROM usuarios WHERE nido_id = :nido AND activo = 1 ORDER BY nombre'
    );
    $stmt->execute([':nido' => $nidoId]);
    json_success($stmt->fetchAll());
}

// ── Crear gasto ──────────────────────────────────────────────────

function crear_gasto(int $nidoId): never
{
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    $participaciones = validar_campos($d, $nidoId);

    $quincena = calcular_quincena($d['fecha_pago']);
    $db       = db();

    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            "INSERT INTO gastos
                (nido_id, concepto, categoria_id, valor, tipo, estado,
                 fecha_pago, quincena, periodo_id, usuario_creador_id, gasto_padre_id, notas)
             VALUES
                (:nido_id, :concepto, :categoria_id, :valor, :tipo, :estado,
                 :fecha_pago, :quincena, :periodo_id, :usuario_id, :padre_id, :notas)"
        );

        $stmt->execute([
            ':nido_id'      => $nidoId,
            ':concepto'     => trim($d['concepto']),
            ':categoria_id' => (int) $d['categoria_id'],
            ':valor'        => (float) $d['valor'],
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

        guardar_participaciones($db, $gastoId, $participaciones);

        if ($d['tipo'] === 'fijo') {
            $db->prepare('UPDATE gastos SET gasto_padre_id = id WHERE id = :id')
               ->execute([':id' => $gastoId]);
        }

        if ($d['tipo'] === 'cuotas') {
            $totalCuotas = max(2, (int)($d['total_cuotas'] ?? 2));
            $valorCuota  = round((float) $d['valor'] / $totalCuotas, 2);
            $db->prepare(
                "INSERT INTO gastos_cuotas
                    (gasto_id, gasto_origen_id, cuota_numero, total_cuotas, valor_cuota)
                 VALUES (:gasto_id, :origen_id, 1, :total, :valor)"
            )->execute([
                ':gasto_id'  => $gastoId,
                ':origen_id' => $gastoId,
                ':total'     => $totalCuotas,
                ':valor'     => $valorCuota,
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

function editar_gasto(int $nidoId): never
{
    $d  = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($d['id'] ?? 0);
    if (!$id) json_error('ID inválido.');

    $actual = db()->prepare('SELECT usuario_creador_id, nido_id FROM gastos WHERE id = :id AND deleted_at IS NULL');
    $actual->execute([':id' => $id]);
    $row = $actual->fetch();

    if (!$row || (int)$row['nido_id'] !== $nidoId) json_error('Gasto no encontrado.', 404);
    if ((int)$row['usuario_creador_id'] !== current_user_id()) {
        json_error('Solo el creador del gasto puede editarlo.', 403);
    }

    $participaciones = validar_campos($d, $nidoId);
    $quincena = calcular_quincena($d['fecha_pago']);
    $db = db();

    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            "UPDATE gastos SET
                concepto     = :concepto,
                categoria_id = :categoria_id,
                valor        = :valor,
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
            ':tipo'         => $d['tipo'],
            ':estado'       => $d['estado'],
            ':fecha_pago'   => $d['fecha_pago'],
            ':quincena'     => $quincena,
            ':notas'        => trim($d['notas'] ?? ''),
            ':id'           => $id,
        ]);

        $db->prepare('DELETE FROM gasto_participaciones WHERE gasto_id = :id')->execute([':id' => $id]);
        guardar_participaciones($db, $id, $participaciones);

        if ($d['tipo'] === 'cuotas') {
            $totalCuotas = max(1, (int)($d['total_cuotas'] ?? 1));
            $check = $db->prepare('SELECT id FROM gastos_cuotas WHERE gasto_id = :id');
            $check->execute([':id' => $id]);
            if ($check->fetch()) {
                $db->prepare('UPDATE gastos_cuotas SET total_cuotas = :total, valor_cuota = :valor WHERE gasto_id = :id')
                   ->execute([':total' => $totalCuotas, ':valor' => (float)$d['valor'], ':id' => $id]);
            }
        }

        $db->commit();
        json_success(null, 'Gasto actualizado correctamente.');

    } catch (Throwable $e) {
        $db->rollBack();
        json_error('Error al actualizar el gasto: ' . $e->getMessage(), 500);
    }
}

// ── Cambiar estado (pagado / pendiente) ──────────────────────────

function cambiar_estado(int $nidoId): never
{
    $d      = json_decode(file_get_contents('php://input'), true) ?? [];
    $id     = (int)($d['id'] ?? 0);
    $estado = $d['estado'] ?? '';

    if (!$id || !in_array($estado, ['pendiente','pagado'])) {
        json_error('Datos inválidos.');
    }

    // Verificar que el gasto pertenece al Nido y que el usuario autenticado es su creador
    $row = db()->prepare('SELECT usuario_creador_id FROM gastos WHERE id = :id AND nido_id = :nido');
    $row->execute([':id' => $id, ':nido' => $nidoId]);
    $g = $row->fetch();

    if (!$g) json_error('Gasto no encontrado.', 404);
    if ((int) $g['usuario_creador_id'] !== current_user_id()) {
        json_error('Solo el creador del gasto puede cambiar su estado.', 403);
    }

    db()->prepare('UPDATE gastos SET estado = :estado WHERE id = :id AND deleted_at IS NULL')
        ->execute([':estado' => $estado, ':id' => $id]);

    json_success(null, 'Estado actualizado.');
}

// ── Eliminar gasto (soft delete) ─────────────────────────────────

function eliminar_gasto(int $nidoId): never
{
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_error('ID inválido.');

    $stmt = db()->prepare(
        'SELECT usuario_creador_id, nido_id FROM gastos WHERE id = :id AND deleted_at IS NULL'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    if (!$row || (int)$row['nido_id'] !== $nidoId) json_error('Gasto no encontrado.', 404);
    if ((int)$row['usuario_creador_id'] !== current_user_id()) {
        json_error('Solo el creador del gasto puede eliminarlo.', 403);
    }

    $check = db()->prepare(
        'SELECT COUNT(*) FROM gastos_cuotas
          JOIN gastos g ON g.id = gastos_cuotas.gasto_id
         WHERE gastos_cuotas.gasto_origen_id = :id1
           AND gastos_cuotas.gasto_id != :id2
           AND g.deleted_at IS NULL'
    );
    $check->execute([':id1' => $id, ':id2' => $id]);
    if ((int)$check->fetchColumn() > 0) {
        json_error('Este gasto tiene cuotas activas en otros meses. Elimínalas individualmente.');
    }

    db()->prepare(
        'UPDATE gastos SET deleted_at = NOW(), deleted_by = :uid WHERE id = :id'
    )->execute([':uid' => current_user_id(), ':id' => $id]);

    json_success(null, 'Gasto eliminado.');
}

// ── Participaciones ────────────────────────────────────────────────

/**
 * Calcula las participaciones a partir del payload.
 * - distribucion = 'individual'   -> 100% a un solo usuario_id
 * - distribucion = 'igual'        -> dividido entre participantes[]
 * - distribucion = 'personalizado'-> montos explícitos en participantes[]
 *
 * Devuelve array [['usuario_id' => int, 'monto' => float], ...]
 */
function calcular_participaciones(array $d, int $nidoId): array
{
    $valor = (float) ($d['valor'] ?? 0);
    $distribucion = $d['distribucion'] ?? 'individual';

    if ($distribucion === 'individual') {
        $uid = (int) ($d['usuario_id'] ?? 0);
        return $uid ? [['usuario_id' => $uid, 'monto' => $valor]] : [];
    }

    $participantes = $d['participantes'] ?? [];
    if (!is_array($participantes) || !count($participantes)) return [];

    if ($distribucion === 'igual') {
        $n = count($participantes);
        $porPersona = round($valor / $n, 2);
        $resultado = [];
        $acumulado = 0;
        foreach ($participantes as $i => $uid) {
            // Ajustar el último para que la suma sea exacta (evita errores de redondeo)
            $monto = ($i === $n - 1) ? round($valor - $acumulado, 2) : $porPersona;
            $acumulado += $monto;
            $resultado[] = ['usuario_id' => (int) $uid, 'monto' => $monto];
        }
        return $resultado;
    }

    if ($distribucion === 'personalizado') {
        $montos = $d['montos'] ?? [];
        $resultado = [];
        foreach ($participantes as $uid) {
            $resultado[] = ['usuario_id' => (int) $uid, 'monto' => (float) ($montos[$uid] ?? 0)];
        }
        return $resultado;
    }

    return [];
}

function guardar_participaciones(PDO $db, int $gastoId, array $participaciones): void
{
    $stmt = $db->prepare(
        'INSERT INTO gasto_participaciones (gasto_id, usuario_id, monto) VALUES (:g, :u, :m)'
    );
    foreach ($participaciones as $p) {
        $stmt->execute([':g' => $gastoId, ':u' => $p['usuario_id'], ':m' => $p['monto']]);
    }
}

// ── Validación común ─────────────────────────────────────────────

function validar_campos(array $d, int $nidoId): array
{
    $errores = [];

    if (empty(trim($d['concepto'] ?? '')))         $errores[] = 'El concepto es obligatorio.';
    if (empty($d['categoria_id']))                  $errores[] = 'La categoría es obligatoria.';
    if (!isset($d['valor']) || $d['valor'] <= 0)   $errores[] = 'El valor debe ser mayor a cero.';
    if (!in_array($d['tipo'] ?? '', ['fijo','cuotas','variable']))
                                                    $errores[] = 'Tipo de gasto inválido.';
    if (empty($d['fecha_pago']))                    $errores[] = 'La fecha de pago es obligatoria.';
    if (empty($d['periodo_id']))                    $errores[] = 'El período es obligatorio.';
    if ($d['tipo'] === 'cuotas' && (int)($d['total_cuotas'] ?? 0) < 2)
                                                    $errores[] = 'Las cuotas deben ser al menos 2.';

    $participaciones = calcular_participaciones($d, $nidoId);

    if (!count($participaciones)) {
        $errores[] = 'Debes asignar el gasto a al menos un miembro del Nido.';
    } else {
        $sumaParticipaciones = round(array_sum(array_column($participaciones, 'monto')), 2);
        $valor = round((float) ($d['valor'] ?? 0), 2);
        if (abs($sumaParticipaciones - $valor) > 0.5) {
            $errores[] = "La suma de las participaciones ($sumaParticipaciones) no coincide con el valor total ($valor).";
        }

        // Verificar que todos los usuario_id pertenezcan al Nido
        $ids = array_column($participaciones, 'usuario_id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = db()->prepare("SELECT COUNT(*) FROM usuarios WHERE id IN ($placeholders) AND nido_id = ?");
        $stmt->execute([...$ids, $nidoId]);
        if ((int) $stmt->fetchColumn() !== count(array_unique($ids))) {
            $errores[] = 'Uno o más participantes no pertenecen a este Nido.';
        }
    }

    if ($errores) json_error('Datos inválidos.', 422, $errores);

    return $participaciones;
}
