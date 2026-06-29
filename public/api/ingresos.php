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
    $method === 'GET'    && $action === 'listar'     => listar_ingresos($nidoId),
    $method === 'GET'    && $action === 'resumen'    => resumen_ingresos($nidoId),
    $method === 'GET'    && $action === 'categorias' => listar_categorias($nidoId),
    $method === 'GET'    && $action === 'periodos'   => listar_periodos($nidoId),
    $method === 'GET'    && $action === 'miembros'   => listar_miembros($nidoId),
    $method === 'POST'   && $action === 'crear'      => crear_ingreso($nidoId),
    $method === 'POST'   && $action === 'editar'     => editar_ingreso($nidoId),
    $method === 'DELETE' && $action === 'eliminar'   => eliminar_ingreso($nidoId),
    default => json_error('Acción no válida.', 400),
};

// ── Helpers ──────────────────────────────────────────────────────

function filtros_sql_ingresos(int $nidoId): array
{
    $where  = ['i.nido_id = :nido_id', 'i.deleted_at IS NULL'];
    $params = [':nido_id' => $nidoId];

    if (!empty($_GET['periodo_id'])) {
        $where[]  = 'i.periodo_id = :periodo_id';
        $params[':periodo_id'] = (int) $_GET['periodo_id'];
    } else {
        $where[]  = 'i.periodo_id = (SELECT id FROM periodos WHERE nido_id = :nido_id2 AND activo = 1 LIMIT 1)';
        $params[':nido_id2'] = $nidoId;
    }

    if (!empty($_GET['categoria_id'])) {
        $where[]  = 'i.categoria_id = :categoria_id';
        $params[':categoria_id'] = (int) $_GET['categoria_id'];
    }

    if (!empty($_GET['tipo']) && in_array($_GET['tipo'], ['fijo','variable'])) {
        $where[]  = 'i.tipo = :tipo';
        $params[':tipo'] = $_GET['tipo'];
    }

    if (!empty($_GET['usuario_id'])) {
        $where[]  = 'i.usuario_id = :usuario_id';
        $params[':usuario_id'] = (int) $_GET['usuario_id'];
    }

    return [implode(' AND ', $where), $params];
}

// ── Listar ingresos ───────────────────────────────────────────────

function listar_ingresos(int $nidoId): never
{
    [$where, $params] = filtros_sql_ingresos($nidoId);

    $sql = "SELECT
                i.id, i.concepto, i.valor, i.tipo, i.fecha, i.notas,
                i.ingreso_padre_id, i.periodo_id, i.usuario_id,
                c.nombre AS categoria, c.color AS cat_color, c.icono AS cat_icono,
                u.nombre AS propietario, u.avatar_color AS propietario_color
            FROM ingresos i
            JOIN categorias c ON i.categoria_id = c.id
            JOIN usuarios   u ON i.usuario_id   = u.id
            WHERE $where
            ORDER BY i.fecha DESC, i.id DESC";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Marcar si el usuario actual puede editar/eliminar
    $uid = current_user_id();
    foreach ($rows as &$r) {
        $r['puede_editar'] = (int)$r['usuario_id'] === $uid;
    }

    json_success($rows);
}

// ── Resumen ───────────────────────────────────────────────────────

function resumen_ingresos(int $nidoId): never
{
    [$where, $params] = filtros_sql_ingresos($nidoId);

    $sql = "SELECT
                COALESCE(SUM(i.valor), 0)                                                    AS total,
                COALESCE(SUM(CASE WHEN i.tipo='fijo'     THEN i.valor ELSE 0 END), 0)        AS fijos,
                COALESCE(SUM(CASE WHEN i.tipo='variable' THEN i.valor ELSE 0 END), 0)        AS variables,
                COUNT(*)                                                                      AS cantidad,
                COUNT(CASE WHEN i.tipo='fijo'     THEN 1 END)                                AS cnt_fijos,
                COUNT(CASE WHEN i.tipo='variable' THEN 1 END)                                AS cnt_variables
            FROM ingresos i
            JOIN categorias c ON i.categoria_id = c.id
            WHERE $where";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    json_success($stmt->fetch());
}

// ── Categorías de ingreso ────────────────────────────────────────

function listar_categorias(int $nidoId): never
{
    $stmt = db()->prepare(
        "SELECT id, nombre, icono, color FROM categorias
          WHERE tipo='ingreso' AND activo=1 AND (nido_id IS NULL OR nido_id = :nido)
          ORDER BY nombre"
    );
    $stmt->execute([':nido' => $nidoId]);
    json_success($stmt->fetchAll());
}

// ── Períodos ─────────────────────────────────────────────────────

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

// ── Miembros del Nido ─────────────────────────────────────────────

function listar_miembros(int $nidoId): never
{
    $stmt = db()->prepare(
        'SELECT id, nombre, avatar_color FROM usuarios WHERE nido_id = :nido AND activo = 1 ORDER BY nombre'
    );
    $stmt->execute([':nido' => $nidoId]);
    json_success($stmt->fetchAll());
}

// ── Crear ingreso ─────────────────────────────────────────────────

