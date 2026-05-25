<?php
include __DIR__ . '/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || (($_GET['format'] ?? '') === 'json')) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'access_denied']);
    } else {
        header('Location: ../index.html');
    }
    exit;
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function wants_json(): bool {
    return (($_GET['format'] ?? '') === 'json') || ($_SERVER['REQUEST_METHOD'] === 'POST');
}

function normalize_rut(string $rut): string {
    return strtoupper(preg_replace('/[.\s]/', '', trim($rut)));
}

function format_rut(string $rut): string {
    $rut = normalize_rut($rut);
    $parts = explode('-', $rut);
    if (count($parts) !== 2) return $rut;

    $numero = $parts[0];
    $dv = $parts[1];
    $cuerpo = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $numero);
    return $cuerpo . '-' . $dv;
}

function validar_rut(string $rut): bool {
    $rut = normalize_rut($rut);
    if (!preg_match('/^\d{7,8}-[\dK]$/', $rut)) {
        return false;
    }

    [$numero, $dv] = explode('-', $rut);
    $suma = 0;
    $multiplicador = 2;

    for ($i = strlen($numero) - 1; $i >= 0; $i--) {
        $suma += intval($numero[$i]) * $multiplicador;
        $multiplicador = $multiplicador === 7 ? 2 : $multiplicador + 1;
    }

    $resto = 11 - ($suma % 11);
    $dvCalculado = $resto === 11 ? '0' : ($resto === 10 ? 'K' : (string)$resto);
    return $dvCalculado === $dv;
}

function validar_email(string $correo): bool {
    $correo = trim($correo);
    if ($correo === '') return false;
    $partes = explode('@', $correo);
    if (count($partes) !== 2) return false;
    if (strlen($partes[0]) < 3) return false;
    if ($partes[1] === '' || str_starts_with($partes[1], '.') || str_ends_with($partes[1], '.')) return false;
    return str_contains($partes[1], '.');
}

function validar_password_robusta(string $password): bool {
    return (bool) preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*[^A-Za-z0-9]).{8,}$/', $password);
}

function validar_telefono(string $telefono): bool {
    $telefono = preg_replace('/\s+/', '', $telefono);
    return (bool) preg_match('/^\d{8,15}$/', $telefono);
}

function subir_pdf_certificado(array $file): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'invalid_file'];
    }

    $original = basename($file['name'] ?? '');
    $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if ($extension !== 'pdf') {
        return ['ok' => false, 'error' => 'invalid_pdf'];
    }

    $uploadDir = __DIR__ . '/../uploads/certificados';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $targetName = time() . '_' . bin2hex(random_bytes(6)) . '.pdf';
    $targetPath = $uploadDir . '/' . $targetName;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['ok' => false, 'error' => 'upload_failed'];
    }

    return ['ok' => true, 'path' => 'uploads/certificados/' . $targetName];
}

