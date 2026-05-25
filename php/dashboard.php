<?php
include __DIR__ . '/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
// Validar sesión activa
if (!isset($_SESSION['id'])) { header("Location: login.html"); exit; }

// CSRF token para operaciones sensibles
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$res = mysqli_query($conn, "SELECT * FROM propiedades");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Dashboard - Inmobiliaria PNK</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="container mt-5">
        <h2>Panel de Administración de Propiedades</h2>
        <div class="d-flex gap-2 mb-3">
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalPropiedad">Nueva Propiedad</button>
            <div class="ms-auto d-flex align-items-center gap-2">
                <input id="searchProvincia" class="form-control form-control-sm" placeholder="Provincia" style="max-width:160px">
                <input id="searchComuna" class="form-control form-control-sm" placeholder="Comuna" style="max-width:160px">
                <input id="searchSector" class="form-control form-control-sm" placeholder="Sector" style="max-width:160px">
                <button class="btn btn-outline-primary btn-sm" onclick="buscarPropiedades()">Buscar</button>
            </div>
        </div>
        <div id="searchResults" class="mb-3"></div>
        
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tipo</th>
                    <th>Descripción</th>
                    <th>Baños</th>
                    <th>Dormitorios</th>
                    <th>Área Total</th>
                    <th>Fotografía</th>
                    <th>Precio</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while($p = mysqli_fetch_assoc($res)): ?>
                <tr>
                    <td><?= (int)$p['id'] ?></td>
                    <td><?= htmlspecialchars($p['tipo_propiedad'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($p['descripcion'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= (int)$p['banos'] ?></td>
                    <td><?= (int)$p['dormitorios'] ?></td>
                    <td><?= htmlspecialchars($p['area_total'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <button type="button" class="btn p-0 border-0 bg-transparent" onclick="abrirGaleria(<?= (int)$p['id'] ?>, <?= json_encode($p['tipo_propiedad'] . ' - ' . $p['provincia'] . ' / ' . $p['comuna']) ?>)">
                            <img src="../uploads/properties/<?= htmlspecialchars($p['foto_url'] ?: 'casa1.webp', ENT_QUOTES, 'UTF-8') ?>" alt="Foto propiedad" title="Ver galería" style="width:72px;height:48px;object-fit:cover;border-radius:6px;cursor:pointer;">
                        </button>
                    </td>
                    <td>$<?= number_format((int)$p['precio_clp']) ?></td>
                    <td>
                        <button class="btn btn-warning btn-sm" data-item='<?= htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8') ?>' onclick="cargarDatosEditar(this)">Editar</button>
                        <button type="button" class="btn btn-danger btn-sm" onclick="confirmarEliminar(<?= (int)$p['id'] ?>)">Eliminar</button>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal: Formulario Propiedad -->
    <div class="modal fade" id="modalPropiedad" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Propiedad</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form id="formPropiedad" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="accion" id="formAccion" value="insertar">
                    <input type="hidden" name="id" id="propId" value="">
                    <div class="modal-body row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Tipo de Propiedad</label>
                            <select name="tipo_propiedad" id="propTipo" class="form-select" required>
                                <option value="">Seleccione</option>
                                <option value="Casa">Casa</option>
                                <option value="Departamento">Departamento</option>
                                <option value="Terreno">Terreno</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Provincia</label>
                            <input name="provincia" id="propProvincia" type="text" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Comuna</label>
                            <input name="comuna" id="propComuna" type="text" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Sector</label>
                            <input name="sector" id="propSector" type="text" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Precio CLP</label>
                            <input name="precio_clp" id="propPrecioCLP" type="number" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Precio UF</label>
                            <input name="precio_uf" id="propPrecioUF" type="number" step="0.01" class="form-control" required readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fecha de Publicación</label>
                            <input name="fecha_publicacion" id="propFecha" type="date" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <textarea name="descripcion" id="propDescripcion" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Baños</label>
                            <input name="banos" id="propBanos" type="number" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Dormitorios</label>
                            <input name="dormitorios" id="propDorm" type="number" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Área Total (m²)</label>
                            <input name="area_total" id="propAreaTotal" type="number" step="0.01" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Área Construida (m²)</label>
                            <input name="area_construida" id="propAreaConst" type="number" step="0.01" class="form-control" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Solicitar Visita</label>
                            <select name="solicitar_visita" id="propVisita" class="form-select" required>
                                <option value="No">No</option>
                                <option value="Sí">Sí</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Bodega</label>
                            <select name="bodega" id="propBodega" class="form-select" required>
                                <option value="No">No</option>
                                <option value="Sí">Sí</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Estacionamiento</label>
                            <select name="estacionamiento" id="propEstac" class="form-select" required>
                                <option value="No">No</option>
                                <option value="Sí">Sí</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Logia</label>
                            <select name="logia" id="propLogia" class="form-select" required>
                                <option value="No">No</option>
                                <option value="Sí">Sí</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Cocina Amoblada</label>
                            <select name="cocina_amoblada" id="propCocina" class="form-select" required>
                                <option value="No">No</option>
                                <option value="Sí">Sí</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Antejardín</label>
                            <select name="antejardin" id="propAnteJ" class="form-select" required>
                                <option value="No">No</option>
                                <option value="Sí">Sí</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Patio Trasero</label>
                            <select name="patio_trasero" id="propPatio" class="form-select" required>
                                <option value="No">No</option>
                                <option value="Sí">Sí</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Piscina</label>
                            <select name="piscina" id="propPiscina" class="form-select" required>
                                <option value="No">No</option>
                                <option value="Sí">Sí</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Fotografías (1 a 10)</label>
                            <input type="file" name="fotos[]" id="propFotos" class="form-control" accept="image/*" multiple>
                            <input type="hidden" name="current_foto_url" id="propCurrentFoto" value="casa1.webp">
                            <small class="text-muted d-block mt-1">Si editas una propiedad y no subes nuevas imágenes, se conserva la principal actual.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button id="btnLimpiarModal" type="button" class="btn btn-outline-secondary" onclick="prepararFormularioNuevo()">Limpiar</button>
                        <button id="btnSubmitModal" type="submit" class="btn btn-primary">Registrar</button>
                        <button id="btnEliminarModal" type="button" class="btn btn-danger" style="display:none;" onclick="confirmarEliminarModal()">Eliminar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="galeriaModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="galeriaTitulo">Galería de propiedad</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div id="galeriaCarousel" class="carousel slide" data-bs-ride="false">
                        <div class="carousel-inner" id="galeriaCarouselInner">
                            <div class="text-center py-5 text-muted">Selecciona una propiedad para ver sus imágenes.</div>
                        </div>
                        <button class="carousel-control-prev" type="button" data-bs-target="#galeriaCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Anterior</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#galeriaCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Siguiente</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Funciones de interacción
        const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?>';

        let galeriaModalInstance = null;

        function getGaleriaModal() {
            if (!galeriaModalInstance) {
                galeriaModalInstance = new bootstrap.Modal(document.getElementById('galeriaModal'));
            }
            return galeriaModalInstance;
        }

        function setModalMode(mode) {
            const btnSubmit = document.getElementById('btnSubmitModal');
            const btnEliminar = document.getElementById('btnEliminarModal');
            if (mode === 'insertar') {
                btnSubmit.textContent = 'Registrar';
                btnEliminar.style.display = 'none';
            } else if (mode === 'modificar') {
                btnSubmit.textContent = 'Modificar';
                btnEliminar.style.display = '';
            }
        }

        function prepararFormularioNuevo() {
            document.getElementById('formPropiedad').reset();
            document.getElementById('formAccion').value = 'insertar';
            document.getElementById('propId').value = '';
            document.querySelector('#modalPropiedad .modal-title').innerText = 'Nueva Propiedad';
            setModalMode('insertar');
        }

        function confirmarEliminarModal() {
            const id = parseInt(document.getElementById('propId').value || 0, 10);
            if (!id) return Swal.fire('Error', 'ID inválido para eliminar', 'error');
            confirmarEliminar(id);
        }

        function crearSlideImagen(src, active) {
            const item = document.createElement('div');
            item.className = 'carousel-item' + (active ? ' active' : '');
            const img = document.createElement('img');
            img.className = 'd-block w-100';
            img.style.maxHeight = '75vh';
            img.style.objectFit = 'contain';
            img.alt = 'Fotografía de la propiedad';
            img.src = src;
            item.appendChild(img);
            return item;
        }

        async function abrirGaleria(id, titulo) {
            const tituloEl = document.getElementById('galeriaTitulo');
            const inner = document.getElementById('galeriaCarouselInner');
            tituloEl.textContent = titulo || 'Galería de propiedad';
            inner.innerHTML = '<div class="text-center py-5 text-muted">Cargando imágenes...</div>';
            getGaleriaModal().show();

            try {
                const response = await fetch(`crud_propiedades.php?accion=galeria&id=${encodeURIComponent(id)}`, { credentials: 'same-origin' });
                const data = await response.json();
                if (!response.ok || !data.ok || !Array.isArray(data.imagenes) || data.imagenes.length === 0) {
                    throw new Error(data.error || 'No se pudo cargar la galería.');
                }

                inner.innerHTML = '';
                data.imagenes.forEach((filename, index) => {
                    inner.appendChild(crearSlideImagen(`../uploads/properties/${filename}`, index === 0));
                });
            } catch (error) {
                inner.innerHTML = `<div class="alert alert-danger mb-0">${error.message || 'No se pudo cargar la galería.'}</div>`;
            }
        }

        function cargarDatosEditar(btn) {
            try {
                const p = JSON.parse(btn.getAttribute('data-item'));
                console.log('Editar propiedad:', p);
                // Abrir modal y poblar campos del formulario
                document.getElementById('formAccion').value = 'modificar';
                document.getElementById('propId').value = p.id || '';
                document.getElementById('propTipo').value = p.tipo_propiedad || '';
                document.getElementById('propPrecioCLP').value = p.precio_clp || '';
                document.getElementById('propPrecioUF').value = p.precio_uf || '';
                document.getElementById('propFecha').value = p.fecha_publicacion ? p.fecha_publicacion.split(' ')[0] : '';
                document.getElementById('propDescripcion').value = p.descripcion || '';
                document.getElementById('propBanos').value = p.banos || 0;
                document.getElementById('propDorm').value = p.dormitorios || 0;
                document.getElementById('propAreaTotal').value = p.area_total || '';
                document.getElementById('propAreaConst').value = p.area_construida || '';
                document.getElementById('propVisita').value = p.solicitar_visita || 'No';
                    document.getElementById('propBodega').value = p.bodega || 'No';
                    document.getElementById('propEstac').value = p.estacionamiento || 'No';
                    document.getElementById('propLogia').value = p.logia || 'No';
                    document.getElementById('propCocina').value = p.cocina_amoblada || 'No';
                    document.getElementById('propAnteJ').value = p.antejardin || 'No';
                    document.getElementById('propPatio').value = p.patio_trasero || 'No';
                    document.getElementById('propPiscina').value = p.piscina || 'No';
                document.getElementById('propCurrentFoto').value = p.foto_url || 'casa1.webp';
                const modal = new bootstrap.Modal(document.getElementById('modalPropiedad'));
                modal.show();
            } catch (e) { console.error(e); }
                setModalMode('modificar');
        }

        function confirmarEliminar(id) {
            Swal.fire({
                title: '¿Eliminar propiedad?',
                text: "Esta acción no se puede deshacer",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('crud_propiedades.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({accion: 'eliminar', id: id, csrf: CSRF_TOKEN})
                    }).then(r => r.json()).then(data => {
                        if (data.ok) {
                            Swal.fire('Eliminado', 'Propiedad eliminada', 'success').then(() => location.reload());
                        } else {
                            Swal.fire('Error', data.error || 'No se pudo eliminar', 'error');
                        }
                    }).catch(() => Swal.fire('Error', 'Error de red', 'error'));
                }
            });
        }

        // Búsqueda AJAX
        function buscarPropiedades() {
            const provincia = document.getElementById('searchProvincia').value.trim();
            const comuna = document.getElementById('searchComuna').value.trim();
            const sector = document.getElementById('searchSector').value.trim();
            const params = new URLSearchParams();
            if (provincia) params.append('provincia', provincia);
            if (comuna) params.append('comuna', comuna);
            if (sector) params.append('sector', sector);

            fetch('search_propiedades.php?' + params.toString()).then(r => r.json()).then(data => {
                const container = document.getElementById('searchResults');
                container.innerHTML = '';
                if (!Array.isArray(data) || data.length === 0) {
                    container.innerHTML = '<div class="alert alert-secondary">No se encontraron propiedades.</div>';
                    return;
                }
                const list = document.createElement('div');
                list.className = 'list-group';
                data.forEach(item => {
                    const el = document.createElement('button');
                    el.type = 'button';
                    el.className = 'list-group-item list-group-item-action';
                    el.textContent = `${item.tipo_propiedad} — $${Number(item.precio_clp).toLocaleString('es-CL')} — ${item.fecha_publicacion || ''}`;
                    el.addEventListener('click', () => {
                        // cargar en formulario
                        document.getElementById('formAccion').value = 'modificar';
                        document.getElementById('propId').value = item.id || '';
                        document.getElementById('propTipo').value = item.tipo_propiedad || '';
                        document.getElementById('propPrecioCLP').value = item.precio_clp || '';
                        document.getElementById('propPrecioUF').value = item.precio_uf || '';
                        document.getElementById('propFecha').value = item.fecha_publicacion ? item.fecha_publicacion.split(' ')[0] : '';
                        document.getElementById('propDescripcion').value = item.descripcion || '';
                        document.getElementById('propBanos').value = item.banos || 0;
                        document.getElementById('propDorm').value = item.dormitorios || 0;
                        document.getElementById('propAreaTotal').value = item.area_total || '';
                        document.getElementById('propAreaConst').value = item.area_construida || '';
                        document.getElementById('propVisita').value = item.solicitar_visita || 'No';
                        document.getElementById('propBodega').value = item.bodega || 'No';
                        document.getElementById('propEstac').value = item.estacionamiento || 'No';
                        document.getElementById('propLogia').value = item.logia || 'No';
                        document.getElementById('propCocina').value = item.cocina_amoblada || 'No';
                        document.getElementById('propAnteJ').value = item.antejardin || 'No';
                        document.getElementById('propPatio').value = item.patio_trasero || 'No';
                        document.getElementById('propPiscina').value = item.piscina || 'No';
                        document.getElementById('propCurrentFoto').value = item.foto_url || 'casa1.webp';
                        const modal = new bootstrap.Modal(document.getElementById('modalPropiedad'));
                        modal.show();
                    });
                    list.appendChild(el);
                });
                container.appendChild(list);
            }).catch(err => {
                console.error(err);
                document.getElementById('searchResults').innerHTML = '<div class="alert alert-danger">Error en búsqueda</div>';
            });
        }

        // Enviar formulario de propiedad via POST (multipart) con validación
        document.getElementById('formPropiedad').addEventListener('submit', function(e){
            e.preventDefault();
            const form = e.target;
            if (!validarFormularioPropiedad(form)) return;
            // calcular UF
            const clp = form.querySelector('[name="precio_clp"]').value;
            form.querySelector('[name="precio_uf"]').value = calcularUFFromCLP(clp);
            const fd = new FormData(form);
            fetch('crud_propiedades.php', { method: 'POST', body: fd }).then(r => r.json()).then(resp => {
                if (resp.ok) {
                    Swal.fire('Guardado','Propiedad guardada','success').then(()=> location.reload());
                } else {
                    Swal.fire('Error', resp.error || 'No se pudo guardar','error');
                }
            }).catch(()=> Swal.fire('Error','Error de red','error'));
        });

        // Listener en tiempo real para calcular automáticamente la UF basándose en el precio CLP
        document.getElementById('propPrecioCLP').addEventListener('input', function() {
            const clpVal = this.value;
            document.getElementById('propPrecioUF').value = calcularUFFromCLP(clpVal);
        });

        // Vincular al botón de "Nueva Propiedad" para limpiar estados previos de edición
        document.querySelector('[data-bs-target="#modalPropiedad"]').addEventListener('click', prepararFormularioNuevo);

        document.addEventListener('DOMContentLoaded', function () {
            setModalMode('insertar');
        });
    </script>
    <script src="../js/bootstrap.bundle.min.js"></script>
    <script src="../js/validaciones.js"></script>
</body>
</html>