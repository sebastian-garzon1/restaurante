<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// ================================================================
//  FacturaController
// ================================================================
class FacturaController
{
    public function __construct(
        private FacturaModel   $facturaModel,
        private ConfiguracionModel $configModel,
        private AuthMiddleware $auth
    ) {}

    // GET /facturas  →  vista principal (SPA con JS)
    public function index(): void
    {
        $this->auth->isLoggedIn();
        require __DIR__ . '/../../views/facturas.php';
    }

    // GET /facturas/{id}/imprimir
    public function imprimir(int $id): void
    {
        $this->auth->isLoggedIn();

        $config = $this->configModel->get();
        if (!$config) {
            http_response_code(400);
            echo json_encode(['error' => 'No hay configuración de impresión']);
            return;
        }

        // Convertir BLOBs a base64
        if (!empty($config['logo_data'])) {
            $config['logo_src'] = "data:image/{$config['logo_tipo']};base64," . base64_encode($config['logo_data']);
        }
        if (!empty($config['qr_data'])) {
            $config['qr_src'] = "data:image/{$config['qr_tipo']};base64," . base64_encode($config['qr_data']);
        }

        $factura_id = $id;
        $factura    = $this->facturaModel->getParaImpresion($id);
        if (!$factura) {
            http_response_code(404);
            echo json_encode(['error' => 'Factura no encontrada']);
            return;
        }

        $detalles = $this->facturaModel->getDetallesParaImpresion($id);
        require __DIR__ . '/../../views/factura.php';
    }

