<?php

declare(strict_types=1);

class ContabilidadController
{
    public function __construct(
        private ContabilidadModel  $contabilidadModel,
        private ResponsableModel   $responsableModel,
        private AuthMiddleware     $auth
    ) {}

    // ── VISTA WEB ──────────────────────────────────────────
    public function index(): void
    {
        $this->auth->requireRole([ROLE_ADMIN, ROLE_CAJERO]);
        $rol          = $this->auth->currentRole();
        $responsables = $this->responsableModel->getAll();

        // Construcción de datos para la vista
        $egresos              = $this->contabilidadModel->getAll();
        $egresosTotal         = ['efectivo' => 0, 'transferencia' => 0, 'tarjeta' => 0, 'total' => 0];
        $traspasos            = [];
        $traspasoTotal        = ['efectivo' => ['ingreso' => 0, 'egreso' => 0], 'transferencia' => ['ingreso' => 0, 'egreso' => 0], 'tarjeta' => ['ingreso' => 0, 'egreso' => 0]];
        $ventasData           = [];
        $ventasTotal          = ['efectivo' => 0, 'transferencia' => 0, 'tarjeta' => 0, 'total' => 0];
        $totales_dia          = [];
        $propinasData         = [];
        $propinasTotal        = ['efectivo' => 0, 'transferencia' => 0, 'tarjeta' => 0, 'total' => 0];
        $propinas_totales_dia = [];
        $boldData             = [];
        $boldTotal            = 0;
        $granTotal            = ['efectivo' => 0, 'transferencia' => 0, 'tarjeta' => 0, 'total' => 0];

        // Calcular totales de egresos por método
        foreach ($egresos as $egreso) {
            $metodo = $egreso['metodo'];
            $egresosTotal[$metodo] = ($egresosTotal[$metodo] ?? 0) + $egreso['valor'];
        }
        $egresosTotal['total'] = array_sum(array_slice($egresosTotal, 0, 3));

        require __DIR__ . '/../../views/contabilidad.php';
    }

    // ── API REST ────────────────────────────────────────────
    public function apiIndex(): void
    {
        $this->auth->requireRole([ROLE_ADMIN, ROLE_CAJERO]);
        header('Content-Type: application/json');

        $filtros = [
            'fecha_desde'   => $_GET['fecha_desde'] ?? '',
            'fecha_hasta'   => $_GET['fecha_hasta'] ?? '',
            'metodo'        => $_GET['metodo']      ?? '',
        ];

        echo json_encode($this->contabilidadModel->getAll($filtros));
    }

    public function apiShow(int $id): void
    {
        $this->auth->requireRole([ROLE_ADMIN, ROLE_CAJERO]);
        header('Content-Type: application/json');
        $egreso = $this->contabilidadModel->getById($id);
        if (!$egreso) {
            http_response_code(404);
            echo json_encode(['error' => 'Egreso no encontrado']);
            return;
        }
        echo json_encode($egreso);
    }

