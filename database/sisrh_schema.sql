-- SISRH - Sistema Integral de Salarios y Recursos Humanos
-- Script de creación de base de datos

CREATE DATABASE IF NOT EXISTS sisrh_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sisrh_db;

-- Tabla de usuarios del sistema
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    rol ENUM('admin', 'rrhh', 'contabilidad') NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_acceso TIMESTAMP NULL
);

-- Tabla de categorías de empleados
CREATE TABLE categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    salario_base DECIMAL(12,2) NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de cargos
CREATE TABLE cargos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    categoria_id INT,
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id)
);

-- Tabla de empleados
CREATE TABLE empleados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cedula VARCHAR(20) UNIQUE NOT NULL,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    fecha_nacimiento DATE NOT NULL,
    telefono VARCHAR(20),
    email VARCHAR(100),
    direccion TEXT,
    fecha_ingreso DATE NOT NULL,
    categoria_id INT,
    cargo_id INT,
    salario_base DECIMAL(12,2) NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id),
    FOREIGN KEY (cargo_id) REFERENCES cargos(id)
);

-- Tabla de tipos de contrato
CREATE TABLE tipos_contrato (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    descripcion TEXT,
    activo BOOLEAN DEFAULT TRUE
);

-- Tabla de contratos
CREATE TABLE contratos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empleado_id INT NOT NULL,
    tipo_contrato_id INT NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NULL,
    salario DECIMAL(12,2) NOT NULL,
    renovacion_automatica BOOLEAN DEFAULT FALSE,
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empleado_id) REFERENCES empleados(id),
    FOREIGN KEY (tipo_contrato_id) REFERENCES tipos_contrato(id)
);

-- Tabla de configuración del sistema
CREATE TABLE configuracion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parametro VARCHAR(100) UNIQUE NOT NULL,
    valor VARCHAR(255) NOT NULL,
    descripcion TEXT,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de conceptos de pago (bonificaciones, deducciones)
CREATE TABLE conceptos_pago (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) UNIQUE NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    tipo ENUM('bonificacion', 'deduccion') NOT NULL,
    es_porcentaje BOOLEAN DEFAULT FALSE,
    valor DECIMAL(12,2) NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de nóminas
CREATE TABLE nominas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empleado_id INT NOT NULL,
    periodo_mes INT NOT NULL,
    periodo_anio INT NOT NULL,
    salario_bruto DECIMAL(12,2) NOT NULL,
    aporte_ips_personal DECIMAL(12,2) NOT NULL,
    aporte_ips_patronal DECIMAL(12,2) NOT NULL,
    total_bonificaciones DECIMAL(12,2) DEFAULT 0,
    total_deducciones DECIMAL(12,2) DEFAULT 0,
    salario_neto DECIMAL(12,2) NOT NULL,
    fecha_pago DATE,
    estado ENUM('pendiente', 'pagado', 'anulado') DEFAULT 'pendiente',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empleado_id) REFERENCES empleados(id),
    UNIQUE KEY unique_nomina (empleado_id, periodo_mes, periodo_anio)
);

-- Tabla de detalles de nómina
CREATE TABLE nomina_detalles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nomina_id INT NOT NULL,
    concepto_id INT NOT NULL,
    cantidad DECIMAL(12,2) DEFAULT 1,
    valor_unitario DECIMAL(12,2) NOT NULL,
    valor_total DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (nomina_id) REFERENCES nominas(id) ON DELETE CASCADE,
    FOREIGN KEY (concepto_id) REFERENCES conceptos_pago(id)
);

-- Tabla de historial laboral
CREATE TABLE historial_laboral (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empleado_id INT NOT NULL,
    accion ENUM('ingreso', 'promocion', 'cambio_salario', 'suspension', 'reintegro', 'egreso') NOT NULL,
    fecha_accion DATE NOT NULL,
    cargo_anterior_id INT NULL,
    cargo_nuevo_id INT NULL,
    salario_anterior DECIMAL(12,2) NULL,
    salario_nuevo DECIMAL(12,2) NULL,
    observaciones TEXT,
    usuario_id INT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empleado_id) REFERENCES empleados(id),
    FOREIGN KEY (cargo_anterior_id) REFERENCES cargos(id),
    FOREIGN KEY (cargo_nuevo_id) REFERENCES cargos(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Tabla de sesiones de usuario
CREATE TABLE sesiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    fecha_inicio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_fin TIMESTAMP NULL,
    activa BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Insertar datos iniciales

-- Configuración inicial del sistema
INSERT INTO configuracion (parametro, valor, descripcion) VALUES
('salario_minimo', '2550000', 'Salario mínimo legal vigente en guaraníes'),
('aporte_ips_personal', '9', 'Porcentaje de aporte personal al IPS'),
('aporte_ips_patronal', '16.5', 'Porcentaje de aporte patronal al IPS'),
('dia_pago', '30', 'Día del mes para el pago de salarios'),
('empresa_nombre', 'Mi Empresa S.A.', 'Nombre de la empresa'),
('empresa_ruc', '80000000-1', 'RUC de la empresa');

-- Tipos de contrato iniciales
INSERT INTO tipos_contrato (nombre, descripcion) VALUES
('Indefinido', 'Contrato por tiempo indefinido'),
('Plazo Fijo', 'Contrato a plazo fijo'),
('Pasantía', 'Contrato de pasantía');

-- Conceptos de pago iniciales
INSERT INTO conceptos_pago (codigo, nombre, tipo, es_porcentaje, valor) VALUES
('BONO_PROD', 'Bonificación por Productividad', 'bonificacion', FALSE, 0),
('HORAS_EXTRA', 'Horas Extras', 'bonificacion', FALSE, 0),
('ADELANTO', 'Adelanto de Salario', 'deduccion', FALSE, 0),
('AUSENCIA', 'Descuento por Ausencia', 'deduccion', FALSE, 0),
('PRESTAMO', 'Descuento Préstamo', 'deduccion', FALSE, 0);

-- Usuario administrador inicial (password: admin123)
INSERT INTO usuarios (username, password, email, rol) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@empresa.com', 'admin');

-- Categorías iniciales
INSERT INTO categorias (nombre, descripcion, salario_base) VALUES
('Gerencial', 'Personal gerencial y directivo', 8000000),
('Profesional', 'Personal profesional universitario', 5000000),
('Técnico', 'Personal técnico especializado', 3500000),
('Administrativo', 'Personal administrativo', 2800000),
('Operativo', 'Personal operativo', 2550000);

-- Cargos iniciales
INSERT INTO cargos (nombre, descripcion, categoria_id) VALUES
('Gerente General', 'Máxima autoridad ejecutiva', 1),
('Gerente de RRHH', 'Responsable de recursos humanos', 1),
('Contador', 'Responsable de contabilidad', 2),
('Analista de RRHH', 'Analista de recursos humanos', 3),
('Asistente Administrativo', 'Apoyo administrativo', 4),
('Operario', 'Personal operativo general', 5);