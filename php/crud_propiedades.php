<?php
// 1. Incluir base de datos de manera global al inicio de todo el ciclo de vida del script
include __DIR__ . '/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Control de acceso seguro y protección de sesión activa
if (!isset($_SESSION['id'])) { 
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403); 
        echo json_encode(['ok' => false, 'error' => 'access_denied']); 
    } else {
        header("Location: login.html");
    }
    exit; 
}

// Configuración dinámica de cabeceras según el tipo de petición
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    header('Content-Type: application/json; charset=utf-8');
} else {
    header('Content-Type: text/html; charset=utf-8');
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

// FLUJO DE CARGA VISUAL DE LA PÁGINA (GET)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['accion'])) {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }

    function h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    $selectedId = intval($_GET['id'] ?? 0);
    $provincia = trim($_GET['provincia'] ?? '');
    $comuna = trim($_GET['comuna'] ?? '');
    $sector = trim($_GET['sector'] ?? '');

    $where = [];
    $params = [];
    $types = '';
    if ($provincia !== '') { $where[] = 'provincia LIKE ?'; $params[] = "%$provincia%"; $types .= 's'; }
    if ($comuna !== '') { $where[] = 'comuna LIKE ?'; $params[] = "%$comuna%"; $types .= 's'; }
    if ($sector !== '') { $where[] = 'sector LIKE ?'; $params[] = "%$sector%"; $types .= 's'; }

    $sqlList = 'SELECT id, tipo_propiedad, descripcion, banos, dormitorios, area_total, area_construida, precio_clp, precio_uf, fecha_publicacion, solicitar_visita, bodega, estacionamiento, logia, cocina_amoblada, antejardin, patio_trasero, piscina, foto_url, provincia, comuna, sector FROM propiedades';
    if (!empty($where)) { $sqlList .= ' WHERE ' . implode(' AND ', $where); }
    $sqlList .= ' ORDER BY id DESC';

    $propiedades = [];
    $stmtList = mysqli_prepare($conn, $sqlList);
    if ($stmtList) {
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmtList, $types, ...$params);
        }
        mysqli_stmt_execute($stmtList);
        $resList = mysqli_stmt_get_result($stmtList);
        while ($row = mysqli_fetch_assoc($resList)) {
            $propiedades[] = $row;
        }
        mysqli_stmt_close($stmtList);
    }

    $selected = [
        'id' => '', 'tipo_propiedad' => '', 'descripcion' => '', 'banos' => '', 'dormitorios' => '',
        'area_total' => '', 'area_construida' => '', 'precio_clp' => '', 'precio_uf' => '',
        'fecha_publicacion' => '', 'solicitar_visita' => 'No', 'bodega' => 'No', 'estacionamiento' => '',
        'logia' => '', 'cocina_amoblada' => '', 'antejardin' => '', 'patio_trasero' => '', 'piscina' => '',
        'foto_url' => 'casa1.webp', 'provincia' => '', 'comuna' => '', 'sector' => ''
    ];
    if ($selectedId > 0) {
        $stmtSelected = mysqli_prepare($conn, 'SELECT id, tipo_propiedad, descripcion, banos, dormitorios, area_total, area_construida, precio_clp, precio_uf, fecha_publicacion, solicitar_visita, bodega, estacionamiento, logia, cocina_amoblada, antejardin, patio_trasero, piscina, foto_url, provincia, comuna, sector FROM propiedades WHERE id = ? LIMIT 1');
        if ($stmtSelected) {
            mysqli_stmt_bind_param($stmtSelected, 'i', $selectedId);
            mysqli_stmt_execute($stmtSelected);
            $resSelected = mysqli_stmt_get_result($stmtSelected);
            if ($resSelected && ($row = mysqli_fetch_assoc($resSelected))) {
                $selected = array_merge($selected, $row);
            }
            mysqli_stmt_close($stmtSelected);
        }
    }
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mantenedor de Propiedades - Inmobiliaria PNK</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background: #f5f1ea; min-height: 100vh; font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; }
        .page-hero { background: linear-gradient(135deg, #722f37 0%, #9c5a63 100%); color: #fff; border-radius: 18px; padding: 1.25rem; box-shadow: 0 10px 25px rgba(114,47,55,.18); }
        .panel-card { border: 1px solid #e6d7cf; border-radius: 16px; box-shadow: 0 8px 22px rgba(62, 34, 21, .08); }
        .panel-card .card-header { background: #fff5f1; border-bottom: 1px solid #ead7d0; color: #722f37; font-weight: 700; }
        .btn-wine { background: #722f37; border-color: #722f37; color: #fff; }
        .btn-wine:hover { background: #5d242c; border-color: #5d242c; color: #fff; }
        .table thead th { background: #fbf4f0; color: #6a2b34; }
        .prop-thumb { width: 68px; height: 46px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd; background: #fff; }
        .sticky-actions { position: sticky; top: 1rem; }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="page-hero mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h1 class="h3 mb-1">Mantenedor de Propiedades</h1>
                    <p class="mb-0">Crear, editar, borrar y subir fotos de propiedades desde un solo panel.</p>
                </div>
                <div class="d-flex gap-2">
                    <a class="btn btn-light btn-sm" href="../pagina_admin.html">Volver al admin</a>
                    <button class="btn btn-outline-light btn-sm" type="button" onclick="limpiarFormulario()">Nueva propiedad</button>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card panel-card sticky-actions">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Formulario de propiedad</span>
                        <span class="badge text-bg-secondary" id="estadoFormulario"><?= $selected['id'] ? 'Editando ID ' . h($selected['id']) : 'Nuevo registro' ?></span>
                    </div>
                    <div class="card-body">
                        <form id="crudPropiedadForm" class="row g-2" enctype="multipart/form-data">
                            <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="accion" id="accionForm" value="<?= $selected['id'] ? 'modificar' : 'insertar' ?>">
                            <input type="hidden" name="id" id="propiedadId" value="<?= h($selected['id']) ?>">
                            <input type="hidden" name="current_foto_url" id="currentFotoUrl" value="<?= h($selected['foto_url'] ?: 'casa1.webp') ?>">

                            <div class="col-12">
                                <label class="form-label">Tipo de propiedad</label>
                                <select name="tipo_propiedad" class="form-select" required>
                                    <option value="">Seleccione</option>
                                    <option value="Casa" <?= $selected['tipo_propiedad'] === 'Casa' ? 'selected' : '' ?>>Casa</option>
                                    <option value="Departamento" <?= $selected['tipo_propiedad'] === 'Departamento' ? 'selected' : '' ?>>Departamento</option>
                                    <option value="Terreno" <?= $selected['tipo_propiedad'] === 'Terreno' ? 'selected' : '' ?>>Terreno</option>
                                </select>
                            </div>
                            <div class="col-md-6"><label class="form-label">Provincia</label><input name="provincia" class="form-control" value="<?= h($selected['provincia']) ?>" required></div>
                            <div class="col-md-6"><label class="form-label">Comuna</label><input name="comuna" class="form-control" value="<?= h($selected['comuna']) ?>" required></div>
                            <div class="col-md-12"><label class="form-label">Sector</label><input name="sector" class="form-control" value="<?= h($selected['sector']) ?>" required></div>
                            <div class="col-12"><label class="form-label">Descripción</label><textarea name="descripcion" class="form-control" rows="3" required><?= h($selected['descripcion']) ?></textarea></div>
                            <div class="col-md-3"><label class="form-label">Baños</label><input name="banos" type="number" min="0" class="form-control" value="<?= h($selected['banos']) ?>" required></div>
                            <div class="col-md-3"><label class="form-label">Dormitorios</label><input name="dormitorios" type="number" min="0" class="form-control" value="<?= h($selected['dormitorios']) ?>" required></div>
                            <div class="col-md-6"><label class="form-label">Área total</label><input name="area_total" type="number" step="0.01" min="0" class="form-control" value="<?= h($selected['area_total']) ?>" required></div>
                            <div class="col-md-6"><label class="form-label">Área construida</label><input name="area_construida" type="number" step="0.01" min="0" class="form-control" value="<?= h($selected['area_construida']) ?>" required></div>
                            <div class="col-md-6"><label class="form-label">Precio CLP</label><input name="precio_clp" id="precioClp" type="number" min="0" class="form-control" value="<?= h($selected['precio_clp']) ?>" required></div>
                            <div class="col-md-6"><label class="form-label">Precio UF</label><input name="precio_uf" id="precioUf" type="number" step="0.01" min="0" class="form-control" value="<?= h($selected['precio_uf']) ?>" readonly></div>
                            <div class="col-md-6"><label class="form-label">Fecha publicación</label><input name="fecha_publicacion" type="date" class="form-control" value="<?= h($selected['fecha_publicacion']) ?>" required></div>
                            <div class="col-md-6"><label class="form-label">Solicitar visita</label><select name="solicitar_visita" class="form-select" required><option value="No" <?= $selected['solicitar_visita'] === 'No' ? 'selected' : '' ?>>No</option><option value="Sí" <?= $selected['solicitar_visita'] === 'Sí' ? 'selected' : '' ?>>Sí</option></select></div>
                            <div class="col-md-6"><label class="form-label">Bodega</label><select name="bodega" class="form-select" required><option value="No" <?= $selected['bodega'] === 'No' ? 'selected' : '' ?>>No</option><option value="Sí" <?= $selected['bodega'] === 'Sí' ? 'selected' : '' ?>>Sí</option></select></div>
                            <div class="col-md-6"><label class="form-label">Estacionamiento</label><select name="estacionamiento" class="form-select" required><option value="">Seleccione</option><option value="Sí" <?= $selected['estacionamiento'] === 'Sí' ? 'selected' : '' ?>>Sí</option><option value="No" <?= $selected['estacionamiento'] === 'No' ? 'selected' : '' ?>>No</option></select></div>
                            <div class="col-md-6"><label class="form-label">Logia</label><select name="logia" class="form-select" required><option value="">Seleccione</option><option value="Sí" <?= $selected['logia'] === 'Sí' ? 'selected' : '' ?>>Sí</option><option value="No" <?= $selected['logia'] === 'No' ? 'selected' : '' ?>>No</option></select></div>
                            <div class="col-md-6"><label class="form-label">Cocina amoblada</label><select name="cocina_amoblada" class="form-select" required><option value="">Seleccione</option><option value="Sí" <?= $selected['cocina_amoblada'] === 'Sí' ? 'selected' : '' ?>>Sí</option><option value="No" <?= $selected['cocina_amoblada'] === 'No' ? 'selected' : '' ?>>No</option></select></div>
                            <div class="col-md-6"><label class="form-label">Antejardín</label><select name="antejardin" class="form-select" required><option value="">Seleccione</option><option value="Sí" <?= $selected['antejardin'] === 'Sí' ? 'selected' : '' ?>>Sí</option><option value="No" <?= $selected['antejardin'] === 'No' ? 'selected' : '' ?>>No</option></select></div>
                            <div class="col-md-6"><label class="form-label">Patio trasero</label><select name="patio_trasero" class="form-select" required><option value="">Seleccione</option><option value="Sí" <?= $selected['patio_trasero'] === 'Sí' ? 'selected' : '' ?>>Sí</option><option value="No" <?= $selected['patio_trasero'] === 'No' ? 'selected' : '' ?>>No</option></select></div>
                            <div class="col-md-6"><label class="form-label">Piscina</label><select name="piscina" class="form-select" required><option value="">Seleccione</option><option value="Sí" <?= $selected['piscina'] === 'Sí' ? 'selected' : '' ?>>Sí</option><option value="No" <?= $selected['piscina'] === 'No' ? 'selected' : '' ?>>No</option></select></div>
                            <div class="col-12"><label class="form-label">Fotografías</label><input type="file" class="form-control" name="fotos[]" id="fotos" accept="image/*" multiple><small class="text-muted">Puede subir hasta 10 imágenes.</small></div>
                                            <div class="col-12 d-flex flex-wrap gap-2 mt-2">
                                                <button id="btnLimpiar" type="button" class="btn btn-outline-secondary" onclick="limpiarFormulario()">Limpiar</button>
                                                <button id="btnRegistrar" type="button" class="btn btn-wine" onclick="enviarAccion('insertar')">Registrar</button>
                                                <button id="btnModificar" type="button" class="btn btn-warning" onclick="enviarAccion('modificar')">Modificar</button>
                                                <button id="btnEliminar" type="button" class="btn btn-danger" onclick="eliminarPorId(document.getElementById('propiedadId').value)">Eliminar</button>
                                                <button id="btnGuardarFoto" type="button" class="btn btn-outline-primary" onclick="enviarAccion('subirFoto')">Guardar Foto</button>
                                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card panel-card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span>Buscar propiedades</span>
                        <span class="badge text-bg-light"><?= count($propiedades) ?> registros</span>
                    </div>
                    <div class="card-body">
                        <form method="get" class="row g-2 align-items-end mb-3">
                            <div class="col-md-3"><input class="form-control form-control-sm" name="provincia" placeholder="Provincia" value="<?= h($provincia) ?>"></div>
                            <div class="col-md-3"><input class="form-control form-control-sm" name="comuna" placeholder="Comuna" value="<?= h($comuna) ?>"></div>
                            <div class="col-md-3"><input class="form-control form-control-sm" name="sector" placeholder="Sector" value="<?= h($sector) ?>"></div>
                            <div class="col-md-3 d-flex gap-2"><button class="btn btn-wine btn-sm" type="submit">Buscar</button><a class="btn btn-outline-secondary btn-sm" href="crud_propiedades.php">Limpiar</a></div>
                        </form>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>ID</th><th>Tipo</th><th>Descripción</th><th>Baños</th><th>Dorm.</th><th>Área</th><th>Foto</th><th>Precio</th><th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($propiedades as $propiedad): ?>
                                        <tr>
                                            <td><?= h($propiedad['id']) ?></td>
                                            <td><?= h($propiedad['tipo_propiedad']) ?></td>
                                            <td><?= h($propiedad['descripcion']) ?></td>
                                            <td><?= h($propiedad['banos']) ?></td>
                                            <td><?= h($propiedad['dormitorios']) ?></td>
                                            <td><?= h($propiedad['area_total']) ?></td>
                                            <td><img class="prop-thumb" src="../uploads/properties/<?= h($propiedad['foto_url'] ?: 'casa1.webp') ?>" alt="Foto"></td>
                                            <td>$<?= number_format((float)$propiedad['precio_clp'], 0, ',', '.') ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-secondary" type="button" onclick='cargarEditar(<?= json_encode($propiedad, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>)'>Editar</button>
                                                <button class="btn btn-sm btn-danger" type="button" onclick="eliminarPorId(<?= (int)$propiedad['id'] ?>)">Eliminar</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($propiedades)): ?>
                                        <tr><td colspan="9" class="text-center text-muted py-4">No hay propiedades registradas.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script>
        const UF_RATE = 35000;
        const form = document.getElementById('crudPropiedadForm');

        function calcularUFFromCLP(clp) {
            const valor = Number(clp || 0);
            return valor > 0 ? (valor / UF_RATE).toFixed(2) : '';
        }

        document.getElementById('precioClp').addEventListener('input', (event) => {
            document.getElementById('precioUf').value = calcularUFFromCLP(event.target.value);
        });

        function setFormMode(mode) {
            const btnLimpiar = document.getElementById('btnLimpiar');
            const btnRegistrar = document.getElementById('btnRegistrar');
            const btnModificar = document.getElementById('btnModificar');
            const btnEliminar = document.getElementById('btnEliminar');
            const btnGuardarFoto = document.getElementById('btnGuardarFoto');

            if (mode === 'insertar') {
                btnLimpiar.style.display = '';
                btnRegistrar.style.display = '';
                btnModificar.style.display = 'none';
                btnEliminar.style.display = 'none';
                btnGuardarFoto.style.display = 'none';
            } else if (mode === 'modificar') {
                btnLimpiar.style.display = '';
                btnRegistrar.style.display = 'none';
                btnModificar.style.display = '';
                btnEliminar.style.display = '';
                btnGuardarFoto.style.display = '';
            }
        }

        function limpiarFormulario() {
            // Limpiar todos los inputs, selects y textareas del formulario
            const elements = form.querySelectorAll('input, select, textarea');
            elements.forEach(el => {
                const type = el.type ? el.type.toLowerCase() : el.tagName.toLowerCase();
                if (el.name === 'csrf') return; // conservar token CSRF
                if (el.name === 'accion') return; // lo ajustamos explícitamente abajo
                if (el.name === 'id') return; // lo ajustamos explícitamente
                if (type === 'file') {
                    try { el.value = ''; } catch (e) { /* some browsers block direct clear, ignore */ }
                    // Si el input acepta múltiples archivos, también limpiar lista
                    if (el.files && el.files.length) {
                        // crear DataTransfer vacío (si es compatible)
                        try { const dt = new DataTransfer(); el.files = dt.files; } catch (e) { /* silent */ }
                    }
                    return;
                }

                if (el.tagName.toLowerCase() === 'select') {
                    // si existe opción 'No', seleccionarla; si existe opción vacía, seleccionarla; si no, seleccionar primer índice
                    if ([...el.options].some(o => o.value === 'No')) el.value = 'No';
                    else if ([...el.options].some(o => o.value === '')) el.value = '';
                    else el.selectedIndex = 0;
                    return;
                }

                if (type === 'number') {
                    el.value = 0;
                    return;
                }

                // escondidos y demás: limpiar texto por defecto
                el.value = '';
            });

            // Valores hidden y estado visual
            document.getElementById('accionForm').value = 'insertar';
            document.getElementById('propiedadId').value = '';
            document.getElementById('currentFotoUrl').value = 'casa1.webp';
            document.getElementById('estadoFormulario').textContent = 'Nuevo registro';
            document.getElementById('precioUf').value = '';
            setFormMode('insertar');
        }

        function cargarEditar(propiedad) {
            form.querySelector('[name="tipo_propiedad"]').value = propiedad.tipo_propiedad || '';
            form.querySelector('[name="provincia"]').value = propiedad.provincia || '';
            form.querySelector('[name="comuna"]').value = propiedad.comuna || '';
            form.querySelector('[name="sector"]').value = propiedad.sector || '';
            form.querySelector('[name="descripcion"]').value = propiedad.descripcion || '';
            form.querySelector('[name="banos"]').value = propiedad.banos || '';
            form.querySelector('[name="dormitorios"]').value = propiedad.dormitorios || '';
            form.querySelector('[name="area_total"]').value = propiedad.area_total || '';
            form.querySelector('[name="area_construida"]').value = propiedad.area_construida || '';
            form.querySelector('[name="precio_clp"]').value = propiedad.precio_clp || '';
            document.getElementById('precioUf').value = propiedad.precio_uf || '';
            form.querySelector('[name="fecha_publicacion"]').value = (propiedad.fecha_publicacion || '').toString().split(' ')[0];
            form.querySelector('[name="solicitar_visita"]').value = propiedad.solicitar_visita || 'No';
            form.querySelector('[name="bodega"]').value = propiedad.bodega || 'No';
            form.querySelector('[name="estacionamiento"]').value = propiedad.estacionamiento === 'Sí' ? 'Sí' : (propiedad.estacionamiento === 'No' ? 'No' : (propiedad.estacionamiento || ''));
            form.querySelector('[name="logia"]').value = propiedad.logia === 'Sí' ? 'Sí' : (propiedad.logia === 'No' ? 'No' : (propiedad.logia || ''));
            form.querySelector('[name="cocina_amoblada"]').value = propiedad.cocina_amoblada === 'Sí' ? 'Sí' : (propiedad.cocina_amoblada === 'No' ? 'No' : (propiedad.cocina_amoblada || ''));
            form.querySelector('[name="antejardin"]').value = propiedad.antejardin === 'Sí' ? 'Sí' : (propiedad.antejardin === 'No' ? 'No' : (propiedad.antejardin || ''));
            form.querySelector('[name="patio_trasero"]').value = propiedad.patio_trasero === 'Sí' ? 'Sí' : (propiedad.patio_trasero === 'No' ? 'No' : (propiedad.patio_trasero || ''));
            form.querySelector('[name="piscina"]').value = propiedad.piscina === 'Sí' ? 'Sí' : (propiedad.piscina === 'No' ? 'No' : (propiedad.piscina || ''));
            document.getElementById('propiedadId').value = propiedad.id || '';
            document.getElementById('currentFotoUrl').value = propiedad.foto_url || 'casa1.webp';
            document.getElementById('accionForm').value = 'modificar';
            document.getElementById('estadoFormulario').textContent = 'Editando ID ' + (propiedad.id || '');
            setFormMode('modificar');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function enviarAccion(accion) {
            if (accion !== 'subirFoto') {
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }
            }
            const fd = new FormData(form);
            fd.set('accion', accion);
            if (accion === 'subirFoto' && !document.getElementById('propiedadId').value) {
                Swal.fire('Atención', 'Primero seleccione una propiedad para subir la foto.', 'warning');
                return;
            }
            if (accion === 'subirFoto' && (!document.getElementById('fotos').files || document.getElementById('fotos').files.length === 0)) {
                Swal.fire('Atención', 'Seleccione al menos una foto.', 'warning');
                return;
            }

            fetch('crud_propiedades.php', { method: 'POST', body: fd })
                .then((response) => response.json())
                .then((data) => {
                    if (data.ok) {
                        Swal.fire('Éxito', 'Operación realizada correctamente.', 'success').then(() => {
                            const targetId = document.getElementById('propiedadId').value || data.id || '';
                            if (accion === 'insertar' && targetId) {
                                window.location.href = 'crud_propiedades.php?id=' + targetId;
                            } else {
                                window.location.reload();
                            }
                        });
                    } else {
                        Swal.fire('Error', data.error || 'No se pudo completar la operación.', 'error');
                    }
                })
                .catch(() => Swal.fire('Error', 'Error de red al conectar con el CRUD.', 'error'));
        }

        function eliminarPorId(id) {
            Swal.fire({
                title: '¿Eliminar propiedad?',
                text: 'Esta acción no se puede deshacer.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar'
            }).then((result) => {
                if (!result.isConfirmed) return;
                document.getElementById('propiedadId').value = id;
                document.getElementById('accionForm').value = 'eliminar';
                const fd = new FormData(form);
                fd.set('accion', 'eliminar');
                fd.set('id', id);
                fetch('crud_propiedades.php', { method: 'POST', body: fd })
                    .then((response) => response.json())
                    .then((data) => {
                        if (data.ok) {
                            Swal.fire('Eliminado', 'La propiedad fue eliminada.', 'success').then(() => window.location.reload());
                        } else {
                            Swal.fire('Error', data.error || 'No se pudo eliminar.', 'error');
                        }
                    })
                    .catch(() => Swal.fire('Error', 'Error de red al conectar con el CRUD.', 'error'));
            });
        }
    </script>
</body>
</html>
<script>
document.addEventListener('DOMContentLoaded', function(){
    // Modo inicial según si se pasó id por GET
    const selectedId = <?= $selectedId ?>;
    if (selectedId && selectedId > 0) {
        setFormMode('modificar');
    } else {
        setFormMode('insertar');
    }
});
</script>
<?php
    exit;
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

// 2B. OPERACIÓN: SUBIR FOTO INDIVIDUAL
if ($accion === 'subirFoto') {
    $csrf = $_POST['csrf'] ?? '';
    if (!verify_csrf($csrf)) { echo json_encode(['ok' => false, 'error' => 'Token CSRF inválido o expirado.']); exit; }

    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['ok' => false, 'error' => 'ID de propiedad inválido.']);
        exit;
    }

    $uploadedPhotos = upload_property_photos(normalize_upload_files($_FILES['fotos'] ?? []));
    if (empty($uploadedPhotos)) {
        $foto_url = 'casa1.webp';
    } else {
        $foto_url = $uploadedPhotos[0];
    }

    $stmt = mysqli_prepare($conn, 'UPDATE propiedades SET foto_url = ? WHERE id = ?');
    if (!$stmt) {
        delete_property_files($uploadedPhotos);
        echo json_encode(['ok' => false, 'error' => 'No se pudo preparar la actualización de foto.']);
        exit;
    }
    mysqli_stmt_bind_param($stmt, 'si', $foto_url, $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        $stmtImage = mysqli_prepare($conn, 'INSERT INTO propiedad_imagenes (propiedad_id, filename, is_default) VALUES (?, ?, 1)');
        if ($stmtImage) {
            mysqli_stmt_bind_param($stmtImage, 'is', $id, $foto_url);
            mysqli_stmt_execute($stmtImage);
            mysqli_stmt_close($stmtImage);
        }
        echo json_encode(['ok' => true, 'foto_url' => $foto_url]);
    } else {
        delete_property_files($uploadedPhotos);
        echo json_encode(['ok' => false, 'error' => 'No se pudo actualizar la fotografía.']);
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