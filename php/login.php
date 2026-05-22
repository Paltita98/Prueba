<?php
// Compatibilidad: este archivo acepta login por formulario (redirige)
// y mantiene compatibilidad con `auth.php` para peticiones AJAX.

require __DIR__ . '/db.php';

// Parámetros de cookie coherentes con auth.php
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

// Constantes de admin (mantener sincronizado con auth.php)
if (!defined('ADMIN_USER')) define('ADMIN_USER', 'Admin');
if (!defined('ADMIN_PASS')) define('ADMIN_PASS', 'SoyelAdmin123@');

// Detectar si la petición viene de fetch / AJAX (espera JSON)
$acceptsJson = (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || $acceptsJson;

// Si es AJAX, delegamos a auth.php para mantener la API JSON
if ($isAjax) {
    require __DIR__ . '/auth.php';
    exit;
}

// Manejo clásico por formulario (POST desde un <form>)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Login admin (formulario que envía 'login_admin' o campos 'usuario' y 'password')
    if (isset($_POST['login_admin']) || (isset($_POST['usuario']) && isset($_POST['password']) && ($_POST['usuario'] === ADMIN_USER))) {
        $usuario = trim($_POST['usuario'] ?? '');
        $pass = $_POST['password'] ?? '';
        if ($usuario === ADMIN_USER && $pass === ADMIN_PASS) {
            session_regenerate_id(true);
            $_SESSION['id'] = 999999;
            $_SESSION['nombre'] = ADMIN_USER;
            $_SESSION['rol'] = 'admin';
            header('Location: ../Administrar.html');
            exit;
        } else {
            header('Location: ../index.html?login=denied');
            exit;
        }
    }

    // Login usuario (correo + password)
    if (isset($_POST['login']) || (isset($_POST['correo']) && isset($_POST['password']))) {
        $correo = trim($_POST['correo'] ?? '');
        $pass = $_POST['password'] ?? '';

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            header('Location: ../index.html?login=denied');
            exit;
        }

        $stmt = mysqli_prepare($conn, 'SELECT id, nombre, password_hash FROM usuarios WHERE correo = ? AND estado = 1 LIMIT 1');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $correo);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $id, $nombre, $password_hash);
            $found = mysqli_stmt_fetch($stmt);
            mysqli_stmt_close($stmt);

            if ($found && password_verify($pass, $password_hash)) {
                session_regenerate_id(true);
                $_SESSION['id'] = $id;
                $_SESSION['nombre'] = $nombre;
                header('Location: ../pagina_propietario.html');
                exit;
            }
        }

        header('Location: ../index.html?login=denied');
        exit;
    }
}

// Si llegamos aquí sin POST, redirigimos a la página principal
header('Location: ../index.html');
exit;

if (empty($_SESSION)) {
    //2 rescatamos variables locales
    $usuario = $_POST['usuario'];
    $clave = $_POST['clave'];

    //3 armamos la consulta
    $query = "SELECT id,nombre,apellido,correo,clave,perfil,foto FROM usuarios WHERE correo = '" . $usuario . "'";

    //3 ejecutamos la consulta
    $resultado = mysqli_query($connect, $query);

    //4 verificamos las coincidencias
    if (mysqli_num_rows($resultado) == 0) {
        header('location: index.html');
    } else {
        $fila = mysqli_fetch_assoc($resultado);
        //verificamos la contraseña
        if (password_verify($clave, $fila['clave'])) {
            //si el usuario es valido creamos las variables de sesión
            $_SESSION['usuario'] = $fila['nombre'] . " " . $fila['apellido'];
            $_SESSION['correo'] = $fila['correo'];
            $_SESSION['perfil'] = $fila['perfil'];
        } else {
            header('location: index.html');
        }
    }
}


// Mostramos todos los usuarios
$usuarios = "";
$query2 = "SELECT id,nombre,apellido,correo,clave,perfil,foto FROM usuarios";
$resultado2 = mysqli_query($connect, $query2);
while ($fila = mysqli_fetch_array($resultado2)) {
    $usuarios .= "<tr>";
    $usuarios .= "<td>" . $fila[0] . "</td>";
    $usuarios .= "<td>" . $fila[1] . "</td>";
    $usuarios .= "<td>" . $fila[2] . "</td>";
    $usuarios .= "<td>" . $fila[3] . "</td>";
    $usuarios .= "<td>" . $fila[5] . "</td>";
    $usuarios .= "<td>
                    <img height='40px' width='auto' src='img/usuarios/".$fila[6]."' alt='imagen del usuario'></td>";
    $usuarios .= "<td>  
                    <a href='usuarios.php?id=$fila[0]'><i class='bi bi-gear text-danger'></i></a>
                </td>";
    $usuarios .= "</tr>";
}

include("cabecera.php");
include("menu.php");
?>
<div class="container">
    <div class="row mt-5">
        <div class="col">
            <h3>Listado de Usuarios</h3>
            <table class="table table-hover table-striped table-dark">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Apellido</th>
                        <th>Correo</th>
                        <th>Perfil</th>
                        <th>Fotografía</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php echo $usuarios; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!--------------------------------------------------------------------------------------->

<!---------------------INICIO MODAL ----------------------------------------------------->
<div class="modal fade modal-xl" id="opcionesModal" tabindex="-1" aria-labelledby="opcionesModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="exampleModalLabel">Mantenedor de Usuarios</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="" method="post">
                    <div class="row">
                        <div class="col-4">
                            <div class="mb-3">
                                <label for="id" class="form-label">ID:</label>
                                <input type="text" class="form-control" id="id" name="id" readonly value="">
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="mb-3">
                                <label for="nombre" class="form-label">Nombre:</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" value="">
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="mb-3">
                                <label for="apellido" class="form-label">Apellido:</label>
                                <input type="text" class="form-control" id="apellido" name="apellido" value="">
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="mb-3">
                                <label for="correo" class="form-label">Correo:</label>
                                <input type="email" class="form-control" id="correo" name="correo" value="">
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="mb-3">
                                <label for="clave1" class="form-label">Contraseña:</label>
                                <input type="password" class="form-control" id="clave1" name="clave1" value="">
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="mb-3">
                                <label for="clave2" class="form-label">Repita Contraseña:</label>
                                <input type="password" class="form-control" id="clave2" name="clave2" value="">
                            </div>
                        </div>
                        <div class="">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <input type="submit" class="btn btn-warning" id="modificar" name="modificar" value="Modificar">
                            <input type="submit" class="btn btn-danger" id="eliminar" name="eliminar" value="Eliminar">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer"></div>
        </div>
    </div>
</div>
<!---------------------- FIN MODAL ------------------------------------------------------>
</div>

<?php include("footer.php"); ?>
