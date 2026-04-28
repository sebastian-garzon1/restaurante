<?php

session_start();
require 'vendor/autoload.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/roles.php';

date_default_timezone_set('America/Bogota');

// ── Autoload de clases MVC ──────────────────────────────────
require_once __DIR__ . '/app/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/app/Models/UsuarioModel.php';
require_once __DIR__ . '/app/Models/ProductoModel.php';
require_once __DIR__ . '/app/Models/FacturaModel.php';
require_once __DIR__ . '/app/Models/RolModel.php';       // incluye ConfiguracionModel
require_once __DIR__ . '/app/Models/MesaModel.php';   
require_once __DIR__ . '/app/Models/InsumoModel.php';
require_once __DIR__ . '/app/Models/CocinaModel.php';
require_once __DIR__ . '/app/Models/ClienteModel.php';
require_once __DIR__ . '/app/Models/ProductoVentaModel.php';
require_once __DIR__ . '/app/Controllers/AuthController.php';
require_once __DIR__ . '/app/Controllers/UsuarioController.php';
require_once __DIR__ . '/app/Controllers/ProductoController.php';
require_once __DIR__ . '/app/Controllers/ClienteController.php'; 
require_once __DIR__ . '/app/Controllers/CocinaController.php'; 
require_once __DIR__ . '/app/Controllers/InventarioController.php'; 
require_once __DIR__ . '/app/Controllers/MesaController.php';
require_once __DIR__ . '/app/Controllers/ProductoVentaController.php';
require_once __DIR__ . '/app/Controllers/Controllers.php'; // todos los demás

// ── Instanciar dependencias ─────────────────────────────────
$auth               = new AuthMiddleware();
$usuarioModel       = new UsuarioModel($pdo);
$productoModel      = new ProductoModel($pdo);
$facturaModel       = new FacturaModel($pdo);
$rolModel           = new RolModel($pdo);
$configModel        = new ConfiguracionModel($pdo);
$mesaModel          = new MesaModel($pdo);
$pedidoModel        = new PedidoModel($pdo);
$insumoModel        = new InsumoModel($pdo);
$cocinaModel        = new CocinaModel($pdo);
$clienteModel       = new ClienteModel($pdo);
$productoVentaModel = new ProductoVentaModel($pdo);

$configModel->initIfEmpty(); // Crea configuración por defecto si no existe

// ── Instanciar controladores ────────────────────────────────
$authCtrl           = new AuthController($usuarioModel, $auth);
$usuarioCtrl        = new UsuarioController($usuarioModel, $auth);
$productoCtrl       = new ProductoController($productoModel, $auth);
$facturaCtrl        = new FacturaController($facturaModel, $configModel, $auth);
$ventaCtrl          = new VentaController($facturaModel, $configModel, $auth);
$rolCtrl            = new RolController($rolModel, $auth);
$configCtrl         = new ConfiguracionController($configModel, $auth);
$mesaCtrl           = new MesaController($mesaModel, $pedidoModel, $auth);
$inventarioCtrl     = new InventarioController($insumoModel, $auth);
$cocinaCtrl         = new CocinaController($cocinaModel, $auth);
$clienteCtrl        = new ClienteController($clienteModel, $auth);
$productoVentaCtrl  = new ProductoVentaController($productoVentaModel, $auth);

use Phroute\Phroute\RouteCollector;
use Phroute\Phroute\Dispatcher;

// ── Servir archivos estáticos ───────────────────────────────
$publicFolders = ['js', 'css', 'images', 'img', 'uploads', 'assets', 'sounds'];
$requestUri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$firstSegment  = explode('/', trim($requestUri, '/'))[0];

if (in_array($firstSegment, $publicFolders)) {
    $filePath = __DIR__ . '/public' . $requestUri;
    if (file_exists($filePath) && is_file($filePath)) {
        $mime = [
            'js' => 'application/javascript', 'css' => 'text/css',
            'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon',
            'webp' => 'image/webp', 'mp3' => 'audio/mpeg', 'pdf' => 'application/pdf',
        ];
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        header('Content-Type: ' . ($mime[$ext] ?? 'application/octet-stream'));
        readfile($filePath);
        exit;
    }
    http_response_code(404);
    exit('Archivo no encontrado');
}

// ── Router ──────────────────────────────────────────────────
$router = new RouteCollector;

