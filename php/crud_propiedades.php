<?php
include __DIR__ . '/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('Content-Type: application/json; charset=utf-8');

// Control de acceso seguro y protección de sesión activa
if (!isset($_SESSION['id'])) { 
    http_response_code(403); 
    echo json_encode(['ok' => false, 'error' => 'access_denied']); 
    exit; 
}

// Verificación robusta mediante Token CSRF contra ataques de suplantación
function verify_csrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function bind_stmt_params($stmt, string $types, array $values) {
    $bindArgs = [$stmt, $types];
    foreach ($values as $index => $value) {
        $bindArgs[] = &$values[$index];
    }
    return call_user_func_array('mysqli_stmt_bind_param', $bindArgs);
}

function normalize_upload_files(array $files): array {
    $normalized = [];
    if (!isset($files['name'])) return $normalized;
    if (is_array($files['name'])) {
        foreach ($files['name'] as $index => $name) {
            $normalized[] = [
                'name' => $name,
                'type' => $files['type'][$index] ?? '',
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$index] ?? 0,
            ];
        }
        return $normalized;
    }
    $normalized[] = $files;
    return $normalized;
}

function upload_property_photos(array $files): array {
    $uploadedFiles = [];
    $uploadDir = __DIR__ . '/../uploads/properties';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    foreach ($files as $file) {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
        $orig = basename($file['name'] ?? '');
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) continue;
        $targetName = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $targetPath = $uploadDir . '/' . $targetName;
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $uploadedFiles[] = $targetName;
        }
    }
    return $uploadedFiles;
}

function delete_property_files(array $filenames): void {
    $uploadDir = __DIR__ . '/../uploads/properties';
    foreach (array_unique($filenames) as $filename) {
        if (!$filename || $filename === 'casa1.webp') continue;
        $path = $uploadDir . '/' . basename($filename);
        if (is_file($path)) { @unlink($path); }
    }
}

$accion = $_POST['accion'] ?? '';

