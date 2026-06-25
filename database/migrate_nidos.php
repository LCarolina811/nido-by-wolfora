<?php

/**
 * Migración: arquitectura multi-tenant basada en "Nidos".
 *
 * Ejecutar UNA SOLA VEZ desde el navegador o CLI:
 *   php database/migrate_nidos.php
 *
 * Qué hace:
 *  - Crea la tabla `nidos` y un Nido inicial ("Wolfora") para los usuarios existentes.
 *  - Agrega nido_id a usuarios, categorias, periodos, gastos, ingresos, ahorros, presupuestos.
 *  - Reemplaza el ENUM `gastos.responsable` por la tabla `gasto_participaciones`
 *    (gastos compartidos antiguos se dividen 50/50 entre los miembros activos).
 *  - Reemplaza el ENUM `presupuestos.responsable` por `presupuestos.usuario_id` (NULL = todo el Nido).
 *
 * Es idempotente: puede ejecutarse varias veces sin duplicar cambios.
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/config/database.php';

header('Content-Type: text/plain; charset=utf-8');

$pdo = db();

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c"
    );
    $stmt->execute([':t' => $table, ':c' => $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t"
    );
    $stmt->execute([':t' => $table]);
    return (int) $stmt->fetchColumn() > 0;
}

function step(string $label, callable $fn): void
{
    echo "→ $label ... ";
    try {
        $fn();
        echo "OK\n";
    } catch (Throwable $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}

// ── 1. Tabla nidos ─────────────────────────────────────────────

step('Crear tabla nidos', function () use ($pdo) {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS nidos (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nombre      VARCHAR(100) NOT NULL,
            created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
});

step('Crear Nido inicial "Wolfora" si no existe', function () use ($pdo) {
    $stmt = $pdo->query("SELECT id FROM nidos ORDER BY id ASC LIMIT 1");
    $row = $stmt->fetch();
    if (!$row) {
        $pdo->exec("INSERT INTO nidos (nombre) VALUES ('Wolfora')");
    }
});

$nidoId = (int) $pdo->query("SELECT id FROM nidos ORDER BY id ASC LIMIT 1")->fetchColumn();

// ── 2. usuarios.nido_id ──────────────────────────────────────────

step('Agregar usuarios.nido_id', function () use ($pdo, $nidoId) {
    if (!columnExists($pdo, 'usuarios', 'nido_id')) {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN nido_id INT UNSIGNED NULL AFTER id");
    }
    $pdo->exec("UPDATE usuarios SET nido_id = $nidoId WHERE nido_id IS NULL");
    if (!columnExists($pdo, 'usuarios', 'nido_id_not_null_done')) {
        try {
            $pdo->exec("ALTER TABLE usuarios MODIFY nido_id INT UNSIGNED NOT NULL");
            $pdo->exec(
                "ALTER TABLE usuarios ADD CONSTRAINT fk_usuarios_nido
                 FOREIGN KEY (nido_id) REFERENCES nidos(id)"
            );
        } catch (Throwable) { /* FK ya existe */ }
    }
});

// ── 3. categorias.nido_id (NULL = categoría global compartida) ──

step('Agregar categorias.nido_id', function () use ($pdo) {
    if (!columnExists($pdo, 'categorias', 'nido_id')) {
        $pdo->exec("ALTER TABLE categorias ADD COLUMN nido_id INT UNSIGNED NULL AFTER id");
        $pdo->exec(
            "ALTER TABLE categorias ADD CONSTRAINT fk_categorias_nido
             FOREIGN KEY (nido_id) REFERENCES nidos(id)"
        );
    }
});

// ── 4. periodos.nido_id ──────────────────────────────────────────

