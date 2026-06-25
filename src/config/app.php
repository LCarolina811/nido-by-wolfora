<?php

declare(strict_types=1);

/**
 * Detecta la URL base de la aplicación automáticamente.
 * Funciona en localhost/subfolder, dominio propio o cualquier servidor.
 *
 * Ejemplos:
 *   http://localhost/nido-by-wolfora/public  → APP_URL = 'http://localhost/nido-by-wolfora/public'
 *   https://nido.tudominio.com               → APP_URL = 'https://nido.tudominio.com'
 *
 * Para forzar una URL específica (ej. producción), define APP_URL_OVERRIDE
 * en un archivo .env o directamente aquí:
 *   define('APP_URL_OVERRIDE', 'https://nido.tudominio.com');
 */
if (!defined('APP_URL')) {

    if (defined('APP_URL_OVERRIDE')) {
        define('APP_URL', rtrim(APP_URL_OVERRIDE, '/'));
    } else {
        $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';

        // Directorio público relativo al document root (resuelto, sin ../../)
        $docRoot  = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
        $pubDir   = rtrim(realpath(__DIR__ . '/../../public') ?: (__DIR__ . '/../../public'), '/\\');

        // Normaliza separadores para comparar en Windows
        $docRoot  = str_replace('\\', '/', $docRoot);
        $pubDir   = str_replace('\\', '/', $pubDir);

        // Comparación insensible a mayúsculas (unidades de Windows: C:/ vs c:/)
        $basePath = stripos($pubDir, $docRoot) === 0
            ? substr($pubDir, strlen($docRoot))
            : '';

        define('APP_URL', $scheme . '://' . $host . $basePath);
    }
}

/**
 * Genera una URL absoluta a partir de una ruta relativa al public/.
 *
 * Ejemplos:
 *   url('views/login.php')      → http://localhost/nido-by-wolfora/public/views/login.php
 *   url('api/auth.php')         → http://localhost/nido-by-wolfora/public/api/auth.php
 *   url('assets/css/app.css')   → http://localhost/nido-by-wolfora/public/assets/css/app.css
 */
function url(string $path = ''): string
{
    return APP_URL . '/' . ltrim($path, '/');
}

/**
 * Redirige a una URL generada con url() y detiene la ejecución.
 */
function redirect(string $path = ''): never
{
    header('Location: ' . url($path));
    exit;
}
