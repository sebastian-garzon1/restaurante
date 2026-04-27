<?php

declare(strict_types=1);

class CocinaController
{
    public function __construct(
        private CocinaModel    $cocinaModel,
        private AuthMiddleware $auth
    ) {}
 
    // ── VISTA WEB ──────────────────────────────────────────────
    public function index(): void
    {
        $this->auth->requireRole([ROLE_ADMIN, ROLE_COCINA]);
        $rol = $this->auth->currentRole();
        require __DIR__ . '/../../views/cocina.php';
    }
 
    // ── API REST ────────────────────────────────────────────────
 
    // GET /api/cocina/cola
    public function apiCola(): void
    {
        $this->auth->isLoggedIn();
        $tipo  = $_GET['tipo'] ?? null;
        $items = $this->cocinaModel->getCola($tipo);
        echo json_encode($items);
    }
 
    // PUT /api/cocina/item/{id}/estado
    public function apiEstado(int $id): void
    {
        $this->auth->isLoggedIn();
        $data   = json_decode(file_get_contents('php://input'), true) ?? [];
        $estado = $data['estado'] ?? '';
        try {
            if (!$this->cocinaModel->actualizarEstado($id, $estado)) {
                http_response_code(404);
                echo json_encode(['error' => 'Item no encontrado o estado no válido']);
                return;
            }
            echo json_encode(['message' => 'Estado actualizado']);
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al actualizar estado']);
        }
    }
}