step('Agregar periodos.nido_id', function () use ($pdo, $nidoId) {
    if (!columnExists($pdo, 'periodos', 'nido_id')) {
        $pdo->exec("ALTER TABLE periodos ADD COLUMN nido_id INT UNSIGNED NULL AFTER id");
    }
    $pdo->exec("UPDATE periodos SET nido_id = $nidoId WHERE nido_id IS NULL");
    try {
        $pdo->exec("ALTER TABLE periodos DROP INDEX uk_periodo");
    } catch (Throwable) { /* ya eliminado */ }
    try {
        $pdo->exec("ALTER TABLE periodos MODIFY nido_id INT UNSIGNED NOT NULL");
        $pdo->exec(
            "ALTER TABLE periodos ADD CONSTRAINT fk_periodos_nido
             FOREIGN KEY (nido_id) REFERENCES nidos(id)"
        );
        $pdo->exec("ALTER TABLE periodos ADD UNIQUE KEY uk_periodo_nido (nido_id, anio, mes)");
    } catch (Throwable) { /* ya aplicado */ }
});

// ── 5. gastos.nido_id + gasto_participaciones ────────────────────

step('Agregar gastos.nido_id', function () use ($pdo, $nidoId) {
    if (!columnExists($pdo, 'gastos', 'nido_id')) {
        $pdo->exec("ALTER TABLE gastos ADD COLUMN nido_id INT UNSIGNED NULL AFTER id");
    }
    $pdo->exec("UPDATE gastos SET nido_id = $nidoId WHERE nido_id IS NULL");
    try {
        $pdo->exec("ALTER TABLE gastos MODIFY nido_id INT UNSIGNED NOT NULL");
        $pdo->exec(
            "ALTER TABLE gastos ADD CONSTRAINT fk_gastos_nido
             FOREIGN KEY (nido_id) REFERENCES nidos(id)"
        );
    } catch (Throwable) { /* ya aplicado */ }
});

