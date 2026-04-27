<?php

declare(strict_types=1);

class ProductoVentaController
{
    public function __construct(
        private ProductoVentaModel $productoVentaModel,
        private AuthMiddleware     $auth
    ) {}
 
    // ── VISTA WEB ──────────────────────────────────────────────
    public function index(): void
    {
        $this->auth->requireRole([ROLE_ADMIN]);
        $rol = $this->auth->currentRole();
 
        $ventas = $this->productoVentaModel->getReporte([
            'desde' => $_GET['desde'] ?? null,
            'hasta' => $_GET['hasta'] ?? null,
            'q'     => $_GET['q']     ?? null,
        ]);
 
        require __DIR__ . '/../../views/productos_ventas.php';
    }
}