function crear_ingreso(int $nidoId): never
{
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    validar_ingreso($d, $nidoId);

    $db = db();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            "INSERT INTO ingresos
                (nido_id, concepto, categoria_id, valor, usuario_id, tipo,
                 fecha, periodo_id, ingreso_padre_id, notas)
             VALUES
                (:nido_id, :concepto, :categoria_id, :valor, :usuario_id, :tipo,
                 :fecha, :periodo_id, :padre_id, :notas)"
        );

        $stmt->execute([
            ':nido_id'      => $nidoId,
            ':concepto'     => trim($d['concepto']),
            ':categoria_id' => (int) $d['categoria_id'],
            ':valor'        => (float) $d['valor'],
            ':usuario_id'   => (int) $d['usuario_id'],
            ':tipo'         => $d['tipo'],
            ':fecha'        => $d['fecha'],
            ':periodo_id'   => (int) $d['periodo_id'],
            ':padre_id'     => null,
            ':notas'        => trim($d['notas'] ?? ''),
        ]);

        $ingresoId = (int) $db->lastInsertId();

        // Si es fijo: se marca a sí mismo como padre para replicación futura
        if ($d['tipo'] === 'fijo') {
            $db->prepare('UPDATE ingresos SET ingreso_padre_id = id WHERE id = :id')
               ->execute([':id' => $ingresoId]);
        }

        $db->commit();
        json_success(['id' => $ingresoId], 'Ingreso registrado correctamente.', 201);

    } catch (Throwable $e) {
        $db->rollBack();
        json_error('Error al crear el ingreso: ' . $e->getMessage(), 500);
    }
}

// ── Editar ingreso ────────────────────────────────────────────────

function editar_ingreso(int $nidoId): never
{
    $d  = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($d['id'] ?? 0);
    if (!$id) json_error('ID inválido.');

    $row = db()->prepare(
        'SELECT usuario_id, nido_id FROM ingresos WHERE id = :id AND deleted_at IS NULL'
    );
    $row->execute([':id' => $id]);
    $actual = $row->fetch();

    if (!$actual || (int)$actual['nido_id'] !== $nidoId) json_error('Ingreso no encontrado.', 404);
    if ((int)$actual['usuario_id'] !== current_user_id()) {
        json_error('Solo el propietario puede editar este ingreso.', 403);
    }

    validar_ingreso($d, $nidoId);

    db()->prepare(
        "UPDATE ingresos SET
            concepto     = :concepto,
            categoria_id = :categoria_id,
            valor        = :valor,
            usuario_id   = :usuario_id,
            tipo         = :tipo,
            fecha        = :fecha,
            notas        = :notas
         WHERE id = :id"
    )->execute([
        ':concepto'     => trim($d['concepto']),
        ':categoria_id' => (int) $d['categoria_id'],
        ':valor'        => (float) $d['valor'],
        ':usuario_id'   => (int) $d['usuario_id'],
        ':tipo'         => $d['tipo'],
        ':fecha'        => $d['fecha'],
        ':notas'        => trim($d['notas'] ?? ''),
        ':id'           => $id,
    ]);

    json_success(null, 'Ingreso actualizado correctamente.');
}

// ── Eliminar ingreso (soft delete) ───────────────────────────────

function eliminar_ingreso(int $nidoId): never
{
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_error('ID inválido.');

    $stmt = db()->prepare(
        'SELECT usuario_id, nido_id FROM ingresos WHERE id = :id AND deleted_at IS NULL'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    if (!$row || (int)$row['nido_id'] !== $nidoId) json_error('Ingreso no encontrado.', 404);
    if ((int)$row['usuario_id'] !== current_user_id()) {
        json_error('Solo el propietario puede eliminar este ingreso.', 403);
    }

    db()->prepare(
        'UPDATE ingresos SET deleted_at = NOW(), deleted_by = :uid WHERE id = :id'
    )->execute([':uid' => current_user_id(), ':id' => $id]);

    json_success(null, 'Ingreso eliminado.');
}

// ── Validación ───────────────────────────────────────────────────

function validar_ingreso(array $d, int $nidoId): void
{
    $errores = [];

    if (empty(trim($d['concepto'] ?? '')))         $errores[] = 'El concepto es obligatorio.';
    if (empty($d['categoria_id']))                  $errores[] = 'La categoría es obligatoria.';
    if (!isset($d['valor']) || $d['valor'] <= 0)   $errores[] = 'El valor debe ser mayor a cero.';
    if (empty($d['usuario_id']))                    $errores[] = 'El propietario es obligatorio.';
    if (!in_array($d['tipo'] ?? '', ['fijo','variable']))
                                                    $errores[] = 'Tipo de ingreso inválido.';
    if (empty($d['fecha']))                         $errores[] = 'La fecha es obligatoria.';
    if (empty($d['periodo_id']))                    $errores[] = 'El período es obligatorio.';

    // Verificar que usuario_id pertenezca al Nido
    if (!empty($d['usuario_id'])) {
        $stmt = db()->prepare('SELECT id FROM usuarios WHERE id = :id AND nido_id = :nido AND activo = 1');
        $stmt->execute([':id' => $d['usuario_id'], ':nido' => $nidoId]);
        if (!$stmt->fetch()) $errores[] = 'El propietario no pertenece a este Nido.';
    }

    if ($errores) json_error('Datos inválidos.', 422, $errores);
}