// Dashboard
$router->get('/', function() use ($auth) {
    $auth->requireRole([ROLE_ADMIN, ROLE_MESERO, ROLE_COCINA, ROLE_CAJERO]);
    $rol = $_SESSION['user']['rol'];
    require __DIR__ . '/views/index.php';
});

// ── Auth ────────────────────────────────────────────────────
$router->get('/login',  fn() => $authCtrl->showLogin());
$router->post('/auth',  fn() => $authCtrl->login());
$router->get('/logout', fn() => $authCtrl->logout());
$router->get('/restore_password', fn() => $authCtrl->restorePass());
$router->post('/restore_password', fn() => $authCtrl->restorePassConfirm());

// ── Usuarios ────────────────────────────────────────────────
$router->get('/usuarios',               fn() => $usuarioCtrl->index());
$router->get('/api/usuarios',           fn() => $usuarioCtrl->apiIndex());
$router->get('/api/usuarios/{id:\d+}',  fn($id) => $usuarioCtrl->apiShow((int)$id));
$router->post('/api/usuarios',          fn() => $usuarioCtrl->apiStore());
$router->put('/api/usuarios/{id:\d+}',  fn($id) => $usuarioCtrl->apiUpdate((int)$id));
$router->delete('/api/usuarios/{id:\d+}', fn($id) => $usuarioCtrl->apiDestroy((int)$id));

// ── Productos ────────────────────────────────────────────────
$router->get('/productos',                          fn() => $productoCtrl->index());
$router->get('/productos/ventas',                   fn() => $productoCtrl->ventas());
$router->get('/productos/plantilla',                fn() => $productoCtrl->plantilla());
$router->post('/productos/importar',                fn() => $productoCtrl->importar());
$router->get('/api/productos',                      fn() => $productoCtrl->apiIndex());
$router->get('/api/productos/buscar',               fn() => $productoCtrl->apiBuscar());
$router->get('/api/productos/{id:\d+}',             fn($id) => $productoCtrl->apiShow((int)$id));
$router->get('/api/productos/insumos/buscar/{id:\d+}', fn($id) => $productoCtrl->apiBuscarInsumos((int)$id));
$router->post('/api/productos',                     fn() => $productoCtrl->apiStore());
$router->put('/api/productos/{id:\d+}',             fn($id) => $productoCtrl->apiUpdate((int)$id));
$router->delete('/api/productos/{id:\d+}',          fn($id) => $productoCtrl->apiDestroy((int)$id));

// ── Facturas ─────────────────────────────────────────────────
$router->get('/facturas',                    fn() => $facturaCtrl->index());
$router->get('/facturas/{id:\d+}/imprimir',  fn($id) => $facturaCtrl->imprimir((int)$id));
$router->post('/api/facturas',               fn() => $facturaCtrl->apiStore());
$router->get('/api/facturas/{id}/detalles',  fn($id) => $facturaCtrl->apiDetalles((int)$id));
$router->delete('/api/facturas/{id:\d+}',    fn($id) => $facturaCtrl->apiDestroy((int)$id));

// ── Ventas ───────────────────────────────────────────────────
$router->get('/ventas',              fn() => $ventaCtrl->index());
$router->get('/ventas/export',       fn() => $ventaCtrl->export());
$router->get('/api/ventas/{id:\d+}', fn($id) => $ventaCtrl->apiShow((int)$id));
$router->put('/api/ventas/{id:\d+}', fn($id) => $ventaCtrl->apiUpdate((int)$id));

// ── Roles ────────────────────────────────────────────────────
$router->get('/api/roles', fn() => $rolCtrl->apiIndex());

// ── Mesas ────────────────────────────────────────────────────
$router->get('/mesas', fn() => $mesaCtrl->index());
$router->get('/api/mesas/listar',                           fn() => $mesaCtrl->apiListar());
$router->post('/api/mesas/crear',                           fn() => $mesaCtrl->apiCrear());
$router->post('/api/mesas/abrir',                           fn() => $mesaCtrl->apiAbrir());
$router->delete('/api/mesas/eliminar',                      fn() => $mesaCtrl->apiEliminar());
$router->get('/api/mesas/{id:\d+}',                         fn($id) => $mesaCtrl->apiShow((int)$id));
$router->put('/api/mesas/{id:\d+}',                         fn($id) => $mesaCtrl->apiUpdate((int)$id));
$router->put('/api/mesas/{id:\d+}/liberar',                 fn($id) => $mesaCtrl->apiLiberar((int)$id));