// 1. OPERACIÓN: INSERTAR (CREATE)
if ($accion === 'insertar') {
    $csrf = $_POST['csrf'] ?? '';
    if (!verify_csrf($csrf)) { echo json_encode(['ok' => false, 'error' => 'Token CSRF inválido o expirado.']); exit; }

    $uploadedPhotos = upload_property_photos(normalize_upload_files($_FILES['fotos'] ?? []));
    
    // Si no sube fotos, se le asigna la fotografía por defecto exigida por la pauta
    $foto_url = 'casa1.webp';
    if (count($uploadedPhotos) > 0) {
        $foto_url = $uploadedPhotos[0];
    }
    if (count($uploadedPhotos) > 10) {
        delete_property_files($uploadedPhotos);
        echo json_encode(['ok' => false, 'error' => 'Supera el límite máximo de 10 fotografías.']);
        exit;
    }

    $tipo = trim($_POST['tipo_propiedad'] ?? '');
    $desc = trim($_POST['descripcion'] ?? '');
    $banos = intval($_POST['banos'] ?? 0);
    $dormitorios = intval($_POST['dormitorios'] ?? 0);
    $area_total = floatval($_POST['area_total'] ?? 0);
    $area_const = floatval($_POST['area_construida'] ?? 0);
    $clp = intval($_POST['precio_clp'] ?? 0);
    $uf = floatval($_POST['precio_uf'] ?? 0);
    $fecha = $_POST['fecha_publicacion'] ?? null;
    $visita = $_POST['solicitar_visita'] ?? 'No';
    $bodega = $_POST['bodega'] ?? 'No';
    
    // Normalizar a cadena "Sí"/"No" para las características del formulario admin
    $estacionamiento = $_POST['estacionamiento'] ?? 'No';
    $logia = $_POST['logia'] ?? 'No';
    $cocina = $_POST['cocina_amoblada'] ?? 'No';
    $antejardin = $_POST['antejardin'] ?? 'No';
    $patio = $_POST['patio_trasero'] ?? 'No';
    $piscina = $_POST['piscina'] ?? 'No';
    
    $provincia = trim($_POST['provincia'] ?? '');
    $comuna = trim($_POST['comuna'] ?? '');
    $sector = trim($_POST['sector'] ?? '');

    mysqli_begin_transaction($conn);

    // Prepared statements para mitigar OWASP Top 10 Injection
    $stmt = mysqli_prepare($conn, "INSERT INTO propiedades (tipo_propiedad, descripcion, banos, dormitorios, area_total, area_construida, precio_clp, precio_uf, fecha_publicacion, solicitar_visita, bodega, estacionamiento, logia, cocina_amoblada, antejardin, patio_trasero, piscina, foto_url, provincia, comuna, sector) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        mysqli_rollback($conn);
        delete_property_files($uploadedPhotos);
        echo json_encode(['ok' => false, 'error' => 'Error al preparar base de datos.']);
        exit;
    }
    
    // Mapeo estricto de tipos de datos en MySQL
    $bindTypes = 'ssiiddidsssssssssssss';
    $bindValues = [$tipo, $desc, $banos, $dormitorios, $area_total, $area_const, $clp, $uf, $fecha, $visita, $bodega, $estacionamiento, $logia, $cocina, $antejardin, $patio, $piscina, $foto_url, $provincia, $comuna, $sector];
    bind_stmt_params($stmt, $bindTypes, $bindValues);
    $ok = mysqli_stmt_execute($stmt);
    
    if (!$ok) {
        mysqli_rollback($conn);
        mysqli_stmt_close($stmt);
        delete_property_files($uploadedPhotos);
        echo json_encode(['ok' => false, 'error' => 'No se pudieron guardar las características básicas.']);
        exit;
    }
    
    $insert_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    // Si subió múltiples fotos, poblamos la tabla de la galería (Punto 1. Galería de imágenes)
    if (count($uploadedPhotos) > 0) {
        $stmtImage = mysqli_prepare($conn, "INSERT INTO propiedad_imagenes (propiedad_id, filename, is_default) VALUES (?, ?, ?)");
        if ($stmtImage) {
            foreach ($uploadedPhotos as $index => $filename) {
                $isDefault = $index === 0 ? 1 : 0;
                mysqli_stmt_bind_param($stmtImage, 'isi', $insert_id, $filename, $isDefault);
                mysqli_stmt_execute($stmtImage);
            }
            mysqli_stmt_close($stmtImage);
        }
    }

    mysqli_commit($conn);
    echo json_encode(['ok' => true, 'id' => $insert_id, 'foto_url' => $foto_url]);
    exit;
}

