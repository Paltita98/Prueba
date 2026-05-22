<?php
include __DIR__ . '/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id'])) {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

$sql = 'SELECT id, rut, nombre, fecha_nacimiento, correo, sexo, telefono, estado FROM usuarios ORDER BY id DESC';
$res = mysqli_query($conn, $sql);
$rows = [];

if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
    }
}

echo json_encode($rows, JSON_UNESCAPED_UNICODE);
