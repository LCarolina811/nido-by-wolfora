<?php

declare(strict_types=1);

function session_start_safe(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => false,   // cambiar a true en producción con HTTPS
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

function is_logged_in(): bool
{
    session_start_safe();
    return isset($_SESSION['usuario_id']) && isset($_SESSION['usuario_nombre']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        // app.php debe estar cargado antes de llamar a require_login()
        if (function_exists('redirect')) {
            redirect('views/login.php');
        }
        header('Location: views/login.php');
        exit;
    }
}

function current_user_id(): int
{
    return (int) ($_SESSION['usuario_id'] ?? 0);
}

function current_user_name(): string
{
    return $_SESSION['usuario_nombre'] ?? '';
}

function current_user_color(): string
{
    return $_SESSION['usuario_color'] ?? '#6C63FF';
}

function login_user(array $usuario): void
{
    session_start_safe();
    session_regenerate_id(true);
    $_SESSION['usuario_id']     = $usuario['id'];
    $_SESSION['usuario_nombre'] = $usuario['nombre'];
    $_SESSION['usuario_email']  = $usuario['email'];
    $_SESSION['usuario_color']  = $usuario['avatar_color'];
}

function logout_user(): void
{
    session_start_safe();
    $_SESSION = [];
    session_destroy();
}
