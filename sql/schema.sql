USE PNK_INMOBILIARIA;

-- 2. Tabla de Propiedades: Mapeada exactamente con tu Prepared Statement y Formulario
CREATE TABLE IF NOT EXISTS propiedades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_propiedad VARCHAR(50) NOT NULL,
    descripcion TEXT NOT NULL,
    banos INT NOT NULL,
    dormitorios INT NOT NULL,
    area_total DECIMAL(10,2) NOT NULL,
    area_construida DECIMAL(10,2) NOT NULL,
    precio_clp INT NOT NULL,
    precio_uf DECIMAL(10,2) NOT NULL,
    fecha_publicacion DATE NOT NULL,
    solicitar_visita VARCHAR(5) NOT NULL DEFAULT 'No',
    bodega VARCHAR(5) NOT NULL DEFAULT 'No',

    estacionamiento VARCHAR(5) NOT NULL DEFAULT 'No',
    logia VARCHAR(5) NOT NULL DEFAULT 'No',
    cocina_amoblada VARCHAR(5) NOT NULL DEFAULT 'No',
    antejardin VARCHAR(5) NOT NULL DEFAULT 'No',
    patio_trasero VARCHAR(5) NOT NULL DEFAULT 'No',
    piscina VARCHAR(5) NOT NULL DEFAULT 'No',

    foto_url VARCHAR(255) NOT NULL DEFAULT 'casa1.webp',
    provincia VARCHAR(50) NOT NULL,
    comuna VARCHAR(50) NOT NULL,
    sector VARCHAR(50) NOT NULL
);

-- 3. Tabla de Galería de Imágenes Extendida (Hasta 10 fotos por propiedad)
CREATE TABLE IF NOT EXISTS propiedad_imagenes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    propiedad_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    is_default TINYINT(1) DEFAULT 0,
    FOREIGN KEY (propiedad_id) REFERENCES propiedades(id) ON DELETE CASCADE
);

-- 4. Tabla de Usuarios: Para el registro de Gestores Freelance y el Login Seguro
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rut VARCHAR(12) UNIQUE NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    fecha_nacimiento DATE NOT NULL,
    correo VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    sexo VARCHAR(20) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    certificado_path VARCHAR(255) NOT NULL,
    estado TINYINT(1) DEFAULT 1
);