    public function apiStore(): void
    {
        $this->auth->requireRole([ROLE_ADMIN, ROLE_CAJERO]);
        header('Content-Type: application/json');
        
        $data = $_POST ?? []; 

        if (empty($data['fecha']) || empty($data['concepto']) || empty($data['valor']) || empty($data['metodo'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Campos requeridos: fecha, concepto, valor, metodo']);
            return;
        }

        try {
            $id = $this->contabilidadModel->create($data);
            http_response_code(201);
            echo json_encode(['id' => $id, 'message' => 'Egreso registrado exitosamente']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al registrar egreso']);
        }
    }

    public function apiUpdate(int $id): void
    {
        $this->auth->requireRole([ROLE_ADMIN, ROLE_CAJERO]);
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        if (empty($data['fecha']) || empty($data['concepto']) || empty($data['valor']) || empty($data['metodo'])) {
            echo json_encode(['error' => 'Campos requeridos: fecha, concepto, valor, metodo']);
            http_response_code(400);
            return;
        }

        try {
            $updated = $this->contabilidadModel->update($id, $data);
            if (!$updated) {
                echo json_encode(['error' => 'Egreso no encontrado']);
                http_response_code(404);
                return;
            }
            echo json_encode(['message' => 'Egreso actualizado exitosamente']);
        } catch (Exception $e) {
            echo json_encode(['error' => 'Error al actualizar egreso']);
            http_response_code(500);
        }
    }

    public function apiDelete(int $id): void
    {
        $this->auth->requireRole([ROLE_ADMIN]);
        header('Content-Type: application/json');
        try {
            $deleted = $this->contabilidadModel->delete($id);
            if (!$deleted) {
                http_response_code(404);
                echo json_encode(['error' => 'Egreso no encontrado']);
                return;
            }
            echo json_encode(['message' => 'Egreso eliminado exitosamente']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al eliminar egreso']);
        }
    }

    public function apiResumen(): void
    {
        $this->auth->requireRole([ROLE_ADMIN, ROLE_CAJERO]);
        header('Content-Type: application/json');
        $fecha_desde = $_GET['fecha_desde'] ?? '';
        $fecha_hasta = $_GET['fecha_hasta'] ?? '';
        echo json_encode($this->contabilidadModel->getResumenPorMetodo($fecha_desde, $fecha_hasta));
    }

    public function apiTotal(): void
    {
        $this->auth->requireRole([ROLE_ADMIN, ROLE_CAJERO]);
        header('Content-Type: application/json');
        $fecha_desde = $_GET['fecha_desde'] ?? '';
        $fecha_hasta = $_GET['fecha_hasta'] ?? '';
        $total = $this->contabilidadModel->getTotalPorPeriodo($fecha_desde, $fecha_hasta);
        echo json_encode(['total' => $total]);
    }

    // ── API TRASPASOS ──────────────────────────
    public function apiAllTraspasos(): void
    {
        $this->auth->requireRole([ROLE_ADMIN, ROLE_CAJERO]);
        header('Content-Type: application/json');

        $filtros = [
            'fecha_desde' => $_GET['fecha_desde'] ?? '',
            'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
        ];

        echo json_encode($this->contabilidadModel->getAllTraspasos($filtros));
    }

    public function apiShowTraspaso(int $id): void
    {
        $this->auth->requireRole([ROLE_ADMIN, ROLE_CAJERO]);
        header('Content-Type: application/json');
        $traspaso = $this->contabilidadModel->getTraspasoById($id);
        if (!$traspaso) {
            http_response_code(404);
            echo json_encode(['error' => 'Traspaso no encontrado']);
            return;
        }
        echo json_encode($traspaso);
    }

    public function apiStoreTraspaso(): void
    {
        $this->auth->requireRole([ROLE_ADMIN, ROLE_CAJERO]);
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        if (empty($data['fecha']) || empty($data['origen']) || empty($data['destino']) || empty($data['valor'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Campos requeridos: fecha, origen, destino, valor']);
            return;
        }

        try {
            $id = $this->contabilidadModel->createTraspaso($data);
            http_response_code(201);
            echo json_encode(['id' => $id, 'message' => 'Traspaso creado exitosamente']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al crear traspaso']);
        }
    }

    public function apiUpdateTraspaso(int $id): void
    {
        $this->auth->requireRole([ROLE_ADMIN, ROLE_CAJERO]);
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        if (empty($data['fecha']) || empty($data['origen']) || empty($data['destino']) || empty($data['valor'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Campos requeridos: fecha, origen, destino, valor']);
            return;
        }

        try {
            $updated = $this->contabilidadModel->updateTraspaso($id, $data);
            if (!$updated) {
                http_response_code(404);
                echo json_encode(['error' => 'Traspaso no encontrado']);
                return;
            }
            echo json_encode(['message' => 'Traspaso actualizado exitosamente']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al actualizar traspaso']);
        }
    }

    public function apiDeleteTraspaso(int $id): void
    {
        $this->auth->requireRole([ROLE_ADMIN]);
        header('Content-Type: application/json');
        try {
            $deleted = $this->contabilidadModel->deleteTraspaso($id);
            if (!$deleted) {
                http_response_code(404);
                echo json_encode(['error' => 'Traspaso no encontrado']);
                return;
            }
            echo json_encode(['message' => 'Traspaso eliminado exitosamente']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al eliminar traspaso']);
        }
    }
}
