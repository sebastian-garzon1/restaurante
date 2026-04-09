<?php

function verificarConfiguracionInicial($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM configuracion_impresion LIMIT 1");
        $config = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$config) {
            $pdo->prepare("
                INSERT INTO configuracion_impresion
                (nombre_negocio, direccion, telefono, pie_pagina)
                VALUES ('Mi Negocio', 'Dirección del Negocio', 'Teléfono', '¡Gracias por su compra!')
            ")->execute();
        }
    } catch (Exception $e) {
        error_log("Error al verificar config inicial: ".$e->getMessage());
    }
}

verificarConfiguracionInicial($pdo);

// Configuiración
$router->get('/configuracion', function() use($pdo) {
    requireRole([ROLE_ADMIN]);
    $rol = currentRole();
    // Obtener configuración
    $stmt = $pdo->query("SELECT * FROM configuracion_impresion LIMIT 1");
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$config) {
        $config = [
            "nombre_negocio" => "",
            "direccion"      => "",
            "telefono"       => "",
            "nit"            => "",
            "pie_pagina"     => "",
            "ancho_papel"    => 80,
            "font_size"      => 1
        ];
    } else {
        unset($config["logo_data"]);
        unset($config["qr_data"]);
    }

    require __DIR__ . "/../../views/configuracion.php";
});


// Configuiración
$router->post('/configuracion', function() use($pdo) {
    isLoggedIn();
    $nombre_negocio = $_POST['nombre_negocio'] ?? null;
    $direccion      = $_POST['direccion'] ?? null;
    $telefono       = $_POST['telefono'] ?? null;
    $nit            = $_POST['nit'] ?? null;
    $pie_pagina     = $_POST['pie_pagina'] ?? null;
    $ancho_papel    = $_POST['ancho_papel'] ?? 80;
    $font_size      = $_POST['font_size'] ?? 1;

    // Ver si ya existe config
    $stmt = $pdo->query("SELECT * FROM configuracion_impresion LIMIT 1");
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    $values = [
        $nombre_negocio,
        $direccion,
        $telefono,
        $nit,
        $pie_pagina,
        $ancho_papel,
        $font_size
    ];

    // Archivos
    $logo_uploaded = isset($_FILES['logo']) && $_FILES['logo']['size'] > 0;
    $qr_uploaded   = isset($_FILES['qr'])   && $_FILES['qr']['size'] > 0;

    if ($logo_uploaded) {
        $values[] = file_get_contents($_FILES['logo']['tmp_name']);
        $values[] = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
    }

    if ($qr_uploaded) {
        $values[] = file_get_contents($_FILES['qr']['tmp_name']);
        $values[] = pathinfo($_FILES['qr']['name'], PATHINFO_EXTENSION);
    }

    // INSERTAR
    if (!$config) {

        $sql = "
            INSERT INTO configuracion_impresion
            (nombre_negocio, direccion, telefono, nit, pie_pagina,
                ancho_papel, font_size
        ";

        if ($logo_uploaded) $sql .= ", logo_data, logo_tipo";
        if ($qr_uploaded)   $sql .= ", qr_data, qr_tipo";

        $sql .= ") VALUES (" . str_repeat("?,", count($values) - 1) . "?)";

        $pdo->prepare($sql)->execute($values);

    } 
    // ACTUALIZAR
    else {

        $sql = "
            UPDATE configuracion_impresion SET
                nombre_negocio = ?, direccion = ?, telefono = ?, nit = ?,
                pie_pagina = ?, ancho_papel = ?, font_size = ?
        ";

        if ($logo_uploaded) $sql .= ", logo_data = ?, logo_tipo = ?";
        if ($qr_uploaded)   $sql .= ", qr_data = ?, qr_tipo = ?";

        $sql .= " WHERE id = ?";

        $values[] = $config["id"];

        $pdo->prepare($sql)->execute($values);
    }


    // Cambiar archivo de menu
    if ( isset($_FILES['menu']) ) {
        $archivo = $_FILES['menu'];

        if ($archivo['error'] == UPLOAD_ERR_OK) {
            // Validar que sea PDF
            $mime = mime_content_type($archivo['tmp_name']);
            if ($mime == 'application/pdf') {
                // Carpeta destino
                $uploadDir = dirname(__DIR__, 2) . '/public/uploads/';

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                // Nombre fijo
                $nombreFinal = 'menu.pdf';
                $rutaFinal = $uploadDir . $nombreFinal;

                // Sobrescribe si existe (PHP lo hace automáticamente)
                move_uploaded_file($archivo['tmp_name'], $rutaFinal);
            }
        }
    }

    header("Location: /configuracion");
});