<?php

declare(strict_types=1);

class InventarioController
{
    public function __construct(
        private InsumoModel    $insumoModel,
        private AuthMiddleware $auth
    ) {}
 
    // ── VISTA WEB ──────────────────────────────────────────────
    public function index(): void
    {
        $this->auth->requireRole([ROLE_ADMIN]);
        $rol    = $this->auth->currentRole();
        $insumos = $this->insumoModel->getAll();
        require __DIR__ . '/../../views/inventario.php';
    }
 
    // ── API REST ────────────────────────────────────────────────
 
    public function apiIndex(): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');
        echo json_encode($this->insumoModel->getAll());
    }
 
    public function apiShow(int $id): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');
        $insumo = $this->insumoModel->getById($id);
        if (!$insumo) { http_response_code(404); echo json_encode(['error' => 'Insumo no encontrado']); return; }
        echo json_encode($insumo);
    }
 
    public function apiBuscar(): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');
        $id  = isset($_GET['id']) ? (int) $_GET['id'] : null;
        $q   = $_GET['q'] ?? '';
        echo json_encode($this->insumoModel->buscar($q, $id));
    }
 
    public function apiInsumosPorProducto(int $id): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');
        $insumos = $this->insumoModel->getByProducto($id);
        if (!$insumos) { http_response_code(404); echo json_encode(['error' => 'Producto no encontrado']); return; }
        echo json_encode($insumos);
    }
 
    public function apiStore(): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($data['nombre'])) { http_response_code(400); echo json_encode(['error' => 'El nombre es requerido']); return; }
        try {
            $id = $this->insumoModel->create($data);
            echo json_encode(['id' => $id, 'message' => 'Insumo creado exitosamente']);
        } catch (PDOException $e) {
            http_response_code($e->errorInfo[1] == 1062 ? 400 : 500);
            echo json_encode(['error' => $e->errorInfo[1] == 1062 ? 'Ya existe un insumo con ese código' : 'Error al crear insumo']);
        }
    }
 
    public function apiAsignar(): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');
        $data    = json_decode(file_get_contents('php://input'), true) ?? [];
        $producto = (int) ($data['producto'] ?? 0);
        $insumo   = (int) ($data['insumo']   ?? 0);
        if (!$producto || !$insumo) { http_response_code(400); echo json_encode(['error' => 'El producto y el insumo son requeridos']); return; }
        try {
            $id = $this->insumoModel->asignar($producto, $insumo);
            echo json_encode(['id' => $id, 'message' => 'Asociación creada exitosamente']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al crear insumo']);
        }
    }
 
    public function apiActualizarCantidad(): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $invId    = (int)   ($data['id_inventario'] ?? 0);
        $cantidad = (float) ($data['cantidad']      ?? 0);
        if (!$invId || !$cantidad) { http_response_code(400); echo json_encode(['error' => 'El id de inventario y la cantidad son requeridos']); return; }
        if (!$this->insumoModel->actualizarCantidad($invId, $cantidad)) {
            http_response_code(404); echo json_encode(['error' => 'Insumo no encontrado']); return;
        }
        echo json_encode(['message' => 'Insumo actualizado exitosamente']);
    }
 
    public function apiDisponibilidad(): void
    {
        $this->auth->isLoggedIn();
        $data      = json_decode(file_get_contents('php://input'), true) ?? [];
        $productoId = (int)   ($data['producto_id'] ?? 0);
        $cantidad   = (float) ($data['cantidad']    ?? 0);
        $faltantes  = $this->insumoModel->verificarDisponibilidad($productoId, $cantidad);
        if (!empty($faltantes)) {
            http_response_code(400);
            echo json_encode(['error' => 'Stock insuficiente', 'detalles' => $faltantes]);
            return;
        }
        echo json_encode(['ok' => true]);
    }
 
    public function apiUpdate(int $id): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($data['nombre'])) { http_response_code(400); echo json_encode(['error' => 'El nombre es requerido']); return; }
        if (!$this->insumoModel->update($id, $data)) {
            http_response_code(404); echo json_encode(['error' => 'Insumo no encontrado']); return;
        }
        echo json_encode(['message' => 'Insumo actualizado exitosamente']);
    }
 
    public function apiDestroy(int $id): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');
        if (!$this->insumoModel->delete($id)) {
            http_response_code(404); echo json_encode(['error' => 'Insumo no encontrado']); return;
        }
        echo json_encode(['message' => 'Insumo eliminado exitosamente']);
    }
 
    public function apiDesasignar(int $id): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');
        if (!$this->insumoModel->desasignar($id)) {
            http_response_code(404); echo json_encode(['error' => 'Insumo no encontrado']); return;
        }
        echo json_encode(['message' => 'Insumo eliminado exitosamente']);
    }
}