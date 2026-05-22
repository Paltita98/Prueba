<?php
include 'db.php';
session_start();

$correo = mysqli_real_escape_string($conn, $_POST['correo']);
$pass   = $_POST['password'];

if (empty($correo) || empty($pass)) {
    echo "vacio"; // Respuesta para atrapar con SweetAlert2 [cite: 102-104]
    exit;
}

$sql = "SELECT * FROM usuarios WHERE correo = '$correo' AND estado = 1"; [cite: 107-108]
$res = mysqli_query($conn, $sql);
$user = mysqli_fetch_assoc($res);

// Verificación segura con Bcrypt [cite: 98-99, 101]
if ($user && password_verify($pass, $user['password_hash'])) {
    $_SESSION['id'] = $user['id']; [cite: 113]
    $_SESSION['nombre'] = $user['nombre']; [cite: 113]
    $_SESSION['rol'] = 'gestor'; [cite: 113]
    echo "success"; [cite: 111]
} else {
    echo "denied"; [cite: 111]
}
?>