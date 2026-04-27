<?php

declare(strict_types=1);

class CocinaModel
{
    // =========================================================
    //  ATRIBUTOS  (espejo exacto de la tabla `pedido_items`)
    // =========================================================
    public int     $id;
    public int     $pedido_id;
    public int     $producto_id;
    public float   $cantidad;
    public string  $unidad_medida   = 'UND';
    public float   $precio_unitario = 0.0;
    public float   $subtotal        = 0.0;
    public string  $estado          = 'pendiente';
    public ?string $nota            = null;
    public ?string $created_at      = null;
    public ?string $enviado_at      = null;
    public ?string $preparado_at    = null;
    public ?string $listo_at        = null;
    public ?string $servido_at      = null;
    public ?string $updated_at      = null;

    // Atributos extra de JOINs
    public ?int    $mesa_id         = null;
    public ?string $mesa_numero     = null;
    public ?string $producto_nombre = null;

    // =========================================================
    //  CONSTRUCTOR
    // =========================================================
    public function __construct(private PDO $pdo) {}

    // =========================================================
    //  HIDRATACIÓN
    // =========================================================
    public function fill(array $data): static
    {
        $this->id              = (int)    ($data['id']              ?? 0);
        $this->pedido_id       = (int)    ($data['pedido_id']       ?? 0);
        $this->producto_id     = (int)    ($data['producto_id']     ?? 0);
        $this->cantidad        = (float)  ($data['cantidad']        ?? 0);
        $this->unidad_medida   = (string) ($data['unidad_medida']   ?? 'UND');
        $this->precio_unitario = (float)  ($data['precio_unitario'] ?? 0);
        $this->subtotal        = (float)  ($data['subtotal']        ?? 0);
        $this->estado          = (string) ($data['estado']          ?? 'pendiente');
        $this->nota            = $data['nota']         ?? null;
        $this->created_at      = $data['created_at']   ?? null;
        $this->enviado_at      = $data['enviado_at']   ?? null;
        $this->preparado_at    = $data['preparado_at'] ?? null;
        $this->listo_at        = $data['listo_at']     ?? null;
        $this->servido_at      = $data['servido_at']   ?? null;
        $this->updated_at      = $data['updated_at']   ?? null;
        $this->mesa_id         = isset($data['mesa_id'])     ? (int)    $data['mesa_id']     : null;
        $this->mesa_numero     = $data['mesa_numero']        ?? null;
        $this->producto_nombre = $data['producto_nombre']    ?? null;
        return $this;
    }

    // =========================================================
    //  MÉTODOS DE ACCESO A DATOS
    // =========================================================

    // Obtener cola de cocina (estados: enviado, preparando, listo)
    // Filtro opcional: 'comida' | 'bebida'
    public function getCola(?string $tipo = null): array
    {
        $sql = "
            SELECT i.*, p.mesa_id, m.numero AS mesa_numero, pr.nombre AS producto_nombre
            FROM pedido_items i
            JOIN pedidos  p  ON p.id  = i.pedido_id
            JOIN mesas    m  ON m.id  = p.mesa_id
            JOIN productos pr ON pr.id = i.producto_id
            WHERE i.estado IN ('enviado','preparando','listo')
        ";
        $params = [];

        if ($tipo === 'comida') {
            $sql     .= " AND pr.cocina = ?";
            $params[] = 1;
        } elseif ($tipo === 'bebida') {
            $sql     .= " AND pr.cocina = ?";
            $params[] = 0;
        }

        $sql .= " ORDER BY COALESCE(i.enviado_at, i.created_at) ASC, i.id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Actualizar estado de un item desde cocina (solo preparando | listo)
    public function actualizarEstado(int $itemId, string $estado): bool
    {
        $permitidos = ['preparando', 'listo'];
        if (!in_array($estado, $permitidos, true)) {
            throw new InvalidArgumentException("Estado inválido: {$estado}");
        }

        $col = $estado === 'preparando' ? 'preparado_at' : 'listo_at';
        $sql = "
            UPDATE pedido_items
            SET estado = :estado, {$col} = NOW()
            WHERE id = :id AND estado IN ('enviado','preparando')
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':estado' => $estado, ':id' => $itemId]);
        return $stmt->rowCount() > 0;
    }
}