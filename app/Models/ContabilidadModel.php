<?php

declare(strict_types=1);

class ContabilidadModel
{
    // =========================================================
    //  ATRIBUTOS (espejo exacto de tabla `egresos`)
    // =========================================================
    public int     $id;
    public string  $fecha;
    public ?int    $responsable_id = null;
    public string  $concepto;
    public float   $valor;
    public string  $metodo;
    public ?string $comprobante = null;
    public int     $modificado   = 0;
    public ?string $creado_en    = null;
    public ?string $responsable_nombre = null;

    // =========================================================
    //  CONSTRUCTOR (inyección de PDO)
    // =========================================================
    public function __construct(private PDO $pdo) {}

    // =========================================================
    //  HIDRATACIÓN
    // =========================================================
    public function fill(array $data): static
    {
        $this->id                  = (int)    ($data['id']                   ?? 0);
        $this->fecha               = (string) ($data['fecha']                ?? date('Y-m-d'));
        $this->responsable_id      = $data['responsable_id']     ?? null;
        $this->concepto            = (string) ($data['concepto']             ?? '');
        $this->valor               = (float)  ($data['valor']                ?? 0);
        $this->metodo              = (string) ($data['metodo']               ?? 'efectivo');
        $this->comprobante         = $data['comprobante']        ?? null;
        $this->modificado          = (int)    ($data['modificado']           ?? 0);
        $this->creado_en           = $data['creado_en']          ?? null;
        $this->responsable_nombre  = $data['responsable_nombre'] ?? null;
        return $this;
    }

    // =========================================================
    //  MÉTODOS DE ACCESO A DATOS
    // =========================================================

    public function getAll(array $filtros = []): array
    {
        $sql = "
            SELECT e.*, r.nombre AS responsable
            FROM egresos e
            LEFT JOIN egresos_responsables r ON e.responsable_id = r.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filtros['fecha_desde'])) {
            $sql .= " AND e.fecha >= ?";
            $params[] = $filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $sql .= " AND e.fecha <= ?";
            $params[] = $filtros['fecha_hasta'];
        }
        if (!empty($filtros['metodo'])) {
            $sql .= " AND e.metodo = ?";
            $params[] = $filtros['metodo'];
        }

        $sql .= " ORDER BY e.fecha DESC, e.id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id): array|false
    {
        $stmt = $this->pdo->prepare("
            SELECT e.*, r.nombre AS responsable
            FROM egresos e
            LEFT JOIN egresos_responsables r ON e.responsable_id = r.id
            WHERE e.id = ? LIMIT 1
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO egresos
            (fecha, responsable_id, concepto, valor, metodo, comprobante)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['fecha']           ?? date('Y-m-d'),
            $data['responsable']  ?? null,
            $data['concepto']        ?? '',
            $data['valor']           ?? 0,
            $data['metodo']          ?? 'efectivo',
            $data['comprobante']     ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE egresos SET
                fecha           = ?,
                responsable_id  = ?,
                concepto        = ?,
                valor           = ?,
                metodo          = ?,
                comprobante     = ?,
                modificado      = 1
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['fecha']           ?? date('Y-m-d'),
            $data['responsable']  ?? null,
            $data['concepto']        ?? '',
            $data['valor']           ?? 0,
            $data['metodo']          ?? 'efectivo',
            $data['comprobante']     ?? null,
            $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM egresos WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getResumenPorMetodo(string $fecha_desde = '', string $fecha_hasta = ''): array
    {
        $sql = "SELECT metodo, SUM(valor) AS total, COUNT(*) AS cantidad FROM egresos WHERE 1=1";
        $params = [];

        if (!empty($fecha_desde)) {
            $sql .= " AND fecha >= ?";
            $params[] = $fecha_desde;
        }
        if (!empty($fecha_hasta)) {
            $sql .= " AND fecha <= ?";
            $params[] = $fecha_hasta;
        }
        $sql .= " GROUP BY metodo";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTotalPorPeriodo(string $fecha_desde = '', string $fecha_hasta = ''): float
    {
        $sql = "SELECT SUM(valor) AS total FROM egresos WHERE 1=1";
        $params = [];

        if (!empty($fecha_desde)) {
            $sql .= " AND fecha >= ?";
            $params[] = $fecha_desde;
        }
        if (!empty($fecha_hasta)) {
            $sql .= " AND fecha <= ?";
            $params[] = $fecha_hasta;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (float) ($result['total'] ?? 0);
    }

    // =========================================================
    //  MÉTODOS PARA TRASPASOS
    // =========================================================

    public function getAllTraspasos(array $filtros = []): array
    {
        $sql = "SELECT * FROM traspasos WHERE 1=1";
        $params = [];

        if (!empty($filtros['fecha_desde'])) {
            $sql .= " AND fecha >= ?";
            $params[] = $filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $sql .= " AND fecha <= ?";
            $params[] = $filtros['fecha_hasta'];
        }

        $sql .= " ORDER BY fecha DESC, id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTraspasoById(int $id): array|false
    {
        $stmt = $this->pdo->prepare("SELECT * FROM traspasos WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createTraspaso(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO traspasos (fecha, origen, destino, valor)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['fecha']   ?? date('Y-m-d'),
            $data['origen']  ?? '',
            $data['destino'] ?? '',
            $data['valor']   ?? 0,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateTraspaso(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE traspasos SET
                fecha   = ?,
                origen  = ?,
                destino = ?,
                valor   = ?,
                modificado = 1
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['fecha']   ?? date('Y-m-d'),
            $data['origen']  ?? '',
            $data['destino'] ?? '',
            $data['valor']   ?? 0,
            $id,
        ]);
    }

    public function deleteTraspaso(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM traspasos WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
