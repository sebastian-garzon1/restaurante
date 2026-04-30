<?php

declare(strict_types=1);

class FacturaModel
{
    // =========================================================
    //  ATRIBUTOS  (espejo exacto de la tabla `facturas`)
    // =========================================================
    public int     $id;
    public int     $cliente_id;
    public int     $user_id;
    public ?string $fecha        = null;
    public float   $total        = 0.0;
    public float   $pago_tarjeta = 0.0;
    public float   $servicio     = 0.0;
    public float   $descuento    = 0.0;
    public string  $forma_pago   = 'efectivo'; // enum: efectivo | transferencia | tarjeta

    // Atributos extra que vienen de JOINs
    public ?string $cliente_nombre  = null;
    public ?string $vendedor_nombre = null;

    // =========================================================
    //  CONSTRUCTOR
    // =========================================================
    public function __construct(private PDO $pdo) {}

    // =========================================================
    //  HIDRATACIÓN
    // =========================================================
    public function fill(array $data): static
    {
        $this->id             = (int)    ($data['id']             ?? 0);
        $this->cliente_id     = (int)    ($data['cliente_id']     ?? 0);
        $this->user_id        = (int)    ($data['user_id']        ?? 0);
        $this->fecha          = $data['fecha']          ?? null;
        $this->total          = (float)  ($data['total']          ?? 0);
        $this->pago_tarjeta   = (float)  ($data['pago_tarjeta']   ?? 0);
        $this->servicio       = (float)  ($data['servicio']       ?? 0);
        $this->descuento      = (float)  ($data['descuento']      ?? 0);
        $this->forma_pago     = (string) ($data['forma_pago']     ?? 'efectivo');
        $this->cliente_nombre = $data['cliente_nombre']  ?? null;
        $this->vendedor_nombre= $data['vendedor_nombre'] ?? null;
        return $this;
    }

    // =========================================================
    //  MÉTODOS DE ACCESO A DATOS
    // =========================================================

