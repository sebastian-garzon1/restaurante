<?php

declare(strict_types=1);

class ProductoModel
{
    // =========================================================
    //  ATRIBUTOS  (espejo exacto de la tabla `productos`)
    // =========================================================
    public int     $id;
    public string  $codigo;
    public string  $nombre;
    public float   $precio_kg     = 0.0;
    public float   $precio_unidad = 0.0;
    public float   $precio_libra  = 0.0;
    public bool    $cocina        = true;
    public ?string $created_at    = null;
    public ?string $updated_at    = null;

    // =========================================================
    //  CONSTRUCTOR
    // =========================================================
    public function __construct(private PDO $pdo) {}

    // =========================================================
    //  HIDRATACIÓN
    // =========================================================
    public function fill(array $data): static
    {
        $this->id            = (int)   ($data['id']            ?? 0);
        $this->codigo        = (string)($data['codigo']        ?? '');
        $this->nombre        = (string)($data['nombre']        ?? '');
        $this->precio_kg     = (float) ($data['precio_kg']     ?? 0);
        $this->precio_unidad = (float) ($data['precio_unidad'] ?? 0);
        $this->precio_libra  = (float) ($data['precio_libra']  ?? 0);
        $this->cocina        = (bool)  ($data['cocina']        ?? true);
        $this->created_at    = $data['created_at'] ?? null;
        $this->updated_at    = $data['updated_at'] ?? null;
        return $this;
    }

    // =========================================================
    //  MÉTODOS DE ACCESO A DATOS
    // =========================================================

    // Todos los productos ordenados por nombre
    public function getAll(): array
    {
        return $this->pdo->query("SELECT * FROM productos ORDER BY nombre")
                         ->fetchAll(PDO::FETCH_ASSOC);
    }

    // Todos ordenados por código DESC (vista admin)
    public function getAllDesc(): array
    {
        return $this->pdo->query("SELECT * FROM productos ORDER BY codigo DESC")
                         ->fetchAll(PDO::FETCH_ASSOC);
    }

    // Un producto por ID
    public function getById(int $id): array|false
    {
        $stmt = $this->pdo->prepare("SELECT * FROM productos WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Búsqueda por nombre o código (AJAX)
    public function buscar(string $q, int $limit = 10): array
    {
        $term = "%{$q}%";
        $stmt = $this->pdo->prepare("
            SELECT * FROM productos
            WHERE nombre LIKE :term OR codigo LIKE :term
            ORDER BY nombre LIMIT :limit
        ");

        $stmt->bindValue(':term', $term, PDO::PARAM_STR);
        // Forzamos que el límite sea un entero
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT); 
        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $data;
    }

    // Crear producto
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO productos (codigo, nombre, precio_unidad, cocina)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['codigo'],
            $data['nombre'],
            $data['precio_unidad'] ?? 0,
            $data['cocina']        ?? false,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    // Actualizar producto
    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE productos
            SET codigo = ?, nombre = ?, precio_unidad = ?, cocina = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $data['codigo'],
            $data['nombre'],
            $data['precio_unidad'] ?? 0,
            $data['cocina']        ?? false,
            $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    // Eliminar producto
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM productos WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    // Reporte de ventas por producto con filtros opcionales
    public function getVentasPorProducto(array $filtros = []): array
    {
        $sql    = "
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

    // Importar productos masivamente desde Excel (upsert)
    public function importar(array $rows): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO productos (codigo, nombre, precio_unidad)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                nombre        = VALUES(nombre),
                precio_unidad = VALUES(precio_unidad)
        ");
        $this->pdo->beginTransaction();
        foreach ($rows as $p) {
            $stmt->execute([$p['codigo'], $p['nombre'], $p['precio_unidad']]);
        }
        $this->pdo->commit();
        return count($rows);
    }
}