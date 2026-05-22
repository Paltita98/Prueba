<?php
$DB_HOST = 'localhost';
$DB_USER = 'usuarioEmpresa';
$DB_PASS = 'usuarioEmpresa';
$DB_NAME = 'PNK_INMOBILIARIA';

$conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if (!$conn) {
    http_response_code(500);
    echo 'Error de conexión a la base de datos.';
    exit;
}

mysqli_set_charset($conn, 'utf8mb4');
