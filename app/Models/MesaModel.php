<?php

declare(strict_types=1);

class MesaModel
{
    // =========================================================
    //  ATRIBUTOS  (espejo exacto de la tabla `mesas`)
    // =========================================================
    public int     $id;
    public string  $numero;
    public ?string $descripcion = null;
    public string  $estado      = 'libre'; // libre | ocupada
    public ?string $created_at  = null;
    public ?string $updated_at  = null;

    // Atributo calculado: pedidos activos (viene de subquery)
    public int $pedidos_abiertos = 0;

    // =========================================================
    //  CONSTRUCTOR
    // =========================================================
    public function __construct(private PDO $pdo) {}

    // =========================================================
    //  HIDRATACIÓN
    // =========================================================
    public function fill(array $data): static
    {
        $this->id               = (int)    ($data['id']               ?? 0);
        $this->numero           = (string) ($data['numero']           ?? '');
        $this->descripcion      = $data['descripcion'] ?? null;
        $this->estado           = (string) ($data['estado']           ?? 'libre');
        $this->created_at       = $data['created_at']  ?? null;
        $this->updated_at       = $data['updated_at']  ?? null;
        $this->pedidos_abiertos = (int)    ($data['pedidos_abiertos'] ?? 0);
        return $this;
    }

    // =========================================================
    //  MÉTODOS DE ACCESO A DATOS
    // =========================================================

