<?php
// Establecer parámetros de cookie seguros antes de iniciar sesión
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

header('Content-Type: application/json; charset=utf-8');

const ADMIN_USER = 'Admin';
const ADMIN_PASS = 'SoyelAdmin123@';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_admin'])) {
    $usuario = trim($_POST['usuario'] ?? '');
    $pass = $_POST['password'] ?? '';

    if ($usuario === ADMIN_USER && $pass === ADMIN_PASS) {
        session_regenerate_id(true);
        $_SESSION['id'] = 999999;
        $_SESSION['nombre'] = ADMIN_USER;
        $_SESSION['rol'] = 'admin';
        echo json_encode(['status' => 'success', 'role' => 'admin']);
    } else {
        echo json_encode(['status' => 'denied']);
    }
    exit;
}

require_once __DIR__ . '/db.php';

if (!$conn) {
    echo json_encode(['status' => 'error', 'reason' => 'db_connection']);
    exit;
}

function registrarUsuario($conn, array $datos) {
    $rut = trim($datos['rut'] ?? '');
    $nombre = trim($datos['nombre'] ?? '');
    $fecha_nacimiento = $datos['fecha_nacimiento'] ?? '';
    $correo = trim($datos['correo'] ?? '');
    $pass = $datos['password'] ?? '';
    $sexo = trim($datos['sexo'] ?? '');
    $telefono = trim($datos['telefono'] ?? '');
    $certificadoPath = trim($datos['certificado_path'] ?? '');

    if ($rut === '' || $nombre === '' || $fecha_nacimiento === '' || $correo === '' || $pass === '' || $sexo === '' || $telefono === '' || $certificadoPath === '') {
        return ['ok' => false, 'error' => 'missing_fields'];
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'invalid_email'];
    }

    if (!preg_match('/^\d{8,15}$/', preg_replace('/\s+/', '', $telefono))) {
        return ['ok' => false, 'error' => 'invalid_phone'];
    }

    if (!preg_match('/^\d{7,8}-[\dkK]$/', preg_replace('/\./', '', strtoupper($rut)))) {
        return ['ok' => false, 'error' => 'invalid_rut'];
    }

    $rut = preg_replace('/[.\s]/', '', strtoupper($rut));

    $stmt = mysqli_prepare($conn, 'SELECT id FROM usuarios WHERE correo = ? OR rut = ? LIMIT 1');
    if (!$stmt) {
        return ['ok' => false, 'error' => 'db_prepare'];
    }
    mysqli_stmt_bind_param($stmt, 'ss', $correo, $rut);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    if (mysqli_stmt_num_rows($stmt) > 0) {
        mysqli_stmt_close($stmt);
        return ['ok' => false, 'error' => 'user_exists'];
    }
    mysqli_stmt_close($stmt);

    $hash = password_hash($pass, PASSWORD_BCRYPT);
    $stmt = mysqli_prepare($conn, 'INSERT INTO usuarios (rut, nombre, fecha_nacimiento, correo, password_hash, sexo, telefono, certificado_path, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)');
    if (!$stmt) {
        return ['ok' => false, 'error' => 'db_prepare'];
    }
    mysqli_stmt_bind_param($stmt, 'ssssssss', $rut, $nombre, $fecha_nacimiento, $correo, $hash, $sexo, $telefono, $certificadoPath);
    $exec = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return ['ok' => (bool)$exec];
}

// Registro vía POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    // Sólo permitimos registros de gestores desde este formulario
    $role = trim(strval($_POST['role'] ?? 'gestor'));
    if ($role !== 'gestor') {
        echo json_encode(['status' => 'error', 'reason' => 'role_not_allowed']);
        exit;
    }

    $certificadoPath = '';
    if (!empty($_FILES['certificado']) && is_array($_FILES['certificado']) && $_FILES['certificado']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['certificado']['tmp_name'];
        $originalName = basename($_FILES['certificado']['name']);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = ['pdf'];
        if (in_array($extension, $allowedExtensions, true)) {
            $uploadDir = __DIR__ . '/../uploads/certificados';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $targetName = time() . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
            $targetPath = $uploadDir . '/' . $targetName;
            if (move_uploaded_file($tmpName, $targetPath)) {
                $certificadoPath = 'uploads/certificados/' . $targetName;
            }
        }
    }

    $res = registrarUsuario($conn, [
        'rut' => $_POST['rut'] ?? '',
        'nombre' => $_POST['nombre'] ?? '',
        'fecha_nacimiento' => $_POST['fecha_nacimiento'] ?? '',
        'correo' => $_POST['correo'] ?? '',
        'password' => $_POST['password'] ?? '',
        'sexo' => $_POST['sexo'] ?? '',
        'telefono' => $_POST['telefono'] ?? '',
        'certificado_path' => $certificadoPath,
    ]);

    if ($res['ok']) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'reason' => $res['error'] ?? 'unknown']);
    }
    exit;
}

// LOGIN: validar credenciales con prepared statements
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $correo = trim($_POST['correo'] ?? '');
    $pass = $_POST['password'] ?? '';

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'denied']);
        exit;
    }

    $stmt = mysqli_prepare($conn, 'SELECT id, nombre, password_hash FROM usuarios WHERE correo = ? AND estado = 1 LIMIT 1');
    if (!$stmt) { echo json_encode(['status' => 'error']); exit; }
    mysqli_stmt_bind_param($stmt, 's', $correo);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $id, $nombre, $password_hash);
    $found = mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    if ($found && password_verify($pass, $password_hash)) {
        session_regenerate_id(true);
        $_SESSION['id'] = $id;
        $_SESSION['nombre'] = $nombre;
        $_SESSION['rol'] = 'gestor';
        echo json_encode(['status' => 'success', 'role' => 'gestor']);
    } else {
        echo json_encode(['status' => 'denied']);
    }
    exit;
}

?>