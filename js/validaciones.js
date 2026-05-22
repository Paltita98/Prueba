// Validación RUT 
function validarRut(rut) {
    if (!/^[0-9]+-[0-9kK]{1}$/.test(rut)) return false;
    let [num, dv] = rut.split('-');
    let suma = 0, mul = 2;
    for (let i = num.length - 1; i >= 0; i--) {
        suma += num[i] * mul;
        mul = mul === 7 ? 2 : mul + 1;
    }
    let res = 11 - (suma % 11);
    let vlp = res === 11 ? '0' : res === 10 ? 'K' : res.toString();
    return vlp.toUpperCase() === dv.toUpperCase();
}

// Validación Email 
function validarEmail(email) {
    const partes = email.split('@');
    if (partes.length !== 2) return false; // Sólo 1 símbolo @
    if (partes[0].length < 3) return false; // Mínimo 3 caracteres antes
    if (!partes[1].includes('.') || partes[1].indexOf('.') === 0) return false; // Al menos 1 punto después
    return true;
}

// Validación Contraseña 
function validarPassword(pass) {
    const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*]).{8,}$/;
    return regex.test(pass);
}

// Validación de formulario de propiedad antes de enviar
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

    // Características obligatorias
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

// Calcula precio UF a partir de CLP (ajustar UF_RATE según valor real)
const UF_RATE = 35000; // ejemplo, actualizar según fuente real
function calcularUFFromCLP(clp) {
    if (!clp || isNaN(clp)) return '';
    return (Number(clp) / UF_RATE).toFixed(2);
}

// Validaciones del registro de gestor (archivo PDF)
function validarRegistroGestor(form) {
    const rut = document.getElementById('rutRegistro').value.trim();
    const correo = document.getElementById('correoRegistro').value.trim();
    const telefono = document.getElementById('telefonoRegistro').value.trim();
    const certificado = document.getElementById('certificadoRegistro');

    if (!rut || !validarRut(rut)) { Swal.fire('Error','RUT inválido','error'); return false; }
    if (!correo || !validarEmail(correo)) { Swal.fire('Error','Correo inválido','error'); return false; }
    if (!telefono || isNaN(telefono) || telefono.length < 8) { Swal.fire('Error','Teléfono inválido','error'); return false; }
    if (!certificado || !certificado.files || certificado.files.length === 0) { Swal.fire('Error','Adjunte certificado PDF','error'); return false; }
    const file = certificado.files[0];
    if (!file.name.toLowerCase().endsWith('.pdf')) { Swal.fire('Error','El certificado debe ser PDF','error'); return false; }
    return true;
}

// Validación del login (contraseña y correo)
function validarLogin(email, pass) {
    if (!email || !validarEmail(email)) { Swal.fire('Error','Ingrese un correo válido','error'); return false; }
    if (!pass || !validarPassword(pass)) { Swal.fire('Error','Contraseña no cumple requisitos','error'); return false; }
    return true;
}