    // Listar mesas con conteo de pedidos abiertos
    public function getAll(): array
    {
        $stmt = $this->pdo->query("
            SELECT m.*, (
                SELECT COUNT(*) FROM pedidos p
                WHERE p.mesa_id = m.id AND p.estado NOT IN ('cerrado','cancelado')
            ) AS pedidos_abiertos
            FROM mesas m
            ORDER BY m.numero
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener una mesa por ID
    public function getById(int $id): array|false
    {
        $stmt = $this->pdo->prepare("SELECT * FROM mesas WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Crear mesa
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO mesas (numero, descripcion, estado)
            VALUES (?, ?, 'libre')
        ");
        $stmt->execute([$data['numero'], $data['descripcion'] ?? null]);
        return (int) $this->pdo->lastInsertId();
    }

    // Actualizar mesa
    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE mesas SET numero = ?, descripcion = ? WHERE id = ?
        ");
        $stmt->execute([$data['numero'], $data['descripcion'] ?? null, $id]);
        return $stmt->rowCount() > 0;
    }

    // Cambiar estado de la mesa
    public function cambiarEstado(int $id, string $estado): void
    {
        $this->pdo->prepare("UPDATE mesas SET estado = ?, updated_at = NOW() WHERE id = ?")
                  ->execute([$estado, $id]);
    }

    // Eliminar mesa
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM mesas WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}


// ================================================================
//  PedidoModel  (tabla `pedidos` — ligada a mesas)
// ================================================================
class PedidoModel
{
    // =========================================================
    //  ATRIBUTOS  (espejo exacto de la tabla `pedidos`)
    // =========================================================
    public int     $id;
    public int     $mesa_id;
    public ?int    $cliente_id = null;
    public string  $estado     = 'abierto'; // abierto | cerrado | cancelado
    public float   $total      = 0.0;
    public ?string $notas      = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    // =========================================================
    //  CONSTRUCTOR
    // =========================================================
    public function __construct(private PDO $pdo) {}

    // =========================================================
    //  HIDRATACIÓN
    // =========================================================
    public function fill(array $data): static
    {
        $this->id         = (int)    ($data['id']         ?? 0);
        $this->mesa_id    = (int)    ($data['mesa_id']    ?? 0);
        $this->cliente_id = isset($data['cliente_id']) ? (int) $data['cliente_id'] : null;
        $this->estado     = (string) ($data['estado']     ?? 'abierto');
        $this->total      = (float)  ($data['total']      ?? 0);
        $this->notas      = $data['notas']      ?? null;
        $this->created_at = $data['created_at'] ?? null;
        $this->updated_at = $data['updated_at'] ?? null;
        return $this;
    }

    // =========================================================
    //  MÉTODOS DE ACCESO A DATOS
    // =========================================================

    // Buscar pedido abierto de una mesa (para saber si ya tiene uno activo)
    public function getPedidoActivo(int $mesaId): array|false
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM pedidos
            WHERE mesa_id = ? AND estado NOT IN ('cerrado','cancelado')
            LIMIT 1
        ");
        $stmt->execute([$mesaId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Obtener pedido por ID
    public function getById(int $id): array|false
    {
        $stmt = $this->pdo->prepare("SELECT * FROM pedidos WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Obtener items de un pedido con nombre de producto
    public function getItems(int $pedidoId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT i.*, p.nombre AS producto_nombre
            FROM pedido_items i
            JOIN productos p ON p.id = i.producto_id
            WHERE i.pedido_id = ?
            ORDER BY i.created_at ASC
        ");
        $stmt->execute([$pedidoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener items válidos (no cancelados) para facturar
    public function getItemsValidos(int $pedidoId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM pedido_items
            WHERE pedido_id = ? AND estado <> 'cancelado'
        ");
        $stmt->execute([$pedidoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Abrir pedido: crea el pedido y actualiza el estado de la mesa
    public function abrir(int $mesaId, ?int $clienteId, ?string $notas): array
    {
        // ¿Ya existe uno activo?
        $existente = $this->getPedidoActivo($mesaId);
        if ($existente) {
            return $existente;
        }

        $fecha = date('Y-m-d H:i:s');

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("
                INSERT INTO pedidos (mesa_id, cliente_id, estado, total, notas, created_at, updated_at)
                VALUES (?, ?, 'abierto', 0, ?, ?, ?)
            ")->execute([$mesaId, $clienteId, $notas, $fecha, $fecha]);

            $pedidoId = (int) $this->pdo->lastInsertId();

            $this->pdo->prepare("UPDATE mesas SET estado='ocupada', updated_at=? WHERE id=?")
                      ->execute([$fecha, $mesaId]);

            $this->pdo->commit();

            return [
                'id'         => $pedidoId,
                'mesa_id'    => $mesaId,
                'cliente_id' => $clienteId,
                'estado'     => 'abierto',
                'total'      => 0,
                'notas'      => $notas,
            ];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // Agregar item al pedido (con verificación de stock)
    public function agregarItem(int $pedidoId, array $data): int
    {
        $productoId = $data['producto_id'];
        $cantidad   = $data['cantidad'];
        $precio     = $data['precio'];
        $unidad     = $data['unidad'] ?? 'UND';
        $nota       = $data['nota']   ?? null;
        $subtotal   = $cantidad * $precio;
        $fecha      = date('Y-m-d H:i:s');

        // Verificar stock
        $stmtCheck = $this->pdo->prepare("
            SELECT i.nombre, i.stock,
                   (pi.cantidad * :cant) AS requerido
            FROM producto_insumo pi
            JOIN insumos i ON i.id = pi.insumo_id
            WHERE pi.producto_id = :pid
            HAVING i.stock < requerido
        ");
        $stmtCheck->execute([':pid' => $productoId, ':cant' => $cantidad]);
        $faltantes = $stmtCheck->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($faltantes)) {
            throw new RuntimeException(json_encode([
                'error'    => 'Stock insuficiente',
                'detalles' => $faltantes,
            ]));
        }

        $this->pdo->prepare("
            INSERT INTO pedido_items
                (pedido_id, producto_id, cantidad, unidad_medida, precio_unitario, subtotal, estado, nota, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, 'pendiente', ?, ?, ?)
        ")->execute([$pedidoId, $productoId, $cantidad, $unidad, $precio, $subtotal, $nota, $fecha, $fecha]);

        return (int) $this->pdo->lastInsertId();
    }

    // Eliminar item
    public function eliminarItem(int $itemId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM pedido_items WHERE id = ?");
        $stmt->execute([$itemId]);
        return $stmt->rowCount() > 0;
    }

    // Enviar item a cocina (cambia estado a 'listo')
    public function enviarItem(int $itemId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE pedido_items SET estado='listo', enviado_at=? WHERE id=?
        ");
        $stmt->execute([date('Y-m-d H:i:s'), $itemId]);
        return $stmt->rowCount() > 0;
    }

    // Actualizar estado de un item
    public function actualizarEstadoItem(int $itemId, string $estado): bool
    {
        $permitidos = ['pendiente','enviado','preparando','listo','servido','cancelado'];
        if (!in_array($estado, $permitidos, true)) {
            throw new InvalidArgumentException("Estado inválido: {$estado}");
        }

        $timestamps = [
            'preparando' => 'preparado_at',
            'listo'      => 'listo_at',
            'servido'    => 'servido_at',
        ];

        if (isset($timestamps[$estado])) {
            $col  = $timestamps[$estado];
            $sql  = "UPDATE pedido_items SET estado=?, {$col}=? WHERE id=?";
            $args = [$estado, date('Y-m-d H:i:s'), $itemId];
        } else {
            $sql  = "UPDATE pedido_items SET estado=?, updated_at=? WHERE id=?";
            $args = [$estado, date('Y-m-d H:i:s'), $itemId];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($args);
        return $stmt->rowCount() > 0;
    }

    // Facturar pedido (transacción completa: factura + detalle + stock + cierre)
    public function facturar(int $pedidoId, array $data, int $userId): int
    {
        $clienteId      = $data['cliente_id'];
        $formaPago      = $data['forma_pago'];
        $servicioEstado = $data['servicioEstado'] ?? false;
        $servicioValor  = (float) ($data['servicioValor'] ?? 0);
        $descuento      = (float) ($data['descuento']     ?? 0);

        $this->pdo->beginTransaction();
        try {
            $pedido = $this->pdo->prepare("SELECT * FROM pedidos WHERE id=? FOR UPDATE");
            $pedido->execute([$pedidoId]);
            $pedido = $pedido->fetch(PDO::FETCH_ASSOC);
            if (!$pedido) throw new Exception("Pedido no encontrado");

            $items = $this->getItemsValidos($pedidoId);
            if (empty($items)) throw new Exception("Pedido sin items");

            $total    = array_sum(array_column($items, 'subtotal'));
            $totalDes = $total - $descuento;

            $pagoTarjeta  = $formaPago === 'tarjeta'   ? round($totalDes * 0.05, 2) : 0.0;
            $pagoServicio = 0.0;
            if ($servicioEstado) {
                $pagoServicio = $servicioValor > 0 ? $servicioValor : round($totalDes * 0.10, 2);
            }

            $fecha = date('Y-m-d H:i:s');

            // Crear factura
            $this->pdo->prepare("
                INSERT INTO facturas (cliente_id, user_id, total, forma_pago, pago_tarjeta, servicio, descuento, fecha)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([$clienteId, $userId, $total, $formaPago, $pagoTarjeta, $pagoServicio, $descuento, $fecha]);

            $facturaId = (int) $this->pdo->lastInsertId();

            // Insertar detalles y descontar stock
            $stmtDet      = $this->pdo->prepare("
                INSERT INTO detalle_factura (factura_id, producto_id, cantidad, precio_unitario, unidad_medida, subtotal)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmtInsumos  = $this->pdo->prepare("SELECT insumo_id, cantidad FROM producto_insumo WHERE producto_id = ?");
            $stmtStock    = $this->pdo->prepare("UPDATE insumos SET stock = stock - ? WHERE id = ?");

            foreach ($items as $it) {
                $stmtDet->execute([
                    $facturaId, $it['producto_id'], $it['cantidad'],
                    $it['precio_unitario'], $it['unidad_medida'], $it['subtotal'],
                ]);

                $stmtInsumos->execute([$it['producto_id']]);
                foreach ($stmtInsumos->fetchAll(PDO::FETCH_ASSOC) as $ins) {
                    $stmtStock->execute([$ins['cantidad'] * $it['cantidad'], $ins['insumo_id']]);
                }
            }

            // Cerrar pedido y liberar mesa
            $this->pdo->prepare("UPDATE pedidos SET estado='cerrado', total=? WHERE id=?")
                      ->execute([$total, $pedidoId]);
            $this->pdo->prepare("UPDATE mesas SET estado='libre' WHERE id=?")
                      ->execute([$pedido['mesa_id']]);

            $this->pdo->commit();
            return $facturaId;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // Mover pedido a otra mesa
    public function mover(int $pedidoId, int $mesaDestino): array
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM pedidos WHERE id=? FOR UPDATE");
            $stmt->execute([$pedidoId]);
            $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$pedido) throw new Exception("Pedido no encontrado");

            // ¿Mesa destino libre?
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) AS cnt FROM pedidos
                WHERE mesa_id = ? AND estado NOT IN ('cerrado','cancelado')
            ");
            $stmt->execute([$mesaDestino]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] > 0) {
                throw new Exception("La mesa destino tiene un pedido activo");
            }

            $this->pdo->prepare("UPDATE pedidos SET mesa_id=? WHERE id=?")
                      ->execute([$mesaDestino, $pedidoId]);

            // ¿Mesa origen quedó libre?
            $stmt->execute([$pedido['mesa_id']]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] == 0) {
                $this->pdo->prepare("UPDATE mesas SET estado='libre' WHERE id=?")
                          ->execute([$pedido['mesa_id']]);
            }

            $this->pdo->prepare("UPDATE mesas SET estado='ocupada' WHERE id=?")
                      ->execute([$mesaDestino]);

            $this->pdo->commit();
            return ['mesa_origen_id' => $pedido['mesa_id'], 'mesa_destino_id' => $mesaDestino];

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // Liberar mesa (cancela pedidos sin items activos)
    public function liberarMesa(int $mesaId): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                SELECT id FROM pedidos
                WHERE mesa_id=? AND estado NOT IN ('cerrado','cancelado')
                FOR UPDATE
            ");
            $stmt->execute([$mesaId]);
            $abiertos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($abiertos)) {
                $ids = array_column($abiertos, 'id');
                $in  = implode(',', array_fill(0, count($ids), '?'));

                // ¿Hay items activos?
                $stmt = $this->pdo->prepare("
                    SELECT COUNT(*) AS cnt FROM pedido_items
                    WHERE pedido_id IN ({$in}) AND estado <> 'cancelado'
                ");
                $stmt->execute($ids);
                if ($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] > 0) {
                    throw new Exception("La mesa tiene items activos, no se puede liberar");
                }

                $this->pdo->prepare("UPDATE pedidos SET estado='cancelado' WHERE id IN ({$in})")
                          ->execute($ids);
            }

            $this->pdo->prepare("UPDATE mesas SET estado='libre' WHERE id=?")->execute([$mesaId]);
            $this->pdo->commit();

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // Preview de factura antes de confirmar
    public function previewFactura(int $pedidoId, array $data): array
    {
        $clienteId      = $data['cliente_id'];
        $formaPago      = $data['forma_pago'] ?? null;
        $servicioEstado = $data['servicioEstado'] ?? false;
        $servicioValor  = $data['servicioValor']  ?? null;
        $descuento      = (float) ($data['descuento'] ?? 0);

        $stmtCliente = $this->pdo->prepare("SELECT nombre FROM clientes WHERE id = ?");
        $stmtCliente->execute([$clienteId]);
        $cliente = $stmtCliente->fetchColumn();
        if (!$cliente) throw new Exception("Cliente no encontrado");

        $stmt = $this->pdo->prepare("
            SELECT p.nombre, pi.cantidad, pi.precio_unitario
            FROM pedido_items pi
            JOIN productos p ON p.id = pi.producto_id
            WHERE pi.pedido_id = ?
        ");
        $stmt->execute([$pedidoId]);
        $itemsDB = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$itemsDB) throw new Exception("Pedido sin items");

        $items    = [];
        $subtotal = 0;
        foreach ($itemsDB as $it) {
            $tot       = $it['cantidad'] * $it['precio_unitario'];
            $subtotal += $tot;
            $items[]   = [
                'nombre'   => $it['nombre'],
                'cantidad' => (float) $it['cantidad'],
                'precio'   => (float) $it['precio_unitario'],
                'total'    => round($tot, 2),
            ];
        }

        $totalDes = $subtotal - $descuento;
        $servicio = 0.0;
        if ($servicioEstado) {
            $servicio = $servicioValor !== null ? (float) $servicioValor : round($totalDes * 0.10, 2);
        }
        $tarjeta = $formaPago === 'tarjeta' ? round($totalDes * 0.05, 2) : 0.0;

        return [
            'cliente'    => $cliente,
            'forma_pago' => $formaPago,
            'items'      => $items,
            'subtotal'   => round($subtotal, 2),
            'servicio'   => $servicio,
            'tarjeta'    => $tarjeta,
            'descuento'  => $descuento,
            'total'      => round($totalDes + $servicio + $tarjeta, 2),
        ];
    }
}