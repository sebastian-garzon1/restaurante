<?php

declare(strict_types=1);

class ResponsableModel
{
    // =========================================================
    //  ATRIBUTOS (espejo exacto de tabla `egresos_responsables`)
    // =========================================================
    public int     $id;
    public string  $nombre;

    // =========================================================
    //  CONSTRUCTOR (inyección de PDO)
    // =========================================================
    public function __construct(private PDO $pdo) {}

    // =========================================================
    //  HIDRATACIÓN (poblar atributos desde un array de BD)
    // =========================================================
    public function fill(array $data): static
    {
        $this->id              = (int)    ($data['id']              ?? 0);
        $this->nombre          = (string) ($data['nombre']          ?? '');
        return $this;
    }

    // =========================================================
    //  MÉTODOS DE ACCESO A DATOS
    // =========================================================

    /**
     * Obtener todos los responsables
     */
    public function getAll(): array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM egresos_responsables ORDER BY nombre ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener un responsable por ID
     */
    public function getById(int $id): array|false
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM egresos_responsables WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Crear nuevo responsable
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO egresos_responsables
            (nombre)
            VALUES (?)
        ");
        $stmt->execute([
            $data['nombre']          ?? '',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Actualizar responsable
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE egresos_responsables SET
                nombre          = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['nombre']          ?? '',
            $id,
        ]);
    }

    /**
     * Eliminar responsable
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM egresos_responsables WHERE id = ?"
        );
        return $stmt->execute([$id]);
    }

}