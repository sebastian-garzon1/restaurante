<?php

declare(strict_types=1);

class UsuarioModel
{
    // =========================================================
    //  ATRIBUTOS  (espejo exacto de la tabla `usuarios`)
    // =========================================================
    public int     $id;
    public string  $nombre;
    public string  $usuario;
    public ?string $correo     = null;
    public string  $password;
    public bool    $activo     = true;
    public int     $rol_id;
    public ?string $creado_en  = null;
    public ?string $ultimo_login = null;

    // Atributo extra que viene del JOIN con roles
    public ?string $rol_nombre = null;

    // =========================================================
    //  CONSTRUCTOR  (inyección de PDO)
    // =========================================================
    public function __construct(private PDO $pdo) {}

    // =========================================================
    //  HIDRATACIÓN  (poblar atributos desde un array de BD)
    // =========================================================
    public function fill(array $data): static
    {
        $this->id          = (int)    ($data['id']           ?? 0);
        $this->nombre      = (string) ($data['nombre']       ?? '');
        $this->usuario     = (string) ($data['usuario']      ?? '');
        $this->correo      = $data['correo']      ?? null;
        $this->password    = (string) ($data['password']     ?? '');
        $this->activo      = (bool)   ($data['activo']       ?? true);
        $this->rol_id      = (int)    ($data['rol_id']       ?? 0);
        $this->creado_en   = $data['creado_en']   ?? null;
        $this->ultimo_login= $data['ultimo_login'] ?? null;
        $this->rol_nombre  = $data['rol_nombre']  ?? null;
        return $this;
    }

    // =========================================================
    //  MÉTODOS DE ACCESO A DATOS
    // =========================================================

    // Buscar usuario por username para el login
    public function findByUsername(string $username): array|false
    {
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.nombre, u.usuario, u.password, u.activo,
                   r.nombre AS rol
            FROM usuarios u
            LEFT JOIN roles r ON u.rol_id = r.id
            WHERE u.usuario = ?
            LIMIT 1
        ");
        $stmt->execute([$username]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Registrar la fecha del último acceso
    public function updateLastLogin(int $id): void
    {
        $this->pdo->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?")
                  ->execute([$id]);
    }

    // Obtener todos los usuarios con su rol
    public function getAll(): array
    {
        $stmt = $this->pdo->query("
            SELECT u.*, r.nombre AS rol_nombre
            FROM usuarios u
            LEFT JOIN roles r ON u.rol_id = r.id
            ORDER BY u.nombre
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener un usuario por ID
    public function getById(int $id): array|false
    {
        $stmt = $this->pdo->prepare("
            SELECT u.*, r.nombre AS rol_nombre
            FROM usuarios u
            LEFT JOIN roles r ON u.rol_id = r.id
            WHERE u.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Crear usuario nuevo
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO usuarios (nombre, usuario, correo, password, rol_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['nombre'],
            $data['username'],
            $data['correo']  ?? null,
            password_hash($data['password'], PASSWORD_BCRYPT),
            $data['rol'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    // Actualizar usuario (password es opcional)
    public function update(int $id, array $data): bool
    {
        $params = [
            $data['nombre'],
            $data['username'],
            $data['correo'] ?? null,
            $data['rol'],
        ];
        $sql = "UPDATE usuarios SET nombre = ?, usuario = ?, correo = ?, rol_id = ?";

        if (!empty($data['password'])) {
            $sql     .= ", password = ?";
            $params[] = password_hash($data['password'], PASSWORD_BCRYPT);
        }

        $sql     .= " WHERE id = ?";
        $params[] = $id;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    // Eliminar usuario
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}