function borrar_archivo_certificado(?string $path): void {
    if (!$path) return;
    $clean = str_replace('\\', '/', $path);
    if (!str_starts_with($clean, 'uploads/certificados/')) return;
    $fullPath = __DIR__ . '/../' . $clean;
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

function obtener_usuario_por_id($conn, int $id): ?array {
    $stmt = mysqli_prepare($conn, 'SELECT id, rut, nombre, fecha_nacimiento, correo, password_hash, sexo, telefono, certificado_path, estado FROM usuarios WHERE id = ? LIMIT 1');
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function listar_usuarios($conn): array {
    $sql = 'SELECT id, rut, nombre, fecha_nacimiento, correo, sexo, telefono, certificado_path, estado FROM usuarios ORDER BY id DESC';
    $res = mysqli_query($conn, $sql);
    $rows = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function usuarios_tiene_columna($conn, string $column): bool {
    static $cache = [];
    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }

    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = ?");
    if (!$stmt) {
        $cache[$column] = false;
        return false;
    }

    mysqli_stmt_bind_param($stmt, 's', $column);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $count);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    $cache[$column] = ((int)$count > 0);
    return $cache[$column];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    header('Content-Type: application/json; charset=utf-8');

    $accion = $_POST['accion'] ?? '';
    $id = intval($_POST['id'] ?? 0);

    if ($accion === 'insertar') {
        $rut = trim($_POST['rut'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $fecha = trim($_POST['fecha_nacimiento'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $password = $_POST['password'] ?? '';
        $sexo = trim($_POST['sexo'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $estado = intval($_POST['estado'] ?? 1);

        if ($rut === '' || $nombre === '' || $fecha === '' || $correo === '' || $password === '' || $sexo === '' || $telefono === '') {
            echo json_encode(['ok' => false, 'error' => 'missing_fields']);
            exit;
        }
        if (!validar_rut($rut)) {
            echo json_encode(['ok' => false, 'error' => 'invalid_rut']);
            exit;
        }
        if (!validar_email($correo)) {
            echo json_encode(['ok' => false, 'error' => 'invalid_email']);
            exit;
        }
        if (!validar_password_robusta($password)) {
            echo json_encode(['ok' => false, 'error' => 'invalid_password']);
            exit;
        }
        if (!validar_telefono($telefono)) {
            echo json_encode(['ok' => false, 'error' => 'invalid_phone']);
            exit;
        }
        if (empty($_FILES['certificado'] ?? null) || ($_FILES['certificado']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            echo json_encode(['ok' => false, 'error' => 'missing_pdf']);
            exit;
        }

        $stmtDup = mysqli_prepare($conn, 'SELECT id FROM usuarios WHERE correo = ? OR rut = ? LIMIT 1');
        if (!$stmtDup) {
            echo json_encode(['ok' => false, 'error' => 'db_prepare']);
            exit;
        }
        $rutNormalizado = normalize_rut($rut);
        mysqli_stmt_bind_param($stmtDup, 'ss', $correo, $rutNormalizado);
        mysqli_stmt_execute($stmtDup);
        mysqli_stmt_store_result($stmtDup);
        if (mysqli_stmt_num_rows($stmtDup) > 0) {
            mysqli_stmt_close($stmtDup);
            echo json_encode(['ok' => false, 'error' => 'user_exists']);
            exit;
        }
        mysqli_stmt_close($stmtDup);

        $upload = subir_pdf_certificado($_FILES['certificado']);
        if (!$upload['ok']) {
            echo json_encode(['ok' => false, 'error' => $upload['error']]);
            exit;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $sqlInsert = 'INSERT INTO usuarios (rut, nombre, fecha_nacimiento, correo, password_hash, sexo, telefono, certificado_path, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';

        $stmt = mysqli_prepare($conn, $sqlInsert);
        if (!$stmt) {
            borrar_archivo_certificado($upload['path']);
            echo json_encode(['ok' => false, 'error' => 'db_prepare', 'details' => mysqli_error($conn)]);
            exit;
        }

        mysqli_stmt_bind_param($stmt, 'ssssssssi', $rutNormalizado, $nombre, $fecha, $correo, $hash, $sexo, $telefono, $upload['path'], $estado);
        $ok = mysqli_stmt_execute($stmt);
        $insertId = mysqli_insert_id($conn);
        $stmtError = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);

        if ($ok) {
            echo json_encode(['ok' => true, 'id' => $insertId]);
        } else {
            borrar_archivo_certificado($upload['path']);
            echo json_encode(['ok' => false, 'error' => 'insert_failed', 'details' => $stmtError ?: mysqli_error($conn)]);
        }
        exit;
    }

    if ($accion === 'modificar') {
        if ($id <= 0) {
            echo json_encode(['ok' => false, 'error' => 'missing_id']);
            exit;
        }

        $actual = obtener_usuario_por_id($conn, $id);
        if (!$actual) {
            echo json_encode(['ok' => false, 'error' => 'not_found']);
            exit;
        }

        $rut = trim($_POST['rut'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $fecha = trim($_POST['fecha_nacimiento'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $sexo = trim($_POST['sexo'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $estado = intval($_POST['estado'] ?? 1);

        if ($rut === '' || $nombre === '' || $fecha === '' || $correo === '' || $sexo === '' || $telefono === '') {
            echo json_encode(['ok' => false, 'error' => 'missing_fields']);
            exit;
        }
        if (!validar_rut($rut)) {
            echo json_encode(['ok' => false, 'error' => 'invalid_rut']);
            exit;
        }
        if (!validar_email($correo)) {
            echo json_encode(['ok' => false, 'error' => 'invalid_email']);
            exit;
        }
        if (!validar_telefono($telefono)) {
            echo json_encode(['ok' => false, 'error' => 'invalid_phone']);
            exit;
        }

        $sqlUpdate = 'UPDATE usuarios SET rut = ?, nombre = ?, fecha_nacimiento = ?, correo = ?, sexo = ?, telefono = ?, estado = ? WHERE id = ?';

        $stmt = mysqli_prepare($conn, $sqlUpdate);
        if (!$stmt) {
            echo json_encode(['ok' => false, 'error' => 'db_prepare', 'details' => mysqli_error($conn)]);
            exit;
        }
        $rutNormalizado = normalize_rut($rut);
        mysqli_stmt_bind_param($stmt, 'ssssssii', $rutNormalizado, $nombre, $fecha, $correo, $sexo, $telefono, $estado, $id);
        $ok = mysqli_stmt_execute($stmt);
        $stmtError = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);

        echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => 'update_failed', 'details' => $stmtError ?: mysqli_error($conn)]);
        exit;
    }

    if ($accion === 'eliminar') {
        if ($id <= 0) {
            echo json_encode(['ok' => false, 'error' => 'missing_id']);
            exit;
        }

        $actual = obtener_usuario_por_id($conn, $id);
        if (!$actual) {
            echo json_encode(['ok' => false, 'error' => 'not_found']);
            exit;
        }

        $stmt = mysqli_prepare($conn, 'DELETE FROM usuarios WHERE id = ?');
        if (!$stmt) {
            echo json_encode(['ok' => false, 'error' => 'db_prepare']);
            exit;
        }
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if ($ok) {
            borrar_archivo_certificado($actual['certificado_path'] ?? '');
        }
        echo json_encode(['ok' => (bool)$ok]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'invalid_action']);
    exit;
}

if (wants_json()) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(listar_usuarios($conn), JSON_UNESCAPED_UNICODE);
    exit;
}

$selectedId = intval($_GET['id'] ?? 0);
$selected = [
    'id' => '',
    'rut' => '',
    'nombre' => '',
    'fecha_nacimiento' => '',
    'correo' => '',
    'sexo' => '',
    'telefono' => '',
    'certificado_path' => '',
    'estado' => 1,
];

if ($selectedId > 0) {
    $found = obtener_usuario_por_id($conn, $selectedId);
    if ($found) {
        $selected = array_merge($selected, $found);
    }
}

$usuarios = listar_usuarios($conn);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRUD de Usuarios - Inmobiliaria PNK</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background: #f5f1ea; }
        .panel { border: 1px solid #ead9d0; border-radius: 16px; box-shadow: 0 10px 24px rgba(100, 60, 40, .08); }
        .page-hero { background: linear-gradient(135deg, #722f37 0%, #9c5a63 100%); color: #fff; border-radius: 18px; padding: 1.25rem; }
        .btn-wine { background: #722f37; border-color: #722f37; color: #fff; }
        .btn-wine:hover { background: #5d242c; border-color: #5d242c; color: #fff; }
        .thumb-link { font-size: .9rem; word-break: break-word; }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="page-hero mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h1 class="h3 mb-1">Agregar Gestor</h1>
                <p class="mb-0">Registro, edición y eliminación con validación de RUT, correo, teléfono, contraseña y PDF.</p>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-light btn-sm" href="../Administrar.html">Volver al admin</a>
                
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card panel sticky-top" style="top: 1rem;">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Formulario de usuario Gestor</span>
                        <span class="badge text-bg-secondary" id="estadoFormulario"><?= $selected['id'] ? 'Editando ID ' . h($selected['id']) : 'Nuevo registro' ?></span>
                    </div>
                    <div class="card-body">
                        <form id="usuarioForm" class="row g-3" enctype="multipart/form-data" novalidate onsubmit="manejarSubmitUsuario(event)">
                            <input type="hidden" name="accion" id="accionForm" value="<?= $selected['id'] ? 'modificar' : 'insertar' ?>">
                            <input type="hidden" name="id" id="usuarioId" value="<?= h($selected['id']) ?>">
                            <input type="hidden" name="current_certificado_path" id="currentCertificadoPath" value="<?= h($selected['certificado_path']) ?>">

                            <div class="col-md-6">
                                <label class="form-label">RUT</label>
                                <input type="text" name="rut" id="rut" class="form-control" placeholder="19864005-0" value="<?= h($selected['rut']) ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Nombre Completo</label>
                                <input type="text" name="nombre" id="nombre" class="form-control" value="<?= h($selected['nombre']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Fecha de Nacimiento</label>
                                <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" class="form-control" value="<?= h($selected['fecha_nacimiento']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Correo Electrónico</label>
                                <input type="email" name="correo" id="correo" class="form-control" value="<?= h($selected['correo']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contraseña <?= $selected['id'] ? '(no se modifica al editar)' : '' ?></label>
                                <input type="password" name="password" id="password" class="form-control" <?= $selected['id'] ? 'disabled' : 'required' ?> >
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Sexo</label>
                                <select name="sexo" id="sexo" class="form-select" required>
                                    <option value="">Seleccione</option>
                                    <option value="femenino" <?= $selected['sexo'] === 'femenino' ? 'selected' : '' ?>>Femenino</option>
                                    <option value="masculino" <?= $selected['sexo'] === 'masculino' ? 'selected' : '' ?>>Masculino</option>
                                    <option value="otro" <?= $selected['sexo'] === 'otro' ? 'selected' : '' ?>>Otro</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Teléfono Móvil</label>
                                <input type="text" name="telefono" id="telefono" class="form-control" value="<?= h($selected['telefono']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Estado</label>
                                <select name="estado" id="estado" class="form-select" required>
                                    <option value="1" <?= (int)$selected['estado'] === 1 ? 'selected' : '' ?>>Activo</option>
                                    <option value="0" <?= (int)$selected['estado'] === 0 ? 'selected' : '' ?>>Inactivo</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Certificado de Antecedentes (PDF)</label>
                                <input type="file" name="certificado" id="certificado" class="form-control" accept="application/pdf" <?= $selected['id'] ? 'disabled' : 'required' ?>>
                                <div class="form-text"><?= $selected['id'] ? 'En edición se mantiene el PDF actual sin permitir reemplazo.' : 'Sólo PDF. Debe adjuntarse al registrar.' ?></div>
                                <?php if (!empty($selected['certificado_path'])): ?>
                                    <div class="mt-2 small">Archivo actual: <a href="../<?= h($selected['certificado_path']) ?>" target="_blank" rel="noopener"><?= h(basename($selected['certificado_path'])) ?></a></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-12 d-flex flex-wrap gap-2 mt-2">
                                <button id="btnLimpiar" type="button" class="btn btn-outline-secondary" onclick="limpiarFormulario()">Limpiar</button>
                                <button id="btnRegistrar" type="submit" form="usuarioForm" class="btn btn-wine">Registrar</button>
                                <button id="btnModificar" type="submit" form="usuarioForm" class="btn btn-warning">Modificar</button>
                                <button id="btnEliminar" type="button" class="btn btn-danger" onclick="eliminarUsuario(document.getElementById('usuarioId').value)">Eliminar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card panel">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span>Usuarios registrados</span>
                        <span class="badge text-bg-light"><?= count($usuarios) ?> registros</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>RUT</th>
                                        <th>Nombre</th>
                                        <th>Correo</th>
                                        <th>Sexo</th>
                                        <th>Teléfono</th>
                                        <th>Estado</th>
                                        <th>Certificado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($usuarios as $usuario): ?>
                                        <tr>
                                            <td><?= h($usuario['id']) ?></td>
                                            <td><?= h($usuario['rut']) ?></td>
                                            <td><?= h($usuario['nombre']) ?></td>
                                            <td><?= h($usuario['correo']) ?></td>
                                            <td><?= h($usuario['sexo']) ?></td>
                                            <td><?= h($usuario['telefono']) ?></td>
                                            <td><?= ((int)$usuario['estado'] === 1) ? 'Activo' : 'Inactivo' ?></td>
                                            <td class="thumb-link"><?= !empty($usuario['certificado_path']) ? '<a href="../' . h($usuario['certificado_path']) . '" target="_blank" rel="noopener">Ver PDF</a>' : '-' ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-secondary" type="button" onclick='cargarEditar(<?= json_encode($usuario, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>)'>Editar</button>
                                                <button class="btn btn-sm btn-danger" type="button" onclick="eliminarUsuario(<?= (int)$usuario['id'] ?>)">Eliminar</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($usuarios)): ?>
                                        <tr><td colspan="10" class="text-center text-muted py-4">No hay usuarios registrados.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../js/swal_fallback.js"></script>
    <script src="../js/bootstrap.bundle.min.js"></script>
    <script src="../js/validaciones.js"></script>
    <script>
        const form = document.getElementById('usuarioForm');

        function setFormMode(mode) {
            const btnLimpiar = document.getElementById('btnLimpiar');
            const btnRegistrar = document.getElementById('btnRegistrar');
            const btnModificar = document.getElementById('btnModificar');
            const btnEliminar = document.getElementById('btnEliminar');

            if (mode === 'insertar') {
                btnRegistrar.style.display = '';
                btnModificar.style.display = 'none';
                btnEliminar.style.display = 'none';
                btnLimpiar.style.display = '';
            } else {
                btnRegistrar.style.display = 'none';
                btnModificar.style.display = '';
                btnEliminar.style.display = '';
                btnLimpiar.style.display = '';
            }
        }

        function limpiarFormulario() {
            form.reset();
            document.getElementById('accionForm').value = 'insertar';
            document.getElementById('usuarioId').value = '';
            document.getElementById('currentCertificadoPath').value = '';
            document.getElementById('password').disabled = false;
            document.getElementById('certificado').disabled = false;
            document.getElementById('estadoFormulario').textContent = 'Nuevo registro';
            setFormMode('insertar');
        }

        function cargarEditar(usuario) {
            document.getElementById('usuarioId').value = usuario.id || '';
            document.getElementById('rut').value = usuario.rut || '';
            document.getElementById('nombre').value = usuario.nombre || '';
            document.getElementById('fecha_nacimiento').value = usuario.fecha_nacimiento || '';
            document.getElementById('correo').value = usuario.correo || '';
            document.getElementById('sexo').value = usuario.sexo || '';
            document.getElementById('telefono').value = usuario.telefono || '';
            document.getElementById('estado').value = String(usuario.estado ?? 1);
            document.getElementById('currentCertificadoPath').value = usuario.certificado_path || '';
            document.getElementById('password').value = '';
            document.getElementById('password').disabled = true;
            document.getElementById('certificado').value = '';
            document.getElementById('certificado').disabled = true;
            document.getElementById('accionForm').value = 'modificar';
            document.getElementById('estadoFormulario').textContent = 'Editando ID ' + (usuario.id || '');
            setFormMode('modificar');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function validarFormularioUsuario(accion) {
            const rut = document.getElementById('rut').value.trim();
            const nombre = document.getElementById('nombre').value.trim();
            const fecha = document.getElementById('fecha_nacimiento').value;
            const correo = document.getElementById('correo').value.trim();
            const password = document.getElementById('password').value.trim();
            const sexo = document.getElementById('sexo').value;
            const telefono = document.getElementById('telefono').value.trim();
            const estado = document.getElementById('estado').value;
            const certificado = document.getElementById('certificado');

            if (!rut || !nombre || !fecha || !correo || !sexo || !telefono || !estado) {
                Swal.fire('Atención', 'Todos los campos deben estar completos.', 'warning');
                return false;
            }
            if (!validarRut(rut)) {
                Swal.fire('Error', 'El RUT no es válido.', 'error');
                return false;
            }
            if (!validarEmail(correo)) {
                Swal.fire('Error', 'El correo electrónico no es válido.', 'error');
                return false;
            }
            if (accion === 'insertar' && !validarPassword(password)) {
                Swal.fire('Error', 'La contraseña debe tener mínimo 8 caracteres, una mayúscula, una minúscula y un carácter especial.', 'error');
                return false;
            }
            if (accion === 'modificar' && password && !validarPassword(password)) {
                Swal.fire('Error', 'La nueva contraseña no cumple con los requisitos.', 'error');
                return false;
            }
            if (!validarTelefonoMovil(telefono)) {
                Swal.fire('Error', 'El teléfono móvil debe ser numérico y tener entre 8 y 15 dígitos.', 'error');
                return false;
            }
            if (accion === 'insertar' && !validarArchivoPdf(certificado)) {
                Swal.fire('Error', 'Debe adjuntar un certificado PDF válido.', 'error');
                return false;
            }
            if (accion === 'modificar' && certificado.files.length > 0 && !validarArchivoPdf(certificado)) {
                Swal.fire('Error', 'El nuevo certificado debe ser un PDF válido.', 'error');
                return false;
            }
            return true;
        }

        function manejarSubmitUsuario(event) {
            if (event) {
                event.preventDefault();
            }

            const accion = document.getElementById('accionForm').value || 'insertar';
            enviarAccion(accion);
        }

        function enviarAccion(accion) {
            if (!validarFormularioUsuario(accion)) {
                return;
            }

            const rutNormalizado = normalizarRut(document.getElementById('rut').value.trim());
            const fd = new FormData();
            fd.append('accion', accion);
            fd.append('id', document.getElementById('usuarioId').value || '');
            fd.append('rut', rutNormalizado);
            fd.append('nombre', document.getElementById('nombre').value.trim());
            fd.append('fecha_nacimiento', document.getElementById('fecha_nacimiento').value);
            fd.append('correo', document.getElementById('correo').value.trim());
            fd.append('sexo', document.getElementById('sexo').value);
            fd.append('telefono', document.getElementById('telefono').value.trim());
            fd.append('estado', document.getElementById('estado').value);
            const password = document.getElementById('password');
            const certificado = document.getElementById('certificado');
            if (password && !password.disabled && password.value.trim()) {
                fd.append('password', password.value.trim());
            }
            if (certificado && !certificado.disabled && certificado.files && certificado.files[0]) {
                fd.append('certificado', certificado.files[0]);
            }

            fetch('listar_usuarios.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then((response) => response.json())
                .then((data) => {
                    if (data.ok) {
                        Swal.fire('Éxito', 'Operación realizada correctamente.', 'success').then(() => {
                            window.location.href = 'listar_usuarios.php';
                        });
                        return;
                    }
                    const detalle = data.details ? `\n\nDetalle: ${data.details}` : '';
                    Swal.fire('Error', (data.error || 'No se pudo completar la operación.') + detalle, 'error');
                })
                .catch((error) => {
                    console.error('crud_usuarios_error', error);
                    Swal.fire('Error', 'Error de red al conectar con el CRUD.', 'error');
                });
        }

        function eliminarUsuario(id) {
            Swal.fire({
                title: '¿Eliminar usuario?',
                text: 'Esta acción no se puede deshacer.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar'
            }).then((result) => {
                if (!result.isConfirmed) return;
                const fd = new FormData();
                fd.append('accion', 'eliminar');
                fd.append('id', id);

                fetch('listar_usuarios.php', { method: 'POST', body: fd })
                    .then((response) => response.json())
                    .then((data) => {
                        if (data.ok) {
                            Swal.fire('Eliminado', 'El usuario fue eliminado.', 'success').then(() => {
                                window.location.href = 'listar_usuarios.php';
                            });
                            return;
                        }
                        const detalle = data.details ? `\n\nDetalle: ${data.details}` : '';
                        Swal.fire('Error', (data.error || 'No se pudo eliminar.') + detalle, 'error');
                    })
                    .catch(() => Swal.fire('Error', 'Error de red al conectar con el CRUD.', 'error'));
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            const selectedId = <?= $selectedId ?>;
            if (selectedId > 0) {
                setFormMode('modificar');
            } else {
                setFormMode('insertar');
            }
        });
    </script>
</body>
</html>