step('Crear tabla gasto_participaciones', function () use ($pdo) {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS gasto_participaciones (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            gasto_id    INT UNSIGNED NOT NULL,
            usuario_id  INT UNSIGNED NOT NULL,
            monto       DECIMAL(12,2) NOT NULL,
            created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_participacion_gasto   FOREIGN KEY (gasto_id)   REFERENCES gastos(id)   ON DELETE CASCADE,
            CONSTRAINT fk_participacion_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
            UNIQUE KEY uk_gasto_usuario (gasto_id, usuario_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
});

step('Migrar gastos.responsable -> gasto_participaciones', function () use ($pdo, $nidoId) {
    if (!columnExists($pdo, 'gastos', 'responsable')) {
        return; // ya migrado
    }

    $miembros = $pdo->prepare("SELECT id, nombre FROM usuarios WHERE nido_id = :nido AND activo = 1");
    $miembros->execute([':nido' => $nidoId]);
    $miembros = $miembros->fetchAll();

    $idsPorNombre = [];
    foreach ($miembros as $m) {
        $idsPorNombre[mb_strtolower($m['nombre'])] = (int) $m['id'];
    }

    $gastos = $pdo->query("SELECT id, valor, responsable FROM gastos")->fetchAll();
    $insert = $pdo->prepare(
        "INSERT IGNORE INTO gasto_participaciones (gasto_id, usuario_id, monto) VALUES (:g, :u, :m)"
    );

    foreach ($gastos as $g) {
        $valor = (float) $g['valor'];

        if ($g['responsable'] === 'compartido') {
            $n = count($miembros);
            if ($n === 0) continue;
            $porPersona = round($valor / $n, 2);
            foreach ($miembros as $m) {
                $insert->execute([':g' => $g['id'], ':u' => $m['id'], ':m' => $porPersona]);
            }
        } else {
            $uid = $idsPorNombre[$g['responsable']] ?? null;
            if ($uid) {
                $insert->execute([':g' => $g['id'], ':u' => $uid, ':m' => $valor]);
            }
        }
    }

    $pdo->exec("ALTER TABLE gastos DROP COLUMN responsable");
});

// ── 6. ingresos.nido_id ───────────────────────────────────────────

step('Agregar ingresos.nido_id', function () use ($pdo, $nidoId) {
    if (!columnExists($pdo, 'ingresos', 'nido_id')) {
        $pdo->exec("ALTER TABLE ingresos ADD COLUMN nido_id INT UNSIGNED NULL AFTER id");
    }
    $pdo->exec("UPDATE ingresos SET nido_id = $nidoId WHERE nido_id IS NULL");
    try {
        $pdo->exec("ALTER TABLE ingresos MODIFY nido_id INT UNSIGNED NOT NULL");
        $pdo->exec(
            "ALTER TABLE ingresos ADD CONSTRAINT fk_ingresos_nido
             FOREIGN KEY (nido_id) REFERENCES nidos(id)"
        );
    } catch (Throwable) { /* ya aplicado */ }
});

// ── 7. ahorros.nido_id ────────────────────────────────────────────

step('Agregar ahorros.nido_id', function () use ($pdo, $nidoId) {
    if (!columnExists($pdo, 'ahorros', 'nido_id')) {
        $pdo->exec("ALTER TABLE ahorros ADD COLUMN nido_id INT UNSIGNED NULL AFTER id");
    }
    $pdo->exec("UPDATE ahorros SET nido_id = $nidoId WHERE nido_id IS NULL");
    try {
        $pdo->exec("ALTER TABLE ahorros MODIFY nido_id INT UNSIGNED NOT NULL");
        $pdo->exec(
            "ALTER TABLE ahorros ADD CONSTRAINT fk_ahorros_nido
             FOREIGN KEY (nido_id) REFERENCES nidos(id)"
        );
    } catch (Throwable) { /* ya aplicado */ }
});

// ── 8. presupuestos.nido_id + usuario_id (reemplaza responsable) ──

step('Agregar presupuestos.nido_id y usuario_id', function () use ($pdo, $nidoId) {
    if (!columnExists($pdo, 'presupuestos', 'nido_id')) {
        $pdo->exec("ALTER TABLE presupuestos ADD COLUMN nido_id INT UNSIGNED NULL AFTER id");
    }
    $pdo->exec("UPDATE presupuestos SET nido_id = $nidoId WHERE nido_id IS NULL");

    if (!columnExists($pdo, 'presupuestos', 'usuario_id')) {
        $pdo->exec("ALTER TABLE presupuestos ADD COLUMN usuario_id INT UNSIGNED NULL AFTER categoria_id");
    }

    if (columnExists($pdo, 'presupuestos', 'responsable')) {
        $miembros = $pdo->prepare("SELECT id, nombre FROM usuarios WHERE nido_id = :nido");
        $miembros->execute([':nido' => $nidoId]);
        foreach ($miembros->fetchAll() as $m) {
            $pdo->prepare("UPDATE presupuestos SET usuario_id = :uid WHERE responsable = :nombre")
                ->execute([':uid' => $m['id'], ':nombre' => mb_strtolower($m['nombre'])]);
        }
        // El índice único original incluye `responsable` y sostiene la FK de categoria_id;
        // se crea un índice de respaldo antes de poder eliminarlo.
        try {
            $pdo->exec("ALTER TABLE presupuestos ADD INDEX idx_presupuestos_categoria (categoria_id)");
        } catch (Throwable) { /* ya existe */ }
        try {
            $pdo->exec("ALTER TABLE presupuestos DROP INDEX uk_presupuesto");
        } catch (Throwable) { /* ya eliminado o no existe */ }
        // 'compartido' queda como usuario_id NULL (presupuesto de todo el Nido)
        $pdo->exec("ALTER TABLE presupuestos DROP COLUMN responsable");
    }

    try {
        $pdo->exec("ALTER TABLE presupuestos MODIFY nido_id INT UNSIGNED NOT NULL");
        $pdo->exec(
            "ALTER TABLE presupuestos ADD CONSTRAINT fk_presupuestos_nido
             FOREIGN KEY (nido_id) REFERENCES nidos(id)"
        );
        $pdo->exec(
            "ALTER TABLE presupuestos ADD CONSTRAINT fk_presupuestos_usuario
             FOREIGN KEY (usuario_id) REFERENCES usuarios(id)"
        );
        $pdo->exec(
            "ALTER TABLE presupuestos ADD UNIQUE KEY uk_presupuesto_nido (nido_id, categoria_id, periodo_id, usuario_id)"
        );
    } catch (Throwable) { /* ya aplicado */ }
});

echo "\n✅ Migración completada. Nido activo: #$nidoId (Wolfora)\n";
echo "⚠️  Elimina este archivo después de confirmar que todo funciona.\n";
