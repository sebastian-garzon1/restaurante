<?php

declare(strict_types=1);

class ClienteModel
{
    // =========================================================
    //  ATRIBUTOS  (espejo exacto de la tabla `clientes`)
    // =========================================================
    public int     $id;
    public string  $nombre;
    public ?string $correo    = null;
    public ?string $direccion = null;
    public ?string $telefono  = null;

    // =========================================================
    //  CONSTRUCTOR
    // =========================================================
    public function __construct(private PDO $pdo) {}

    // =========================================================
    //  HIDRATACIÓN
    // =========================================================
    public function fill(array $data): static
    {
        $this->id        = (int)    ($data['id']        ?? 0);
        $this->nombre    = (string) ($data['nombre']    ?? '');
        $this->correo    = $data['correo']    ?? null;
        $this->direccion = $data['direccion'] ?? null;
        $this->telefono  = $data['telefono']  ?? null;
        return $this;
    }

    // =========================================================
    //  MÉTODOS DE ACCESO A DATOS
    // =========================================================

    // Todos los clientes ordenados por nombre
    public function getAll(): array
    {
        return $this->pdo->query("SELECT * FROM clientes ORDER BY nombre")
                         ->fetchAll(PDO::FETCH_ASSOC);
    }

    // Un cliente por ID
    public function getById(int $id): array|false
    {
        $stmt = $this->pdo->prepare("SELECT * FROM clientes WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Buscar por nombre, teléfono o correo (AJAX)
    public function buscar(string $q, int $limit = 10): array
    {
        $term = "%{$q}%";
        
        $stmt = $this->pdo->prepare("
            SELECT * FROM clientes
            WHERE nombre LIKE :term 
            OR telefono LIKE :term 
            OR correo LIKE :term
            ORDER BY nombre 
            LIMIT :limit
        ");

        $stmt->bindValue(':term', $term, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Crear cliente
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO clientes (nombre, correo, direccion, telefono)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['nombre'],
            $data['correo']    ?? null,
            $data['direccion'] ?? null,
            $data['telefono']  ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    // Actualizar cliente
    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE clientes SET nombre = ?, correo = ?, direccion = ?, telefono = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $data['nombre'],
            $data['correo']    ?? null,
            $data['direccion'] ?? null,
            $data['telefono']  ?? null,
            $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    // Eliminar cliente
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM clientes WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}