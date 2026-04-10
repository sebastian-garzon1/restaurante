<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProductoController
{
    public function __construct(
        private ProductoModel  $productoModel,
        private AuthMiddleware $auth
    ) {}

    // ── VISTAS WEB ──────────────────────────────────────────
    public function index(): void
    {
        $this->auth->requireRole([ROLE_ADMIN, ROLE_MESERO, ROLE_COCINA]);
        $rol       = $this->auth->currentRole();
        $productos = $this->productoModel->getAllDesc();
        require __DIR__ . '/../../views/productos.php';
    }

    public function ventas(): void
    {
        $this->auth->requireRole([ROLE_ADMIN]);
        $rol    = $this->auth->currentRole();
        $ventas = $this->productoModel->getVentasPorProducto([
            'desde' => $_GET['desde'] ?? null,
            'hasta' => $_GET['hasta'] ?? null,
            'q'     => $_GET['q']     ?? null,
        ]);
        require __DIR__ . '/../../views/productos_ventas.php';
    }

    // ── EXCEL ───────────────────────────────────────────────
    public function plantilla(): void
    {
        $this->auth->isLoggedIn();
        $spreadsheet = new Spreadsheet();

        $ws = $spreadsheet->getActiveSheet();
        $ws->setTitle('Instrucciones');
        $ws->setCellValue('A1', 'PLANTILLA DE PRODUCTOS');
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $ws->setCellValue('A2', '1) No cambie los encabezados de la hoja "Productos".');
        $ws->setCellValue('A3', '2) Columnas obligatorias: codigo, nombre. Los precios pueden ser 0.');
        $ws->setCellValue('A4', '3) Use punto como decimal (ej: 1234.56).');
        $ws->setCellValue('A5', '4) El código debe ser único; si existe se actualizará.');
        $ws->getColumnDimension('A')->setWidth(80);

        $sheetProd = $spreadsheet->createSheet();
        $sheetProd->setTitle('Productos');
        $sheetProd->fromArray(['codigo', 'nombre', 'precio_unidad'], null, 'A1');
        $sheetProd->fromArray([
            ['P001', 'Asado argentino', 65000],
            ['P002', 'CocaCola 400ml', 5000],
            ['P003', 'Bife de chorizo', 55000],
        ], null, 'A2');
        $sheetProd->freezePane('A2');

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="plantilla_productos.xlsx"');
        (new Xlsx($spreadsheet))->save('php://output');
        exit;
    }

    public function importar(): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');

        if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['error' => 'Archivo requerido']);
            return;
        }

        try {
            $spreadsheet = IOFactory::load($_FILES['archivo']['tmp_name']);
            $sheet       = $spreadsheet->getSheetByName('Productos') ?? $spreadsheet->getSheet(0);
            $rows        = [];

            for ($i = 2; $i <= $sheet->getHighestRow(); $i++) {
                $codigo = trim((string) $sheet->getCell("A{$i}")->getValue());
                $nombre = trim((string) $sheet->getCell("B{$i}")->getValue());
                $precio = (float) $sheet->getCell("C{$i}")->getValue();
                if (!$codigo || !$nombre) continue;
                $rows[] = ['codigo' => $codigo, 'nombre' => $nombre, 'precio_unidad' => $precio];
            }

            if (empty($rows)) {
                http_response_code(400);
                echo json_encode(['error' => 'No hay registros válidos en el archivo']);
                return;
            }

            echo json_encode(['inserted' => $this->productoModel->importar($rows)]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al importar productos']);
        }
    }

    // ── API REST ────────────────────────────────────────────
    public function apiIndex(): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');
        echo json_encode($this->productoModel->getAll());
    }

    public function apiBuscar(): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');
        echo json_encode($this->productoModel->buscar($_GET['q'] ?? ''));
    }

    public function apiShow(int $id): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');
        $producto = $this->productoModel->getById($id);
        if (!$producto) {
            http_response_code(404);
            echo json_encode(['error' => 'Producto no encontrado']);
            return;
        }
        echo json_encode($producto);
    }

    public function apiBuscarInsumos(int $id): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');
        echo json_encode($this->productoModel->buscarInsumos($id, $_GET['q'] ?? ''));
    }

    public function apiStore(): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        if (empty($data['codigo']) || empty($data['nombre'])) {
            http_response_code(400);
            echo json_encode(['error' => 'El código y nombre son requeridos']);
            return;
        }

        try {
            $id = $this->productoModel->create($data);
            echo json_encode(['id' => $id, 'message' => 'Producto creado exitosamente']);
        } catch (PDOException $e) {
            http_response_code($e->errorInfo[1] === 1062 ? 400 : 500);
            echo json_encode(['error' => $e->errorInfo[1] === 1062
                ? 'Ya existe un producto con ese código'
                : 'Error al crear producto']);
        }
    }

    public function apiUpdate(int $id): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        if (empty($data['codigo']) || empty($data['nombre'])) {
            http_response_code(400);
            echo json_encode(['error' => 'El código y nombre son requeridos']);
            return;
        }

        try {
            if (!$this->productoModel->update($id, $data)) {
                http_response_code(404);
                echo json_encode(['error' => 'Producto no encontrado']);
                return;
            }
            echo json_encode(['message' => 'Producto actualizado exitosamente']);
        } catch (PDOException $e) {
            http_response_code($e->errorInfo[1] === 1062 ? 400 : 500);
            echo json_encode(['error' => $e->errorInfo[1] === 1062
                ? 'Ya existe un producto con ese código'
                : 'Error al actualizar producto']);
        }
    }

    public function apiDestroy(int $id): void
    {
        $this->auth->isLoggedIn();
        header('Content-Type: application/json');
        try {
            if (!$this->productoModel->delete($id)) {
                http_response_code(404);
                echo json_encode(['error' => 'Producto no encontrado']);
                return;
            }
            echo json_encode(['message' => 'Producto eliminado exitosamente']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al eliminar producto']);
        }
    }
}
