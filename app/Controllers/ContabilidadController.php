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

        // =========================================================
        // FILTRO DE FECHAS (Lógica original adaptada para los modelos)
        // =========================================================
        if (!empty($_GET['desde']) && !empty($_GET['hasta'])) {
            $fecha_desde_sql = $_GET['desde'] . ' 00:00:00';
            $fecha_hasta_sql = $_GET['hasta'] . ' 23:59:59';
            
            // Filtros en formato Y-m-d para pasárselos a los métodos del modelo
            $filtrosModel = [
                'fecha_desde' => $_GET['desde'],
                'fecha_hasta' => $_GET['hasta']
            ];
        } else {
            $fecha_desde_sql = '2025-12-01 00:00:00';
            $fecha_hasta_sql = date('Y-m-d') . ' 23:59:59';
            
            $filtrosModel = [
                'fecha_desde' => '2025-12-01',
                'fecha_hasta' => date('Y-m-d')
            ];
        }

        // =========================================================
        // CARGA DE DATOS DESDE EL MODELO
        // =========================================================
        $egresos   = $this->contabilidadModel->getAll($filtrosModel);
        $traspasos = $this->contabilidadModel->getAllTraspasos($filtrosModel);
        $ventas    = $this->contabilidadModel->getVentasAgrupadas($fecha_desde_sql, $fecha_hasta_sql);

        // =========================================================
        // ESTRUCTURAS DE DATOS PARA LA VISTA
        // =========================================================
        $ventasData     = [];
        $totales_dia    = [];
        $propinasData   = [];
        $propinas_totales_dia = [];
        $boldData   = [];
        $bold_totales_dia    = [];

        $ventasTotal = [
            "efectivo" => 0,
            "transferencia" => 0,
            "tarjeta" => 0,
            "total" => 0,
        ];

        $propinasTotal = [
            "efectivo" => 0,
            "transferencia" => 0,
            "tarjeta" => 0,
            "total" => 0,
        ];

        $boldTotal = 0;

        // =========================================================
        // CÁLCULOS: VENTAS ⚡
        // =========================================================
        foreach ($ventas as $row) {
            if ($row["metodo"] == "efectivo") {
                $ventasTotal["efectivo"] += $row["valor"];
            } else if ($row["metodo"] == "transferencia") {
                $ventasTotal["transferencia"] += $row["valor"];
            } else if ($row["metodo"] == "tarjeta") {
                $ventasTotal["tarjeta"] += $row["valor"];
            }

            $ventasTotal["total"] += $row["valor"];

            $ventasData[$row['dia']][] = $row;
            @$totales_dia[$row['dia']] += $row['valor'];

            // Servicio / Propinas
            if ($row["servicio"] > 0) {
                if ($row["metodo"] == "efectivo") {
                    $propinasTotal["efectivo"] += $row["servicio"];
                } else if ($row["metodo"] == "transferencia") {
                    $propinasTotal["transferencia"] += $row["servicio"];
                } else if ($row["metodo"] == "tarjeta") {
                    $propinasTotal["tarjeta"] += $row["servicio"];
                }

                $propinasTotal["total"] += $row["servicio"];

                $propinasData[$row['dia']][] = $row;
                @$propinas_totales_dia[$row['dia']] += $row['servicio'];
            }

            // Tarjeta (Bold)
            if ($row["pago_tarjeta"] > 0) {
                $row["pago_tarjeta"] *= (0.95 / 5);
                $boldTotal += $row["pago_tarjeta"];

                $boldData[$row['dia']][] = $row;
                @$bold_totales_dia[$row['dia']] += $row['pago_tarjeta'];
            }
        }

        // =========================================================
        // CÁLCULOS: EGRESOS ⚡
        // =========================================================
        $egresosTotal = [
            "efectivo" => 0,
            "transferencia" => 0,
            "tarjeta" => 0,
            "total" => 0,
        ];

        foreach ($egresos as $row) {
            if ($row["metodo"] == "efectivo") {
                $egresosTotal["efectivo"] += $row["valor"];
            } else if ($row["metodo"] == "transferencia") {
                $egresosTotal["transferencia"] += $row["valor"];
            } else if ($row["metodo"] == "tarjeta") {
                $egresosTotal["tarjeta"] += $row["valor"];
            }
            $egresosTotal["total"] += $row["valor"];
        }

        // =========================================================
        // CÁLCULOS: TRASPASOS ⚡
        // =========================================================
        $traspasoTotal = [
            "efectivo" => ["ingreso" => 0, "egreso" => 0],
            "transferencia" => ["ingreso" => 0, "egreso" => 0],
            "tarjeta" => ["ingreso" => 0, "egreso" => 0]
        ];

        foreach ($traspasos as $row) {
            // Ingresos
            if ($row["destino"] == "efectivo") {
                $traspasoTotal["efectivo"]["ingreso"] += $row["valor"];
            } else if ($row["destino"] == "transferencia") {
                $traspasoTotal["transferencia"]["ingreso"] += $row["valor"];
            } else if ($row["destino"] == "tarjeta") {
                $traspasoTotal["tarjeta"]["ingreso"] += $row["valor"];
            }
        
            // Egresos
            if ($row["origen"] == "efectivo") {
                $traspasoTotal["efectivo"]["egreso"] += $row["valor"];
            } else if ($row["origen"] == "transferencia") {
                $traspasoTotal["transferencia"]["egreso"] += $row["valor"];
            } else if ($row["origen"] == "tarjeta") {
                $traspasoTotal["tarjeta"]["egreso"] += $row["valor"];
            }
        }

        // =========================================================
        // CÁLCULOS: TOTALES FINALES
        // =========================================================
        $granTotal = [
            "efectivo" => 0,
            "transferencia" => 0,
            "tarjeta" => 0,
            "total" => 0
        ];

        $granTotal["efectivo"] = $ventasTotal["efectivo"] - $egresosTotal["efectivo"] + $traspasoTotal["efectivo"]["ingreso"] - $traspasoTotal["efectivo"]["egreso"] + $propinasTotal["efectivo"];
        $granTotal["transferencia"] = $ventasTotal["transferencia"] - $egresosTotal["transferencia"] + $traspasoTotal["transferencia"]["ingreso"] - $traspasoTotal["transferencia"]["egreso"] + $propinasTotal["transferencia"];
        $granTotal["tarjeta"] = $ventasTotal["tarjeta"] - $egresosTotal["tarjeta"] + $traspasoTotal["tarjeta"]["ingreso"] - $traspasoTotal["tarjeta"]["egreso"] + $propinasTotal["tarjeta"] + $boldTotal;
        $granTotal["total"] = $granTotal["efectivo"] + $granTotal["transferencia"] + $granTotal["tarjeta"];

        // Carga de la vista
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
