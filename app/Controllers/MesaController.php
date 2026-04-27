<?php

declare(strict_types=1);

class MesaController
{
    public function __construct(
        private MesaModel    $mesaModel,
        private PedidoModel  $pedidoModel,
        private AuthMiddleware $auth
    ) {}
 
    // ── VISTA WEB ──────────────────────────────────────────────
    public function index(): void
    {
        $this->auth->requireRole([ROLE_ADMIN, ROLE_CAJERO, ROLE_MESERO]);
        $rol   = $this->auth->currentRole();
        $mesas = $this->mesaModel->getAll();
        require __DIR__ . '/../../views/mesas.php';
    }
 
    // ── API REST ────────────────────────────────────────────────
 
    // GET /api/mesas/{id}
    public function apiShow(int $id): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');
        $mesa = $this->mesaModel->getById($id);
        if (!$mesa) { http_response_code(408); echo json_encode(['error' => 'Mesa no encontrada']); return; }
        echo json_encode($mesa);
    }
 
    // GET /api/mesas/listar
    public function apiListar(): void
    {
        $this->auth->isLoggedIn();
        echo json_encode($this->mesaModel->getAll());
    }
 
    // POST /api/mesas/crear
    public function apiCrear(): void
    {
        $this->auth->isLoggedIn();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($data['numero'])) { echo json_encode(['error' => 'El número de mesa es requerido']); return; }
        try {
            echo json_encode(['id' => $this->mesaModel->create($data)]);
        } catch (Exception $e) {
            echo json_encode(['error' => 'Error al crear mesa']);
        }
    }
 
    // PUT /api/mesas/{id}
    public function apiUpdate(int $id): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if (!$id || empty($data['numero'])) { echo json_encode(['error' => 'ID y número de mesa son requeridos']); return; }
        try {
            $this->mesaModel->update($id, $data);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['error' => 'Error al editar mesa']);
        }
    }
 
    // DELETE /api/mesas/eliminar
    public function apiEliminar(): void
    {
        $this->auth->isLoggedIn();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $id   = (int) ($data['id'] ?? 0);
        if (!$id) { echo json_encode(['error' => 'ID de mesa requerido']); return; }
        try {
            $this->mesaModel->delete($id);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['error' => 'Error al eliminar mesa']);
        }
    }
 
    // POST /api/mesas/abrir
    public function apiAbrir(): void
    {
        $this->auth->isLoggedIn();
        $data      = json_decode(file_get_contents('php://input'), true) ?? [];
        $mesaId    = (int) ($data['mesa_id']    ?? 0);
        $clienteId = isset($data['cliente_id']) ? (int) $data['cliente_id'] : null;
        $notas     = $data['notas'] ?? null;
        if (!$mesaId) { echo json_encode(['error' => 'mesa_id requerido']); return; }
        try {
            $pedido = $this->pedidoModel->abrir($mesaId, $clienteId, $notas);
            echo json_encode(['pedido' => $pedido]);
        } catch (Exception $e) {
            echo json_encode(['error' => 'Error al abrir pedido']);
        }
    }
 
    // GET /api/mesas/pedidos/{id}
    public function apiGetPedido(int $id): void
    {
        $this->auth->isLoggedIn();
        $pedido = $this->pedidoModel->getById($id);
        if (!$pedido) { echo json_encode(['error' => 'Pedido no encontrado']); return; }
        $items = $this->pedidoModel->getItems($id);
        echo json_encode(['pedido' => $pedido, 'items' => $items]);
    }
 
    // POST /api/mesas/pedidos/{id}/items
    public function apiAgregarItem(int $id): void
    {
        $this->auth->isLoggedIn();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($data['producto_id']) || empty($data['cantidad']) || empty($data['precio'])) {
            echo json_encode(['error' => 'producto_id, cantidad y precio son requeridos']);
            return;
        }
        try {
            $itemId = $this->pedidoModel->agregarItem($id, $data);
            echo json_encode(['id' => $itemId]);
        } catch (RuntimeException $e) {
            http_response_code(400);
            echo $e->getMessage(); // JSON ya embebido
        } catch (Exception $e) {
            echo json_encode(['error' => 'Error al agregar item']);
        }
    }
 
    // DELETE /api/mesas/pedidos/{id}/items
    public function apiEliminarItem(int $id): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');
        if (!$this->pedidoModel->eliminarItem($id)) {
            http_response_code(404);
            echo json_encode(['error' => 'Item no encontrado']);
            return;
        }
        echo json_encode(['message' => 'Item eliminado exitosamente']);
    }
 
    // PUT /api/mesas/items/{id}/enviar
    public function apiEnviarItem(int $id): void
    {
        $this->auth->isLoggedIn();
        if (!$this->pedidoModel->enviarItem($id)) {
            echo json_encode(['error' => 'Item no encontrado']);
            return;
        }
        echo json_encode(['message' => 'Item enviado a cocina']);
    }
 
    // PUT /api/mesas/items/{id}/estado
    public function apiEstadoItem(int $id): void
    {
        $this->auth->isLoggedIn();
        $data   = json_decode(file_get_contents('php://input'), true) ?? [];
        $estado = $data['estado'] ?? '';
        try {
            $this->pedidoModel->actualizarEstadoItem($id, $estado);
            echo json_encode(['message' => 'Estado actualizado']);
        } catch (InvalidArgumentException $e) {
            echo json_encode(['error' => $e->getMessage()]);
        } catch (Exception $e) {
            echo json_encode(['error' => 'Error al actualizar estado']);
        }
    }
 
    // POST /api/mesas/pedidos/{id}/preview-factura
    public function apiPreviewFactura(int $id): void
    {
        $this->auth->isLoggedIn();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($data['cliente_id'])) { http_response_code(400); echo json_encode(['error' => 'Cliente requerido']); return; }
        try {
            echo json_encode($this->pedidoModel->previewFactura($id, $data));
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
 
    // POST /api/mesas/pedidos/{id}/facturar
    public function apiFacturar(int $id): void
    {
        $this->auth->isLoggedIn();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($data['cliente_id'])) { echo json_encode(['error' => 'cliente_id requerido']); return; }
        if (empty($data['forma_pago'])) { echo json_encode(['error' => 'forma_pago requerido']); return; }
        try {
            $facturaId = $this->pedidoModel->facturar($id, $data, $this->auth->currentUserId());
            echo json_encode(['factura_id' => $facturaId]);
        } catch (Exception $e) {
            echo json_encode(['error' => 'Error al facturar pedido']);
        }
    }
 
    // PUT /api/mesas/pedidos/{id}/mover
    public function apiMover(int $id): void
    {
        $this->auth->isLoggedIn();
        $data      = json_decode(file_get_contents('php://input'), true) ?? [];
        $mesaDest  = (int) ($data['mesa_destino_id'] ?? 0);
        if (!$mesaDest) { echo json_encode(['error' => 'mesa_destino_id requerido']); return; }
        try {
            $result = $this->pedidoModel->mover($id, $mesaDest);
            echo json_encode(array_merge(['message' => 'Pedido movido'], $result));
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
 
    // PUT /api/mesas/{id}/liberar
    public function apiLiberar(int $id): void
    {
        $this->auth->isLoggedIn();
        try {
            $this->pedidoModel->liberarMesa($id);
            echo json_encode(['message' => 'Mesa liberada']);
        } catch (Exception $e) {
            http_response_code(409);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}