// 2. OPERACIÓN: MODIFICAR (UPDATE)
if ($accion === 'modificar') {
    $csrf = $_POST['csrf'] ?? '';
    if (!verify_csrf($csrf)) { echo json_encode(['ok' => false, 'error' => 'Token CSRF inválido o expirado.']); exit; }

    $id = intval($_POST['id'] ?? 0);
    $tipo = trim($_POST['tipo_propiedad'] ?? '');
    $desc = trim($_POST['descripcion'] ?? '');
    $banos = intval($_POST['banos'] ?? 0);
    $dormitorios = intval($_POST['dormitorios'] ?? 0);
    $area_total = floatval($_POST['area_total'] ?? 0);
    $area_const = floatval($_POST['area_construida'] ?? 0);
    $clp = intval($_POST['precio_clp'] ?? 0);
    $uf = floatval($_POST['precio_uf'] ?? 0);
    $fecha = $_POST['fecha_publicacion'] ?? null;
    $visita = $_POST['solicitar_visita'] ?? 'No';
    $bodega = $_POST['bodega'] ?? 'No';
    $estacionamiento = $_POST['estacionamiento'] ?? 'No';
    $logia = $_POST['logia'] ?? 'No';
    $cocina = $_POST['cocina_amoblada'] ?? 'No';
    $antejardin = $_POST['antejardin'] ?? 'No';
    $patio = $_POST['patio_trasero'] ?? 'No';
    $piscina = $_POST['piscina'] ?? 'No';
    $provincia = trim($_POST['provincia'] ?? '');
    $comuna = trim($_POST['comuna'] ?? '');
    $sector = trim($_POST['sector'] ?? '');
    $currentPhoto = trim($_POST['current_foto_url'] ?? 'casa1.webp');

    $uploadedPhotos = upload_property_photos(normalize_upload_files($_FILES['fotos'] ?? []));
    if (count($uploadedPhotos) > 10) {
        delete_property_files($uploadedPhotos);
        echo json_encode(['ok' => false, 'error' => 'Supera el límite de 10 fotografías.']);
        exit;
    }
    
    $foto_url = $currentPhoto;
    if (!empty($uploadedPhotos)) {
        $foto_url = $uploadedPhotos[0];
    }

    mysqli_begin_transaction($conn);

    $stmt = mysqli_prepare($conn, "UPDATE propiedades SET tipo_propiedad = ?, descripcion = ?, banos = ?, dormitorios = ?, area_total = ?, area_construida = ?, precio_clp = ?, precio_uf = ?, fecha_publicacion = ?, solicitar_visita = ?, bodega = ?, estacionamiento = ?, logia = ?, cocina_amoblada = ?, antejardin = ?, patio_trasero = ?, piscina = ?, foto_url = ?, provincia = ?, comuna = ?, sector = ? WHERE id = ?");
    if (!$stmt) {
        mysqli_rollback($conn);
        delete_property_files($uploadedPhotos);
        echo json_encode(['ok' => false, 'error' => 'Error al preparar la actualización en la BD.']);
        exit;
    }
    
    $bindTypes = 'ssiiddidsssssssssssssi';
    $bindValues = [$tipo, $desc, $banos, $dormitorios, $area_total, $area_const, $clp, $uf, $fecha, $visita, $bodega, $estacionamiento, $logia, $cocina, $antejardin, $patio, $piscina, $foto_url, $provincia, $comuna, $sector, $id];
    bind_stmt_params($stmt, $bindTypes, $bindValues);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok && !empty($uploadedPhotos)) {
        $stmtImage = mysqli_prepare($conn, "INSERT INTO propiedad_imagenes (propiedad_id, filename, is_default) VALUES (?, ?, ?)");
        if ($stmtImage) {
            foreach ($uploadedPhotos as $index => $filename) {
                $isDefault = $index === 0 ? 1 : 0;
                mysqli_stmt_bind_param($stmtImage, 'isi', $id, $filename, $isDefault);
                mysqli_stmt_execute($stmtImage);
            }
            mysqli_stmt_close($stmtImage);
        }
    }

    if ($ok) {
        mysqli_commit($conn);
        echo json_encode(['ok' => true, 'foto_url' => $foto_url]);
    } else {
        mysqli_rollback($conn);
        delete_property_files($uploadedPhotos);
        echo json_encode(['ok' => false, 'error' => 'No se pudieron actualizar los registros.']);
    }
    exit;
}

// 3. OPERACIÓN: ELIMINAR (DELETE)
if ($accion === 'eliminar') {
    $csrf = $_POST['csrf'] ?? '';
    if (!verify_csrf($csrf)) { echo json_encode(['ok' => false, 'error' => 'Token CSRF inválido.']); exit; }
    $id = intval($_POST['id'] ?? 0);

    $filenamesToDelete = [];
    $stmtPhotos = mysqli_prepare($conn, "SELECT foto_url FROM propiedades WHERE id = ? LIMIT 1");
    if ($stmtPhotos) {
        mysqli_stmt_bind_param($stmtPhotos, 'i', $id);
        mysqli_stmt_execute($stmtPhotos);
        mysqli_stmt_bind_result($stmtPhotos, $mainPhoto);
        if (mysqli_stmt_fetch($stmtPhotos) && $mainPhoto) {
            $filenamesToDelete[] = $mainPhoto;
        }
        mysqli_stmt_close($stmtPhotos);
    }

    $stmt = mysqli_prepare($conn, "DELETE FROM propiedades WHERE id = ?");
    if (!$stmt) { echo json_encode(['ok' => false, 'error' => 'Error estructural al preparar borrado.']); exit; }
    mysqli_stmt_bind_param($stmt, 'i', $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    if ($ok) {
        delete_property_files($filenamesToDelete);
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'No se pudo eliminar de la base de datos relacional.']);
    }
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Acción interna inválida.']);
?>