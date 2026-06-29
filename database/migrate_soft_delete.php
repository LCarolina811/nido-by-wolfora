<?php

/**
 * Migración: Soft Delete (eliminación lógica).
 *
 * Ejecutar UNA SOLA VEZ:
 *   http://nido-by-wolfora.test/database/migrate_soft_delete.php
 *   o: php database/migrate_soft_delete.php
 *
 * Qué hace:
 *  - Agrega deleted_at y deleted_by a: gastos, ingresos, presupuestos, ahorros.
 *  - Crea índices para filtrar registros activos eficientemente.
 *  - Es idempotente: puede ejecutarse varias veces sin efecto.
 *
 * Convención: WHERE deleted_at IS NULL = registro activo (visible).
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

function step(string $label, callable $fn): void
{
    echo "→ $label ... ";
    try { $fn(); echo "OK\n"; }
    catch (Throwable $e) { echo "ERROR: " . $e->getMessage() . "\n"; }
}

$tablas = ['gastos', 'ingresos', 'presupuestos', 'ahorros'];

foreach ($tablas as $tabla) {

    step("$tabla: agregar deleted_at", function () use ($pdo, $tabla) {
        if (columnExists($pdo, $tabla, 'deleted_at')) return;
        $pdo->exec(
            "ALTER TABLE `$tabla`
             ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at"
        );
        // Índice para filtrar registros activos (WHERE deleted_at IS NULL)
        $pdo->exec(
            "ALTER TABLE `$tabla`
             ADD INDEX idx_{$tabla}_deleted (deleted_at)"
        );
    });

    step("$tabla: agregar deleted_by", function () use ($pdo, $tabla) {
        if (columnExists($pdo, $tabla, 'deleted_by')) return;
        $pdo->exec(
            "ALTER TABLE `$tabla`
             ADD COLUMN deleted_by INT UNSIGNED NULL DEFAULT NULL AFTER deleted_at,
             ADD CONSTRAINT fk_{$tabla}_deleted_by
                 FOREIGN KEY (deleted_by) REFERENCES usuarios(id)"
        );
    });

}

echo "\n✅ Soft delete implementado en: " . implode(', ', $tablas) . "\n";
echo "⚠️  Elimina este archivo después de confirmar que todo funciona.\n";
