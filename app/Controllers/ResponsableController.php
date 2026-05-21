<?php

declare(strict_types=1);

class ResponsableController
{
    public function __construct(
        private ResponsableModel  $responsableModel,
        private AuthMiddleware    $auth
    ) {}

    // ── VISTA WEB ──────────────────────────────────────────
    public function index(): void
    {
        $this->auth->requireRole([ROLE_ADMIN]);
        $rol          = $this->auth->currentRole();
        $responsables = $this->responsableModel->getAll();
        require __DIR__ . '/../../views/responsables.php';
    }

    // ── API REST ────────────────────────────────────────────
    public function apiIndex(): void
    {
        $this->auth->requireRole([ROLE_ADMIN]);
        header('Content-Type: application/json');
        echo json_encode($this->responsableModel->getAll());
    }

    public function apiShow(int $id): void
    {
        $this->auth->requireRole([ROLE_ADMIN]);
        header('Content-Type: application/json');
        $responsable = $this->responsableModel->getById($id);
        if (!$responsable) {
            http_response_code(404);
            echo json_encode(['error' => 'Responsable no encontrado']);
            return;
        }
        echo json_encode($responsable);
    }

    public function apiStore(): void
    {
        $this->auth->requireRole([ROLE_ADMIN]);
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        if (empty($data['nombre'])) {
            http_response_code(400);
            echo json_encode(['error' => 'El nombre es requerido']);
            return;
        }

        try {
            $id = $this->responsableModel->create($data);
            http_response_code(201);
            echo json_encode(['id' => $id, 'message' => 'Responsable creado exitosamente']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al crear responsable']);
        }
    }

    public function apiUpdate(int $id): void
    {
        $this->auth->requireRole([ROLE_ADMIN]);
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        if (empty($data['nombre'])) {
            http_response_code(400);
            echo json_encode(['error' => 'El nombre es requerido']);
            return;
        }

        try {
            $updated = $this->responsableModel->update($id, $data);
            if (!$updated) {
                http_response_code(404);
                echo json_encode(['error' => 'Responsable no encontrado']);
                return;
            }
            echo json_encode(['message' => 'Responsable actualizado exitosamente']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al actualizar responsable']);
        }
    }

    public function apiDelete(int $id): void
    {
        $this->auth->requireRole([ROLE_ADMIN]);
        header('Content-Type: application/json');
        try {
            $deleted = $this->responsableModel->delete($id);
            if (!$deleted) {
                http_response_code(404);
                echo json_encode(['error' => 'Responsable no encontrado']);
                return;
            }
            echo json_encode(['message' => 'Responsable eliminado exitosamente']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al eliminar responsable']);
        }
    }
}
