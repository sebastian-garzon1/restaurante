<?php

declare(strict_types=1);

class UsuarioController
{
    public function __construct(
        private UsuarioModel   $usuarioModel,
        private AuthMiddleware $auth
    ) {}

    // ── VISTA WEB ──────────────────────────────────────────
    public function index(): void
    {
        $this->auth->requireRole([ROLE_ADMIN]);
        $rol      = $this->auth->currentRole();
        $usuarios = $this->usuarioModel->getAll();
        require __DIR__ . '/../../views/usuarios.php';
    }

    // ── API REST ────────────────────────────────────────────
    public function apiIndex(): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');
        echo json_encode($this->usuarioModel->getAll());
    }

    public function apiShow(int $id): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');
        $usuario = $this->usuarioModel->getById($id);
        if (!$usuario) {
            http_response_code(404);
            echo json_encode(['error' => 'Usuario no encontrado']);
            return;
        }
        echo json_encode($usuario);
    }

    public function apiStore(): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        if (empty($data['nombre']) || empty($data['username']) || empty($data['password']) || empty($data['rol'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Todos los campos son requeridos']);
            return;
        }

        try {
            $id = $this->usuarioModel->create($data);
            echo json_encode(['id' => $id, 'message' => 'Usuario creado exitosamente']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al crear usuario']);
        }
    }

    public function apiUpdate(int $id): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        if (empty($data['nombre']) || empty($data['username']) || empty($data['rol'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Campos requeridos faltantes']);
            return;
        }

        try {
            $updated = $this->usuarioModel->update($id, $data);
            if (!$updated) {
                http_response_code(404);
                echo json_encode(['error' => 'Usuario no encontrado']);
                return;
            }
            echo json_encode(['message' => 'Usuario actualizado exitosamente']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al actualizar usuario']);
        }
    }

    public function apiDestroy(int $id): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');
        try {
            $deleted = $this->usuarioModel->delete($id);
            if (!$deleted) {
                http_response_code(404);
                echo json_encode(['error' => 'Usuario no encontrado']);
                return;
            }
            echo json_encode(['message' => 'Usuario eliminado exitosamente']);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                http_response_code(400);
                echo json_encode(['error' => 'No se puede eliminar porque tiene registros asociados']);
                return;
            }
            http_response_code(500);
            echo json_encode(['error' => 'Error al eliminar usuario']);
        }
    }
}