    // Obtener ventas con filtros (para la vista /ventas)
    public function getVentas(array $filtros = []): array
    {
        $sql = "
            SELECT f.*,
                   (COALESCE(f.total - f.descuento, 0)) AS total_final,
                   c.nombre as cliente_nombre,
                   u.nombre AS vendedor_nombre
            FROM facturas f
            JOIN clientes c ON f.cliente_id = c.id
            JOIN usuarios u ON f.user_id    = u.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filtros['desde']) && !empty($filtros['hasta'])) {
            $sql     .= " AND f.fecha >= ? AND f.fecha <= ?";
            $params[] = $filtros['desde'] . ' 00:00:00';
            $params[] = date('Y-m-d', strtotime($filtros['hasta'] . ' +1 day')) . ' 00:00:00';
        } else {
            $sql     .= " AND f.fecha >= ? AND f.fecha <= ?";
            $params[] = date('Y-m-d') . ' 00:00:00';
            $params[] = date('Y-m-d', strtotime('+1 day')) . ' 00:00:00';
        }

        if (!empty($filtros['q'])) {
            $sql     .= " AND (f.id LIKE ? OR f.forma_pago LIKE ?)";
            $term     = '%' . $filtros['q'] . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $sql .= " ORDER BY f.fecha DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener una factura por ID (con cliente y vendedor)
    public function getById(int $id): array|false
    {
        $stmt = $this->pdo->prepare("
            SELECT f.*, c.nombre AS cliente_nombre, u.nombre AS vendedor_nombre
            FROM facturas f
            LEFT JOIN usuarios u ON f.user_id    = u.id
            LEFT JOIN clientes c ON f.cliente_id = c.id
            WHERE f.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Obtener factura con datos de cliente para impresión
    public function getParaImpresion(int $id): array|false
    {
        $stmt = $this->pdo->prepare("
            SELECT f.*, c.nombre AS cliente_nombre, c.direccion, c.telefono
            FROM facturas f
            JOIN clientes c ON f.cliente_id = c.id
            WHERE f.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Obtener líneas del detalle (para la API JSON)
    public function getDetalles(int $id): array
    {
        $stmt = $this->pdo->prepare("
            SELECT d.cantidad, d.precio_unitario, d.unidad_medida, d.subtotal, p.nombre
            FROM detalle_factura d
            JOIN productos p ON d.producto_id = p.id
            WHERE d.factura_id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener detalle con nombre de producto (para impresión)
    public function getDetallesParaImpresion(int $id): array
    {
        $stmt = $this->pdo->prepare("
            SELECT d.*, p.nombre AS producto_nombre
            FROM detalle_factura d
            JOIN productos p ON d.producto_id = p.id
            WHERE d.factura_id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Crear factura + detalles + descontar stock (todo en una transacción)
    public function create(array $data, int $userId): int
    {
        $cliente_id      = $data['cliente_id'] ?? null;
        $total           = (float) ($data['total']            ?? 0);
        $descuento       = (float) ($data['descuento']        ?? 0);
        $forma_pago      = $data['forma_pago']                ?? 'efectivo';
        $productos       = $data['productos']                 ?? [];
        $incluir_servicio= $data['incluir_servicio']          ?? false;
        $servicio_custom = (float) ($data['servicio']         ?? 0);

        $totalDes      = $total - $descuento;
        $pago_tarjeta  = $forma_pago === 'tarjeta' ? round($totalDes * 0.05, 2) : 0.0;
        $pago_servicio = 0.0;

        if ($incluir_servicio) {
            $pago_servicio = $servicio_custom > 0
                ? $servicio_custom
                : round($totalDes * 0.10, 2);
        }

        if ( !$cliente_id ) {
            http_response_code(400);
            echo json_encode(['error' => 'Datos incompletos']);
            return null;
        }

        $this->pdo->beginTransaction();

        try {
            // 1. Insertar factura
            $stmt = $this->pdo->prepare("
                INSERT INTO facturas
                    (cliente_id, user_id, total, forma_pago, pago_tarjeta, servicio, descuento, fecha)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$cliente_id, $userId, $total, $forma_pago, $pago_tarjeta, $pago_servicio, $descuento]);
            $facturaId = (int) $this->pdo->lastInsertId();

            // 2. Insertar detalle_factura
            $stmtDetalle = $this->pdo->prepare("
                INSERT INTO detalle_factura
                    (factura_id, producto_id, cantidad, precio_unitario, unidad_medida, subtotal)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            foreach ($productos as $p) {
                $stmtDetalle->execute([
                    $facturaId,
                    $p['producto_id'],
                    $p['cantidad'],
                    $p['precio'],
                    $p['unidad'],
                    $p['subtotal'],
                ]);
            }

            $this->pdo->commit();
            return $facturaId;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // Actualizar fecha, cliente, forma de pago y descuento de una factura
    public function update(int $id, array $data): bool
    {
        $factura = $this->getById($id);
        if (!$factura) return false;

        $forma_pago   = $data['forma_pago'] ?? $factura['forma_pago'];
        $descuento    = (float) ($data['descuento'] ?? $factura['descuento']);
        $totalDes     = ((float) $factura['total']) - $descuento;
        $pago_tarjeta = $forma_pago === 'tarjeta' ? round($totalDes * 0.05, 2) : 0.0;

        $stmt = $this->pdo->prepare("
            UPDATE facturas
            SET fecha = ?, cliente_id = ?, user_id = ?,
                forma_pago = ?, pago_tarjeta = ?, descuento = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $data['fecha'],
            $data['cliente_id'],
            $data['usuario_id'],
            $forma_pago,
            $pago_tarjeta,
            $descuento,
            $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    // Eliminar factura y revertir stock (todo en una transacción)
    public function delete(int $id): void
    {
        $this->pdo->beginTransaction();

        try {
            $stmtDetalle = $this->pdo->prepare("SELECT producto_id, cantidad FROM detalle_factura WHERE factura_id = ?");
            $stmtDetalle->execute([$id]);
            $detalles = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);

            if (empty($detalles)) {
                throw new Exception('No se encontraron detalles para la factura');
            }

            $this->pdo->prepare("DELETE FROM detalle_factura WHERE factura_id = ?")->execute([$id]);

            $stmt = $this->pdo->prepare("DELETE FROM facturas WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                throw new Exception('Factura no encontrada');
            }

            $this->pdo->commit();

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // Filas para exportar a Excel con filtros
    public function getParaExport(array $filtros = []): array
    {
        $sql = "
            SELECT f.id, f.fecha, c.nombre AS cliente, u.nombre AS vendedor,
                   f.forma_pago, f.total, f.pago_tarjeta, f.servicio, f.descuento
            FROM facturas f
            JOIN clientes c ON f.cliente_id = c.id
            JOIN usuarios u ON f.user_id    = u.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filtros['desde']) && !empty($filtros['hasta'])) {
            $sql     .= " AND DATE(f.fecha) BETWEEN ? AND ?";
            $params[] = $filtros['desde'];
            $params[] = $filtros['hasta'];
        }
        if (!empty($filtros['q'])) {
            $sql     .= " AND (c.nombre LIKE ? OR f.id LIKE ? OR f.forma_pago LIKE ?)";
            $term     = '%' . $filtros['q'] . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $sql .= " ORDER BY f.fecha DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}