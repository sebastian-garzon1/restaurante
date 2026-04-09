<?php

$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "123456";
$DB_NAME = "restaurante_palermoche";

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8", $DB_USER, $DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} 
catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

?>