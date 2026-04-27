<?php

declare(strict_types=1);

// ProductoVentaModel no tiene tabla propia:
// es una capa de consulta sobre detalle_factura + facturas + productos
class ProductoVentaModel
{
    // =========================================================
    //  ATRIBUTOS  (resultados de las consultas de reporte)
    // =========================================================
    public string $producto  = '';
    public float  $cantidad  = 0.0;
    public float  $total     = 0.0;

    // =========================================================
    //  CONSTRUCTOR
    // =========================================================
    public function __construct(private PDO $pdo) {}

    // =========================================================
    //  HIDRATACIÓN
    // =========================================================
    public function fill(array $data): static
    {
        $this->producto = (string) ($data['producto'] ?? '');
        $this->cantidad = (float)  ($data['cantidad'] ?? 0);
        $this->total    = (float)  ($data['total']    ?? 0);
        return $this;
    }

    // =========================================================
    //  MÉTODOS DE ACCESO A DATOS
    // =========================================================

    // Reporte de ventas agrupado por producto con filtros opcionales
    public function getReporte(array $filtros = []): array
    {
        $sql = "
            SELECT p.nombre AS producto,
                   SUM(d.cantidad) AS cantidad,
                   SUM(d.subtotal) AS total
            FROM detalle_factura d
            JOIN facturas f       ON f.id  = d.factura_id
            LEFT JOIN productos p ON p.id  = d.producto_id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filtros['desde']) && !empty($filtros['hasta'])) {
            $sql     .= " AND f.fecha >= ? AND f.fecha <= ?";
            $params[] = $filtros['desde'] . ' 00:00:00';
            $params[] = date('Y-m-d', strtotime($filtros['hasta'] . ' +1 day')) . ' 00:00:00';
        }

        if (!empty($filtros['q'])) {
            $sql     .= " AND (p.nombre LIKE ? OR f.id LIKE ? OR f.forma_pago LIKE ?)";
            $term     = '%' . $filtros['q'] . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $sql .= " GROUP BY p.nombre ORDER BY cantidad DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}