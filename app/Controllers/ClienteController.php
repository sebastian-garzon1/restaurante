<?php

declare(strict_types=1);

class ClienteController
{
    public function __construct(
        private ClienteModel   $clienteModel,
        private AuthMiddleware $auth
    ) {}
 
    // ── VISTA WEB ──────────────────────────────────────────────
    public function index(): void
    {
        $this->auth->requireRole([ROLE_ADMIN, ROLE_CAJERO, ROLE_MESERO]);
        $rol      = $this->auth->currentRole();
        $clientes = $this->clienteModel->getAll();
        require __DIR__ . '/../../views/clientes.php';
    }
 
    // ── API REST ────────────────────────────────────────────────
 
    public function apiIndex(): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');
        echo json_encode($this->clienteModel->getAll());
    }
 
    public function apiBuscar(): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');
        echo json_encode($this->clienteModel->buscar($_GET['q'] ?? ''));
    }
 
    public function apiShow(int $id): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');
        $cliente = $this->clienteModel->getById($id);
        if (!$cliente) { http_response_code(404); echo json_encode(['error' => 'Cliente no encontrado']); return; }
        echo json_encode($cliente);
    }
 
    public function apiStore(): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($data['nombre'])) { http_response_code(400); echo json_encode(['error' => 'El nombre es requerido']); return; }
        try {
            $id = $this->clienteModel->create($data);
            echo json_encode(['id' => $id, 'message' => 'Cliente creado exitosamente']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'Error al crear cliente', 'detalle' => $e->getMessage()]);
        }
    }
 
    public function apiUpdate(int $id): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($data['nombre'])) { http_response_code(400); echo json_encode(['error' => 'El nombre es requerido']); return; }
        if (!$this->clienteModel->update($id, $data)) {
            http_response_code(404); echo json_encode(['error' => 'Cliente no encontrado']); return;
        }
        echo json_encode(['message' => 'Cliente actualizado exitosamente']);
    }
 
    public function apiDestroy(int $id): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');
        try {
            if (!$this->clienteModel->delete($id)) {
                http_response_code(404); echo json_encode(['error' => 'Cliente no encontrado']); return;
            }
            echo json_encode(['message' => 'Cliente eliminado exitosamente']);
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1451) {
                http_response_code(400);
                echo json_encode(['error' => 'No se puede eliminar el cliente porque tiene facturas asociadas']);
                return;
            }
            http_response_code(500);
            echo json_encode(['error' => 'Error al eliminar cliente']);
        }
    }
}