    // POST /api/facturas
    public function apiStore(): void
    {
        $this->auth->isLoggedIn();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        if ( empty($data['productos'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Datos incompletos']);
            return;
        }

        try {
            $id = $this->facturaModel->create($data, $this->auth->currentUserId());
            echo json_encode(['id' => $id]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al crear factura', 'detalle' => $e->getMessage()]);
        }
    }

    // GET /api/facturas/{id}/detalles
    public function apiDetalles(int $id): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');

        $factura = $this->facturaModel->getParaImpresion($id);
        if (!$factura) {
            http_response_code(404);
            echo json_encode(['error' => 'Factura no encontrada']);
            return;
        }

        $productos = $this->facturaModel->getDetalles($id);

        echo json_encode([
            'factura' => [
                'id'           => $factura['id'],
                'fecha'        => $factura['fecha'],
                'servicio'     => (float) $factura['servicio'],
                'pago_tarjeta' => (float) $factura['pago_tarjeta'],
                'descuento'    => (float) $factura['descuento'],
                'total'        => (float) $factura['total'],
                'forma_pago'   => $factura['forma_pago'],
            ],
            'cliente' => [
                'nombre'    => $factura['cliente_nombre'],
                'direccion' => $factura['direccion'],
                'telefono'  => $factura['telefono'],
            ],
            'productos' => array_map(fn($p) => [
                'nombre'   => $p['nombre'],
                'cantidad' => (float) $p['cantidad'],
                'unidad'   => $p['unidad_medida'],
                'precio'   => (float) $p['precio_unitario'],
                'subtotal' => (float) $p['subtotal'],
            ], $productos),
        ]);
    }

    // DELETE /api/facturas/{id}
    public function apiDestroy(int $id): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');
        try {
            $this->facturaModel->delete($id);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}


// ================================================================
//  VentaController
// ================================================================
class VentaController
{
    public function __construct(
        private FacturaModel       $facturaModel,
        private ConfiguracionModel $configModel,
        private AuthMiddleware     $auth
    ) {}

    // GET /ventas
    public function index(): void
    {
        $this->auth->requireRole([ROLE_ADMIN, ROLE_CAJERO]);
        $rol    = $this->auth->currentRole();
        $ventas = $this->facturaModel->getVentas([
            'desde' => $_GET['desde'] ?? null,
            'hasta' => $_GET['hasta'] ?? null,
            'q'     => $_GET['q']     ?? null,
        ]);
        require __DIR__ . '/../../views/ventas.php';
    }

    // GET /ventas/export  →  descargar Excel
    public function export(): void
    {
        $this->auth->isLoggedIn();

        $rows   = $this->facturaModel->getParaExport([
            'desde' => $_GET['desde'] ?? null,
            'hasta' => $_GET['hasta'] ?? null,
            'q'     => $_GET['q']     ?? null,
        ]);
        $config = $this->configModel->get();

        $sheet = new Spreadsheet();
        $ws    = $sheet->getActiveSheet();
        $ws->setTitle('Ventas');

        $titulo = $config['nombre_negocio'] ?? 'Reporte de Ventas';
        $info   = ($config['direccion'] ?? '') . ' • Tel: ' . ($config['telefono'] ?? '');
        $rango  = 'Rango: ' . ($_GET['desde'] ?? '-') . ' a ' . ($_GET['hasta'] ?? '-');

        $ws->mergeCells('B1:E1');
        $ws->setCellValue('B1', $titulo);
        $ws->getStyle('B1')->getFont()->setBold(true)->setSize(16);
        $ws->getStyle('B1')->getAlignment()->setHorizontal('center');
        $ws->mergeCells('B2:E2');
        $ws->setCellValue('B2', $info);
        $ws->getStyle('B2')->getAlignment()->setHorizontal('center');
        $ws->mergeCells('B3:E3');
        $ws->setCellValue('B3', $rango);
        $ws->getStyle('B3')->getAlignment()->setHorizontal('center');

        if (!empty($config['logo_data'])) {
            $tmpLogo = sys_get_temp_dir() . '/tmp_logo.png';
            file_put_contents($tmpLogo, $config['logo_data']);
            $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
            $drawing->setPath($tmpLogo)->setHeight(60)->setCoordinates('A1')->setWorksheet($ws);
        }

        $header = ['Factura #', 'Fecha', 'Cliente', 'Forma de Pago', 'SubTotal', 'Tarjeta', 'Propina', 'Descuento', 'Total'];
        $ws->fromArray($header, null, 'A6');
        $ws->getStyle('A6:I6')->getFont()->setBold(true);
        $ws->getStyle('A6:I6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E9ECEF');

        $fila = 7;
        foreach ($rows as $r) {
            $ws->setCellValue("A{$fila}", $r['id']);
            $ws->setCellValue("B{$fila}", $r['fecha']);
            $ws->setCellValue("C{$fila}", $r['cliente']);
            $ws->setCellValue("D{$fila}", ucfirst($r['forma_pago']));
            $ws->setCellValue("E{$fila}", $r['total']);
            $ws->setCellValue("F{$fila}", $r['pago_tarjeta']);
            $ws->setCellValue("G{$fila}", $r['servicio']);
            $ws->setCellValue("H{$fila}", $r['descuento']);
            $ws->setCellValue("I{$fila}", $r['total'] + $r['pago_tarjeta'] + $r['servicio'] - $r['descuento']);
            $fila++;
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="ventas.xlsx"');
        (new Xlsx($sheet))->save('php://output');
        exit;
    }

    // GET /api/ventas/{id}
    public function apiShow(int $id): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');
        $venta = $this->facturaModel->getById($id);
        if (!$venta) {
            http_response_code(404);
            echo json_encode(['error' => 'Venta no encontrada']);
            return;
        }
        echo json_encode($venta);
    }

    // PUT /api/ventas/{id}
    public function apiUpdate(int $id): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        if (empty($input['fecha']) || empty($input['cliente_id']) || empty($input['usuario_id']) || empty($input['forma_pago'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Datos requeridos faltantes']);
            return;
        }

        if (!strtotime($input['fecha'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Formato de fecha inválido']);
            return;
        }

        try {
            $updated = $this->facturaModel->update($id, $input);
            if (!$updated) {
                http_response_code(404);
                echo json_encode(['error' => 'Factura no encontrada o sin cambios']);
                return;
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al actualizar la venta']);
        }
    }
}


// ================================================================
//  ClienteController
// ================================================================
class ClienteController
{
    public function __construct(
        private ClienteModel   $clienteModel,
        private AuthMiddleware $auth
    ) {}

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
        if (!$cliente) {
            http_response_code(404);
            echo json_encode(['error' => 'Cliente no encontrado']);
            return;
        }
        echo json_encode($cliente);
    }

    public function apiStore(): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($data['nombre'])) {
            http_response_code(400);
            echo json_encode(['error' => 'El nombre es requerido']);
            return;
        }
        try {
            $id = $this->clienteModel->create($data);
            echo json_encode(['id' => $id, 'message' => 'Cliente creado exitosamente']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al crear cliente']);
        }
    }

    public function apiUpdate(int $id): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($data['nombre'])) {
            http_response_code(400);
            echo json_encode(['error' => 'El nombre es requerido']);
            return;
        }
        try {
            if (!$this->clienteModel->update($id, $data)) {
                http_response_code(404);
                echo json_encode(['error' => 'Cliente no encontrado']);
                return;
            }
            echo json_encode(['message' => 'Cliente actualizado exitosamente']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al actualizar cliente']);
        }
    }

    public function apiDestroy(int $id): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');
        try {
            if (!$this->clienteModel->delete($id)) {
                http_response_code(404);
                echo json_encode(['error' => 'Cliente no encontrado']);
                return;
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


// ================================================================
//  RolController
// ================================================================
class RolController
{
    public function __construct(
        private RolModel       $rolModel,
        private AuthMiddleware $auth
    ) {}

    public function apiIndex(): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');
        try {
            echo json_encode($this->rolModel->getAll());
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al cargar roles']);
        }
    }
}


// ================================================================
//  ConfiguracionController
// ================================================================
class ConfiguracionController
{
    public function __construct(
        private ConfiguracionModel $configModel,
        private AuthMiddleware     $auth
    ) {}

    public function index(): void
    {
        $this->auth->requireRole([ROLE_ADMIN]);
        $rol    = $this->auth->currentRole();
        $config = $this->configModel->get();

        if (!$config) {
            $config = [
                'nombre_negocio' => '',
                'direccion'      => '',
                'telefono'       => '',
                'nit'            => '',
                'pie_pagina'     => '',
                'ancho_papel'    => 80,
                'font_size'      => 1,
            ];
        } else {
            unset($config['logo_data'], $config['qr_data']);
        }

        require __DIR__ . '/../../views/configuracion.php';
    }

    public function update(): void
    {
        $this->auth->isLoggedIn();

        $logo = (isset($_FILES['logo']) && $_FILES['logo']['size'] > 0) ? $_FILES['logo'] : null;
        $qr   = (isset($_FILES['qr'])   && $_FILES['qr']['size']   > 0) ? $_FILES['qr']   : null;
        $menu = $_FILES['menu'] ?? null;

        $this->configModel->save($_POST, $logo, $qr, $menu);

        header('Location: /configuracion');
        exit;
    }
}
