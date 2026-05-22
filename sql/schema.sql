CREATE DATABASE IF NOT EXISTS PNK_INMOBILIARIA CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE PNK_INMOBILIARIA;

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
) ENGINE=InnoDB;

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
    solicitar_visita VARCHAR(5) NOT NULL,
    bodega VARCHAR(5) NOT NULL,
    estacionamiento INT NOT NULL,
    logia INT NOT NULL,
    cocina_amoblada INT NOT NULL,
    antejardin INT NOT NULL,
    patio_trasero INT NOT NULL,
    piscina INT NOT NULL,
    foto_url VARCHAR(255) DEFAULT 'casa1.webp',
    comuna VARCHAR(50) NOT NULL,
    provincia VARCHAR(50) NOT NULL,
    sector VARCHAR(50) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS propiedad_imagenes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    propiedad_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    is_default TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (propiedad_id) REFERENCES propiedades(id) ON DELETE CASCADE
) ENGINE=InnoDB;