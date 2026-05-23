function normalizarRut(rut) {
    return String(rut || '')
        .trim()
        .replace(/\./g, '')
        .replace(/\s+/g, '')
        .toUpperCase();
}

function formatearRut(rut) {
    const limpio = normalizarRut(rut);
    const partes = limpio.split('-');
    if (partes.length !== 2) return limpio;

    const numero = partes[0];
    const dv = partes[1];
    const cuerpo = numero.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return `${cuerpo}-${dv}`;
}

function validarRut(rut) {
    const limpio = normalizarRut(rut);
    if (!/^\d{7,8}-[\dK]$/.test(limpio)) return false;

    const [numero, dv] = limpio.split('-');
    let suma = 0;
    let multiplicador = 2;

    for (let i = numero.length - 1; i >= 0; i--) {
        suma += Number(numero[i]) * multiplicador;
        multiplicador = multiplicador === 7 ? 2 : multiplicador + 1;
    }

    const resto = 11 - (suma % 11);
    const dvCalculado = resto === 11 ? '0' : resto === 10 ? 'K' : String(resto);
    return dvCalculado === dv;
}

function validarEmail(email) {
    const valor = String(email || '').trim();
    if (!valor) return false;
    const partes = valor.split('@');
    if (partes.length !== 2) return false;
    if (partes[0].length < 3) return false;
    if (!partes[1] || !partes[1].includes('.')) return false;
    if (partes[1].startsWith('.')) return false;
    if (partes[1].endsWith('.')) return false;
    return true;
}

function validarPassword(pass) {
    return /^(?=.*[a-z])(?=.*[A-Z])(?=.*[^A-Za-z0-9]).{8,}$/.test(String(pass || ''));
}

function validarTelefonoMovil(telefono) {
    const limpio = String(telefono || '').replace(/\s+/g, '');
    return /^\d{8,15}$/.test(limpio);
}

function validarArchivoPdf(input) {
    if (!input || !input.files || input.files.length === 0) return false;
    const archivo = input.files[0];
    return archivo && /\.pdf$/i.test(archivo.name);
}

function mostrarError(mensaje) {
    Swal.fire('Error', mensaje, 'error');
}

function validarRegistroUsuario(form) {
    const rut = document.getElementById('rutRegistro')?.value || '';
    const nombre = document.getElementById('nombreRegistro')?.value || '';
    const nacimiento = document.getElementById('fechaNacimientoRegistro')?.value || '';
    const correo = document.getElementById('correoRegistro')?.value || '';
    const contrasena = document.getElementById('contrasenaRegistro')?.value || '';
    const sexo = document.getElementById('sexoRegistro')?.value || '';
    const telefono = document.getElementById('telefonoRegistro')?.value || '';
    const certificado = document.getElementById('certificadoRegistro');

    if (!rut || !nombre || !nacimiento || !correo || !contrasena || !sexo || !telefono) {
        mostrarError('Todos los campos deben estar completos.');
        return false;
    }
    if (!validarRut(rut)) { mostrarError('El RUT ingresado no es válido.'); return false; }
    if (!validarEmail(correo)) { mostrarError('El correo electrónico no es válido.'); return false; }
    if (!validarPassword(contrasena)) {
        mostrarError('La contraseña debe tener al menos 8 caracteres, una mayúscula, una minúscula y un carácter especial.');
        return false;
    }
    if (!validarTelefonoMovil(telefono)) { mostrarError('El teléfono móvil debe ser numérico y tener entre 8 y 15 dígitos.'); return false; }
    if (!validarArchivoPdf(certificado)) { mostrarError('Debe adjuntar un archivo PDF válido y no vacío.'); return false; }

    return true;
}

function validarLoginUsuario(email, pass) {
    if (!validarEmail(email)) { mostrarError('Ingrese un correo electrónico válido.'); return false; }
    if (!validarPassword(pass)) {
        mostrarError('La contraseña debe tener mínimo 8 caracteres, una mayúscula, una minúscula y un carácter especial.');
        return false;
    }
    return true;
}

function validarFormularioPropiedad(form) {
    const accion = form.querySelector('[name="accion"]')?.value || 'insertar';
    const tipo = form.querySelector('[name="tipo_propiedad"]').value.trim();
    const descripcion = form.querySelector('[name="descripcion"]').value.trim();
    const banos = form.querySelector('[name="banos"]').value;
    const dormitorios = form.querySelector('[name="dormitorios"]').value;
    const areaTotal = form.querySelector('[name="area_total"]').value;
    const areaConst = form.querySelector('[name="area_construida"]').value;
    const precioCLP = form.querySelector('[name="precio_clp"]').value;
    const fecha = form.querySelector('[name="fecha_publicacion"]').value;
    const visita = form.querySelector('[name="solicitar_visita"]').value;

    if (!tipo) { Swal.fire('Error','Tipo de propiedad es obligatorio','error'); return false; }
    if (!descripcion) { Swal.fire('Error','Descripción es obligatoria','error'); return false; }
    if (!banos || isNaN(banos)) { Swal.fire('Error','Cantidad de baños inválida','error'); return false; }
    if (!dormitorios || isNaN(dormitorios)) { Swal.fire('Error','Cantidad de dormitorios inválida','error'); return false; }
    if (!areaTotal || isNaN(areaTotal)) { Swal.fire('Error','Área total inválida','error'); return false; }
    if (!areaConst || isNaN(areaConst)) { Swal.fire('Error','Área construida inválida','error'); return false; }
    if (!precioCLP || isNaN(precioCLP)) { Swal.fire('Error','Precio CLP inválido','error'); return false; }
    if (!fecha) { Swal.fire('Error','Fecha de publicación es obligatoria','error'); return false; }
    if (!visita) { Swal.fire('Error','Seleccione si solicitar visita','error'); return false; }

    const bodega = form.querySelector('[name="bodega"]').value;
    const estacionamiento = form.querySelector('[name="estacionamiento"]').value;
    const logia = form.querySelector('[name="logia"]').value;
    const cocina = form.querySelector('[name="cocina_amoblada"]').value;
    const antejardin = form.querySelector('[name="antejardin"]').value;
    const patio = form.querySelector('[name="patio_trasero"]').value;
    const piscina = form.querySelector('[name="piscina"]').value;

    if (!bodega) { Swal.fire('Error','Bodega es obligatorio','error'); return false; }
    if (!estacionamiento) { Swal.fire('Error','Estacionamiento es obligatorio','error'); return false; }
    if (!logia) { Swal.fire('Error','Logia es obligatoria','error'); return false; }
    if (!cocina) { Swal.fire('Error','Cocina amoblada es obligatoria','error'); return false; }
    if (!antejardin) { Swal.fire('Error','Antejardín es obligatorio','error'); return false; }
    if (!patio) { Swal.fire('Error','Patio trasero es obligatorio','error'); return false; }
    if (!piscina) { Swal.fire('Error','Piscina es obligatoria','error'); return false; }

    const fotos = form.querySelector('[name="fotos[]"]');
    const cantidadFotos = fotos && fotos.files ? fotos.files.length : 0;
    if (accion === 'insertar' && cantidadFotos === 0) {
        Swal.fire('Error','Debe adjuntar al menos una fotografía','error');
        return false;
    }
    if (cantidadFotos > 10) {
        Swal.fire('Error','Máximo 10 fotografías','error');
        return false;
    }

    return true;
}

const UF_RATE = 35000;
function calcularUFFromCLP(clp) {
    if (!clp || isNaN(clp)) return '';
    return (Number(clp) / UF_RATE).toFixed(2);
}

function validarRegistroGestor(form) {
    return validarRegistroUsuario(form);
}
