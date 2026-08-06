-- Esquema de tablas para Cuentas Hogar.
-- El nombre de la base lo defines en config/db.php (DB_NAME).
--
-- Instalación recomendada: http://tu-dominio/importar.php
--
-- phpMyAdmin (hosting): selecciona primero tu base de datos en el panel
-- y luego importa este archivo (solo crea tablas, no crea la base).

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tipos_pago (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    color VARCHAR(7) NOT NULL DEFAULT '#6366f1',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tipos_pago_usuario_nombre (usuario_id, nombre),
    CONSTRAINT fk_tipos_pago_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pagos_fijos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    dia_pago TINYINT NOT NULL,
    tipo_pago_id INT NULL,
    notas TEXT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pagos_fijos_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_pagos_fijos_tipo_pago
        FOREIGN KEY (tipo_pago_id) REFERENCES tipos_pago(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cuentas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    monto DECIMAL(12,2) NOT NULL DEFAULT 0,
    fecha_vencimiento DATE NOT NULL,
    tipo_pago_id INT NULL,
    pago_fijo_id INT NULL,
    valor_asignado TINYINT(1) NOT NULL DEFAULT 0,
    estado ENUM('pendiente', 'pagado') NOT NULL DEFAULT 'pendiente',
    fecha_pago DATE NULL,
    notas TEXT NULL,
    mes TINYINT NOT NULL,
    anio SMALLINT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cuentas_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_cuentas_tipo_pago
        FOREIGN KEY (tipo_pago_id) REFERENCES tipos_pago(id) ON DELETE SET NULL,
    CONSTRAINT fk_cuentas_pago_fijo
        FOREIGN KEY (pago_fijo_id) REFERENCES pagos_fijos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
