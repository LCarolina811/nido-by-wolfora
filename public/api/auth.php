<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../src/helpers/response_helper.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Método no permitido.', 405);
}

$body = json_decode(file_get_contents('php://input'), true);

if (!$body || !isset($body['action'])) {
    json_error('Solicitud inválida.', 400);
}

match ($body['action']) {
    'login'  => handle_login($body),
    'logout' => handle_logout(),
    default  => json_error('Acción no reconocida.', 400),
};

// ── Handlers ────────────────────────────────────────────────────

function handle_login(array $data): never
{
    $email    = trim($data['email']    ?? '');
    $password = trim($data['password'] ?? '');

    if (!$email || !$password) {
        json_error('Correo y contraseña son obligatorios.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_error('Correo inválido.');
    }

    try {
        $stmt = db()->prepare(
            'SELECT id, nombre, email, password_hash, avatar_color
               FROM usuarios
              WHERE email = :email AND activo = 1
              LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        $usuario = $stmt->fetch();

        if (!$usuario || !password_verify($password, $usuario['password_hash'])) {
            // Mismo mensaje para no revelar qué campo es incorrecto
            json_error('Credenciales incorrectas.', 401);
        }

        login_user($usuario);

        json_success([
            'nombre' => $usuario['nombre'],
            'color'  => $usuario['avatar_color'],
        ], 'Sesión iniciada correctamente.');

    } catch (PDOException $e) {
        json_error('Error interno del servidor.', 500);
    }
}

function handle_logout(): never
{
    logout_user();
    json_success(null, 'Sesión cerrada.');
}
