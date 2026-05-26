<?php
include __DIR__ . '/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['id'])) { http_response_code(403); echo json_encode([]); exit; }

$prov = trim($_GET['provincia'] ?? '');
$com = trim($_GET['comuna'] ?? '');
$sec = trim($_GET['sector'] ?? '');
$q = trim($_GET['q'] ?? '');

$where = [];
$params = [];
$types = '';

if ($prov !== '') {
    $where[] = "provincia LIKE ?";
    $params[] = "%$prov%"; $types .= 's';
}
if ($com !== '') {
    $where[] = "comuna LIKE ?";
    $params[] = "%$com%"; $types .= 's';
}
if ($sec !== '') {
    $where[] = "sector LIKE ?";
    $params[] = "%$sec%"; $types .= 's';
}

if ($q !== '') {
    $where[] = "(tipo_propiedad LIKE ? OR descripcion LIKE ? OR provincia LIKE ? OR comuna LIKE ? OR sector LIKE ? OR CAST(id AS CHAR) LIKE ? OR CAST(precio_clp AS CHAR) LIKE ?)";
    for ($i = 0; $i < 7; $i++) {
        $params[] = "%$q%";
        $types .= 's';
    }
}

$sql = "SELECT id, tipo_propiedad, descripcion, banos, dormitorios, area_total, area_construida, precio_clp, precio_uf, fecha_publicacion, solicitar_visita, bodega, estacionamiento, logia, cocina_amoblada, antejardin, patio_trasero, piscina, foto_url, provincia, comuna, sector FROM propiedades";
if (!empty($where)) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' LIMIT 50';

$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $rows[] = $r;
    }
    mysqli_stmt_close($stmt);
    echo json_encode($rows);
    exit;
}

// fallback empty
echo json_encode([]);
