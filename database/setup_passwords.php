<?php

/**
 * Script de configuración inicial de contraseñas.
 * Ejecutar UNA SOLA VEZ desde el navegador o CLI:
 *   php database/setup_passwords.php
 * Luego ELIMINAR o mover fuera del directorio público.
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/config/database.php';

$usuarios = [
    ['email' => 'carolina@nido.local', 'password' => 'carolina2025'],
    ['email' => 'javier@nido.local',   'password' => 'javier2025'],
];

echo "<pre>\n";
echo "Actualizando contraseñas...\n\n";

try {
    $stmt = db()->prepare(
        'UPDATE usuarios SET password_hash = :hash WHERE email = :email'
    );

    foreach ($usuarios as $u) {
        $hash = password_hash($u['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt->execute([':hash' => $hash, ':email' => $u['email']]);
        echo "✅ {$u['email']} — hash generado OK\n";
    }

    echo "\n✅ Contraseñas configuradas correctamente.\n";
    echo "⚠️  IMPORTANTE: Elimina este archivo ahora.\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "</pre>\n";
