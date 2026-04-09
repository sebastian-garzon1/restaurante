<?php
session_start();

require "vendor/autoload.php";

// Configuración
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/roles.php';
require_once __DIR__ . '/middleware/auth.php';

date_default_timezone_set('America/Bogota');

use Phroute\Phroute\RouteCollector;
use Phroute\Phroute\Dispatcher;

// Obtener la ruta solicitada
$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

// Normalizar (quitar trailing slash)
$path = rtrim($path, '/');
if ($path === '') $path = '/';

$publicDir = __DIR__ . '/public';
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

/*
|--------------------------------------------------------------------------
| ARCHIVOS COMPARTIDOS PÚBLICAMENTE
|--------------------------------------------------------------------------
*/
// Lista de carpetas visibles públicamente
$publicFolders = ['js', 'css', 'images', 'img', 'uploads', 'assets', 'sounds'];

$firstSegment = explode('/', trim($requestUri, '/'))[0];

if (in_array($firstSegment, $publicFolders)) {

    $filePath = $publicDir . $requestUri;

    if (file_exists($filePath) && is_file($filePath)) {

        $ext = pathinfo($filePath, PATHINFO_EXTENSION);

        $mime = [
            'js'   => 'application/javascript',
            'css'  => 'text/css',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'svg'  => 'image/svg+xml',
            'ico'  => 'image/x-icon',
            'webp' => 'image/webp',
            'json' => 'application/json',
            'mp3'  => 'audio/mpeg',
            'pdf' => 'application/pdf',
        ];

        header("Content-Type: " . ($mime[$ext] ?? "application/octet-stream"));
        readfile($filePath);
        exit;
    }

    http_response_code(404);
    exit("Archivo no encontrado");
}

// Inicializar router
$router = new RouteCollector;

/*
|--------------------------------------------------------------------------
| RUTAS WEB (vistas)
|--------------------------------------------------------------------------
*/
$router->get('/', function() {
    requireRole([ROLE_ADMIN, ROLE_MESERO, ROLE_COCINA, ROLE_CAJERO]);
    $rol = currentRole();
    require __DIR__ . "/views/index.php";
});

require __DIR__ . "/routes/web/auth.php";
require __DIR__ . "/routes/web/productos.php";
require __DIR__ . "/routes/web/usuarios.php";
require __DIR__ . "/routes/web/configuracion.php";
require __DIR__ . "/routes/web/ventas.php";
require __DIR__ . "/routes/web/facturas.php";

/*
|--------------------------------------------------------------------------
| RUTAS API (API REST)
|--------------------------------------------------------------------------
*/
require __DIR__ . "/routes/api/productos.php";
require __DIR__ . "/routes/api/usuarios.php";
require __DIR__ . "/routes/api/roles.php";
require __DIR__ . "/routes/api/facturas.php";
require __DIR__ . "/routes/api/ventas.php";

// ===============================
//     404 si no coincide nada
// ===============================

$dispatcher = new Dispatcher($router->getData());

try {
    echo $dispatcher->dispatch($_SERVER["REQUEST_METHOD"], $path);
} catch (Exception $e) {
    http_response_code(404);
    require __DIR__ . "/views/404.php";
}