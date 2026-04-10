<?php

declare(strict_types=1);

// ================================================================
//  RolModel
// ================================================================
class RolModel
{
    // =========================================================
    //  ATRIBUTOS  (espejo exacto de la tabla `roles`)
    // =========================================================
    public int     $id;
    public string  $nombre;
    public ?string $descripcion = null;

    // =========================================================
    //  CONSTRUCTOR
    // =========================================================
    public function __construct(private PDO $pdo) {}

    // =========================================================
    //  HIDRATACIÓN
    // =========================================================
    public function fill(array $data): static
    {
        $this->id          = (int)    ($data['id']          ?? 0);
        $this->nombre      = (string) ($data['nombre']      ?? '');
        $this->descripcion = $data['descripcion'] ?? null;
        return $this;
    }

    // =========================================================
    //  MÉTODOS DE ACCESO A DATOS
    // =========================================================

    // Obtener todos los roles
    public function getAll(): array
    {
        return $this->pdo->query("SELECT id, nombre FROM roles ORDER BY nombre")
                         ->fetchAll(PDO::FETCH_ASSOC);
    }
}


// ================================================================
//  ConfiguracionModel
// ================================================================
class ConfiguracionModel
{
    // =========================================================
    //  ATRIBUTOS  (espejo exacto de la tabla `configuracion_impresion`)
    // =========================================================
    public int     $id;
    public string  $nombre_negocio;
    public ?string $direccion   = null;
    public ?string $telefono    = null;
    public ?string $nit         = null;
    public ?string $pie_pagina  = null;
    public int     $ancho_papel = 80;
    public int     $font_size   = 1;
    public ?string $created_at  = null;
    public ?string $updated_at  = null;

    // Atributos BLOB (no se exponen directamente, se convierten a base64 cuando se necesitan)
    public ?string $logo_data   = null;
    public ?string $logo_tipo   = null;
    public ?string $qr_data     = null;
    public ?string $qr_tipo     = null;

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
        $this->nombre_negocio = (string) ($data['nombre_negocio'] ?? '');
        $this->direccion      = $data['direccion']  ?? null;
        $this->telefono       = $data['telefono']   ?? null;
        $this->nit            = $data['nit']        ?? null;
        $this->pie_pagina     = $data['pie_pagina'] ?? null;
        $this->ancho_papel    = (int) ($data['ancho_papel'] ?? 80);
        $this->font_size      = (int) ($data['font_size']   ?? 1);
        $this->logo_data      = $data['logo_data']  ?? null;
        $this->logo_tipo      = $data['logo_tipo']  ?? null;
        $this->qr_data        = $data['qr_data']    ?? null;
        $this->qr_tipo        = $data['qr_tipo']    ?? null;
        $this->created_at     = $data['created_at'] ?? null;
        $this->updated_at     = $data['updated_at'] ?? null;
        return $this;
    }

    // =========================================================
    //  MÉTODOS DE ACCESO A DATOS
    // =========================================================

    // Obtener la configuración actual
    public function get(): array|false
    {
        return $this->pdo->query("SELECT * FROM configuracion_impresion LIMIT 1")
                         ->fetch(PDO::FETCH_ASSOC);
    }

    // Crear configuración por defecto si no existe
    public function initIfEmpty(): void
    {
        if (!$this->get()) {
            $this->pdo->prepare("
                INSERT INTO configuracion_impresion
                    (nombre_negocio, direccion, telefono, pie_pagina)
                VALUES ('Mi Negocio', 'Dirección del Negocio', 'Teléfono', '¡Gracias por su compra!')
            ")->execute();
        }
    }

    // Guardar (insert o update) con logo, QR y menú PDF opcionales
    public function save(array $data, ?array $logo = null, ?array $qr = null, ?array $menu = null): void
    {
        $config = $this->get();

        $base = [
            $data['nombre_negocio'],
            $data['direccion']   ?? null,
            $data['telefono']    ?? null,
            $data['nit']         ?? null,
            $data['pie_pagina']  ?? null,
            $data['ancho_papel'] ?? 80,
            $data['font_size']   ?? 1,
        ];

        $extra     = [];
        $extraCols = '';
        $extraSets = '';

        if ($logo) {
            $extra[]   = file_get_contents($logo['tmp_name']);
            $extra[]   = pathinfo($logo['name'], PATHINFO_EXTENSION);
            $extraCols .= ', logo_data, logo_tipo';
            $extraSets .= ', logo_data = ?, logo_tipo = ?';
        }
        if ($qr) {
            $extra[]   = file_get_contents($qr['tmp_name']);
            $extra[]   = pathinfo($qr['name'], PATHINFO_EXTENSION);
            $extraCols .= ', qr_data, qr_tipo';
            $extraSets .= ', qr_data = ?, qr_tipo = ?';
        }

        $values = array_merge($base, $extra);

        if (!$config) {
            $placeholders = rtrim(str_repeat('?,', count($values)), ',');
            $this->pdo->prepare("
                INSERT INTO configuracion_impresion
                    (nombre_negocio, direccion, telefono, nit, pie_pagina, ancho_papel, font_size{$extraCols})
                VALUES ({$placeholders})
            ")->execute($values);
        } else {
            $values[] = $config['id'];
            $this->pdo->prepare("
                UPDATE configuracion_impresion
                SET nombre_negocio = ?, direccion = ?, telefono = ?, nit = ?,
                    pie_pagina = ?, ancho_papel = ?, font_size = ? {$extraSets}
                WHERE id = ?
            ")->execute($values);
        }

        // Guardar menú PDF si se subió uno válido
        if ($menu && $menu['error'] === UPLOAD_ERR_OK) {
            if (mime_content_type($menu['tmp_name']) === 'application/pdf') {
                $uploadDir = dirname(__DIR__, 2) . '/public/uploads/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                move_uploaded_file($menu['tmp_name'], $uploadDir . 'menu.pdf');
            }
        }
    }
}