$router->get('/api/mesas/pedidos/{id:\d+}',                 fn($id) => $mesaCtrl->apiGetPedido((int)$id));
$router->post('/api/mesas/pedidos/{id:\d+}/items',          fn($id) => $mesaCtrl->apiAgregarItem((int)$id));
$router->post('/api/mesas/pedidos/{id:\d+}/preview-factura',fn($id) => $mesaCtrl->apiPreviewFactura((int)$id));
$router->post('/api/mesas/pedidos/{id:\d+}/facturar',       fn($id) => $mesaCtrl->apiFacturar((int)$id));
$router->put('/api/mesas/pedidos/{id:\d+}/mover',           fn($id) => $mesaCtrl->apiMover((int)$id));

$router->delete('/api/mesas/pedidos/{id:\d+}/items',        fn($id) => $mesaCtrl->apiEliminarItem((int)$id));
$router->put('/api/mesas/items/{id:\d+}/enviar',            fn($id) => $mesaCtrl->apiEnviarItem((int)$id));
$router->put('/api/mesas/items/{id:\d+}/estado',            fn($id) => $mesaCtrl->apiEstadoItem((int)$id));

// ── Inventario ────────────────────────────────────────────────────
$router->get('/inventario', fn() => $inventarioCtrl->index());
$router->get('/api/inventario',                             fn() => $inventarioCtrl->apiIndex());
$router->get('/api/inventario/buscar',                      fn() => $inventarioCtrl->apiBuscar());
$router->post('/api/inventario/insumo_producto',            fn() => $inventarioCtrl->apiAsignar());
$router->put('/api/inventario/insumo_producto/',            fn() => $inventarioCtrl->apiActualizarCantidad());
$router->post('/api/inventario/disponibilidad/',            fn() => $inventarioCtrl->apiDisponibilidad());
$router->get('/api/inventario/insumos/{id:\d+}',            fn($id) => $inventarioCtrl->apiInsumosPorProducto((int)$id));
$router->get('/api/inventario/{id:\d+}',                    fn($id) => $inventarioCtrl->apiShow((int)$id));
$router->post('/api/inventario',                            fn() => $inventarioCtrl->apiStore());
$router->put('/api/inventario/{id:\d+}',                    fn($id) => $inventarioCtrl->apiUpdate((int)$id));
$router->delete('/api/inventario/{id:\d+}',                 fn($id) => $inventarioCtrl->apiDestroy((int)$id));
$router->delete('/api/inventario/insumo_producto/{id:\d+}', fn($id) => $inventarioCtrl->apiDesasignar((int)$id));

// ── Cocina ────────────────────────────────────────
$router->get('/cocina', fn() => $cocinaCtrl->index());
$router->get('/api/cocina/cola',                            fn() => $cocinaCtrl->apiCola());
$router->put('/api/cocina/item/{id:\d+}/estado',            fn($id) => $cocinaCtrl->apiEstado((int)$id));

// ── Clientes ────────────────────────────────────────
$router->get('/clientes', fn() => $clienteCtrl->index());
$router->get('/api/clientes',                               fn() => $clienteCtrl->apiIndex());
$router->get('/api/clientes/buscar',                        fn() => $clienteCtrl->apiBuscar());
$router->get('/api/clientes/{id}',                          fn($id) => $clienteCtrl->apiShow((int)$id));
$router->post('/api/clientes',                              fn() => $clienteCtrl->apiStore());
$router->put('/api/clientes/{id}',                          fn($id) => $clienteCtrl->apiUpdate((int)$id));
$router->delete('/api/clientes/{id}',                       fn($id) => $clienteCtrl->apiDestroy((int)$id));

// ── Configuración ────────────────────────────────────────────
$router->get('/configuracion',  fn() => $configCtrl->index());
$router->post('/configuracion', fn() => $configCtrl->update());

// ── Páginas de error ─────────────────────────────────────────
$router->get('/error', function() {
    http_response_code(403);
    require __DIR__ . '/views/error.php';
});

// ── Dispatch ─────────────────────────────────────────────────
$path       = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') ?: '/';
$dispatcher = new Dispatcher($router->getData());

try {
    echo $dispatcher->dispatch($_SERVER['REQUEST_METHOD'], $path);
} catch (Exception $e) {
    http_response_code(404);
    require __DIR__ . '/views/404.php';
}