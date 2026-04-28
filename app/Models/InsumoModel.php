<?php

declare(strict_types=1);

class InsumoModel
{
    // =========================================================
    //  ATRIBUTOS  (espejo exacto de la tabla `insumos`)
    // =========================================================
    public int     $id;
    public string  $nombre;
    public ?string $descripcion  = null;
    public float   $stock        = 0.0;
    public float   $stock_minimo = 0.0;
    public bool    $activo       = true;
    public ?string $created_at   = null;
    public ?string $updated_at   = null;

    // =========================================================
    //  CONSTRUCTOR
    // =========================================================
    public function __construct(private PDO $pdo) {}

    // =========================================================
    //  HIDRATACIÓN
    // =========================================================
    public function fill(array $data): static
    {
        $this->id           = (int)    ($data['id']           ?? 0);
        $this->nombre       = (string) ($data['nombre']       ?? '');
        $this->descripcion  = $data['descripcion']  ?? null;
        $this->stock        = (float)  ($data['stock']        ?? 0);
        $this->stock_minimo = (float)  ($data['stock_minimo'] ?? 0);
        $this->activo       = (bool)   ($data['activo']       ?? true);
        $this->created_at   = $data['created_at'] ?? null;
        $this->updated_at   = $data['updated_at'] ?? null;
        return $this;
    }

    // =========================================================
    //  MÉTODOS DE ACCESO A DATOS — INSUMOS
    // =========================================================

    // Todos los insumos
    public function getAll(): array
    {
        return $this->pdo->query("SELECT * FROM insumos")
                         ->fetchAll(PDO::FETCH_ASSOC);
    }

    // Un insumo por ID
    public function getById(int $id): array|false
    {
        $stmt = $this->pdo->prepare("SELECT * FROM insumos WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Buscar insumos activos NO asignados a un producto (para la vista de asignación)
    public function buscar(string $q, ?int $excluirProductoId = null, int $limit = 10): array
    {
        $term = "%{$q}%";
        $stmt = $this->pdo->prepare("
            SELECT i.*
            FROM insumos i
            LEFT JOIN producto_insumo pi
                ON pi.insumo_id = i.id
                AND pi.producto_id = ?
            WHERE (i.nombre LIKE ? OR i.descripcion LIKE ?)
                AND i.activo = 1
                AND pi.insumo_id IS NULL
            ORDER BY i.nombre
            LIMIT ?
        ");
        $stmt->execute([$excluirProductoId, $term, $term, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Crear insumo
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO insumos (nombre, descripcion, stock, stock_minimo, activo)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['nombre'],
            $data['descripcion']  ?? null,
            $data['stock']        ?? 0,
            $data['stock_minimo'] ?? 0,
            $data['estado']       ?? false,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    // Actualizar insumo
    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE insumos
            SET nombre = ?, descripcion = ?, stock = ?, stock_minimo = ?, activo = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $data['nombre'],
            $data['descripcion']  ?? null,
            $data['stock']        ?? 0,
            $data['stock_minimo'] ?? 0,
            $data['estado']       ?? false,
            $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    // Eliminar insumo
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM insumos WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    // =========================================================
    //  MÉTODOS DE ACCESO A DATOS — PRODUCTO_INSUMO
    // =========================================================

    // Obtener insumos asignados a un producto con su cantidad
    public function getByProducto(int $productoId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT i.*, pi.id AS inventario_id, pi.cantidad
            FROM producto_insumo pi
            LEFT JOIN productos p  ON p.id  = pi.producto_id
            LEFT JOIN insumos   i  ON i.id  = pi.insumo_id
            WHERE p.id = ?
        ");
        $stmt->execute([$productoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Asignar insumo a producto
    public function asignar(int $productoId, int $insumoId): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO producto_insumo (producto_id, insumo_id) VALUES (?, ?)
        ");
        $stmt->execute([$productoId, $insumoId]);
        return (int) $this->pdo->lastInsertId();
    }

    // Actualizar cantidad de insumo en producto_insumo
    public function actualizarCantidad(int $inventarioId, float $cantidad): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE producto_insumo SET cantidad = ? WHERE id = ?
        ");
        $stmt->execute([$cantidad, $inventarioId]);
        return $stmt->rowCount() > 0;
    }

    // Eliminar relación producto-insumo
    public function desasignar(int $inventarioId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM producto_insumo WHERE id = ?");
        $stmt->execute([$inventarioId]);
        return $stmt->rowCount() > 0;
    }

    // Verificar disponibilidad de stock para una cantidad dada de producto
    public function verificarDisponibilidad(int $productoId, float $cantidad): array
    {
        $stmt = $this->pdo->prepare("
            SELECT i.nombre, i.stock,
                   (pi.cantidad * :cant) AS requerido
            FROM producto_insumo pi
            JOIN insumos i ON i.id = pi.insumo_id
            WHERE pi.producto_id = :pid
            HAVING i.stock < requerido
        ");
        $stmt->execute([':pid' => $productoId, ':cant' => $cantidad]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}