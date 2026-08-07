<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/locale.php';

function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Unknown database')) {
                die(
                    '<h2>Base de datos no encontrada</h2>' .
                    '<p>Crea e importa la base de datos <strong>' . htmlspecialchars(DB_NAME) . '</strong> desde:</p>' .
                    '<p><a href="importar.php">http://localhost/KuentaBootstrap/importar.php</a></p>' .
                    '<p>O usa <a href="http://localhost/phpmyadmin">phpMyAdmin</a> con <code>database/schema.sql</code>.</p>'
                );
            }
            throw $e;
        }

        initDatabase($pdo);
    }

    return $pdo;
}

function initDatabase(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS usuarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
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
                FOREIGN KEY (tipo_pago_id) REFERENCES tipos_pago(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pagos_fijos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            nombre VARCHAR(200) NOT NULL,
            dia_pago TINYINT NOT NULL,
            tipo_monto ENUM('variable', 'fijo') NOT NULL DEFAULT 'variable',
            monto DECIMAL(12,2) NOT NULL DEFAULT 0,
            tipo_pago_id INT NULL,
            notas TEXT NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_pagos_fijos_usuario
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
            CONSTRAINT fk_pagos_fijos_tipo_pago
                FOREIGN KEY (tipo_pago_id) REFERENCES tipos_pago(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    migrateCuentasColumns($pdo);
    migratePagosFijosColumns($pdo);
    migrateTiposPagoEcuador($pdo);
    migrateValorAsignado($pdo);
    migrateUsuarios($pdo);
}

function migrateValorAsignado(PDO $pdo): void
{
    $col = $pdo->query("SHOW COLUMNS FROM cuentas LIKE 'valor_asignado'")->fetch();
    if (!$col) {
        $pdo->exec('ALTER TABLE cuentas ADD COLUMN valor_asignado TINYINT(1) NOT NULL DEFAULT 0 AFTER pago_fijo_id');
    }

    $pdo->exec('UPDATE cuentas SET valor_asignado = 1 WHERE monto > 0 OR estado = \'pagado\'');
}

function migrateTiposPagoEcuador(PDO $pdo): void
{
    $pdo->exec("
        UPDATE tipos_pago SET nombre = 'Deuna', color = '#ec4899'
        WHERE nombre IN ('Nequi / Daviplata', 'Nequi', 'Daviplata')
    ");
}

function migrateCuentasColumns(PDO $pdo): void
{
    $columns = $pdo->query("SHOW COLUMNS FROM cuentas LIKE 'pago_fijo_id'")->fetch();
    if (!$columns) {
        $pdo->exec('ALTER TABLE cuentas ADD COLUMN pago_fijo_id INT NULL AFTER tipo_pago_id');
    }

    $fk = $pdo->query("
        SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'cuentas'
          AND COLUMN_NAME = 'pago_fijo_id'
          AND REFERENCED_TABLE_NAME = 'pagos_fijos'
    ")->fetch();

    if (!$fk) {
        try {
            $pdo->exec('
                ALTER TABLE cuentas
                ADD CONSTRAINT fk_cuentas_pago_fijo
                FOREIGN KEY (pago_fijo_id) REFERENCES pagos_fijos(id) ON DELETE SET NULL
            ');
        } catch (PDOException) {
            // La FK puede fallar si pagos_fijos aún no existe en instalaciones antiguas.
        }
    }
}

function migratePagosFijosColumns(PDO $pdo): void
{
    $tipoMonto = $pdo->query("SHOW COLUMNS FROM pagos_fijos LIKE 'tipo_monto'")->fetch();
    if (!$tipoMonto) {
        $pdo->exec("
            ALTER TABLE pagos_fijos
            ADD COLUMN tipo_monto ENUM('variable', 'fijo') NOT NULL DEFAULT 'variable' AFTER dia_pago
        ");
    }

    $monto = $pdo->query("SHOW COLUMNS FROM pagos_fijos LIKE 'monto'")->fetch();
    if (!$monto) {
        $pdo->exec('ALTER TABLE pagos_fijos ADD COLUMN monto DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER tipo_monto');
    }
}

function migrateUsuarios(PDO $pdo): void
{
    $tablas = ['tipos_pago', 'pagos_fijos', 'cuentas'];
    foreach ($tablas as $tabla) {
        $col = $pdo->query("SHOW COLUMNS FROM {$tabla} LIKE 'usuario_id'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE {$tabla} ADD COLUMN usuario_id INT NULL AFTER id");
        }
    }

    $huerfanos = (int) $pdo->query('
        SELECT COUNT(*) FROM tipos_pago WHERE usuario_id IS NULL
    ')->fetchColumn();

    if ($huerfanos > 0) {
        $email = 'datos@migracion.local';
        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
        $stmt->execute([$email]);
        $usuarioId = $stmt->fetchColumn();

        if (!$usuarioId) {
            $hash = password_hash('migracion123', PASSWORD_DEFAULT);
            $insert = $pdo->prepare('INSERT INTO usuarios (nombre, email, password) VALUES (?, ?, ?)');
            $insert->execute(['Usuario migrado', $email, $hash]);
            $usuarioId = (int) $pdo->lastInsertId();
        } else {
            $usuarioId = (int) $usuarioId;
        }

        foreach ($tablas as $tabla) {
            $pdo->exec("UPDATE {$tabla} SET usuario_id = {$usuarioId} WHERE usuario_id IS NULL");
        }
    }

    foreach ($tablas as $tabla) {
        $col = $pdo->query("SHOW COLUMNS FROM {$tabla} LIKE 'usuario_id'")->fetch();
        if ($col && strtoupper($col['Null']) === 'YES') {
            try {
                $pdo->exec("ALTER TABLE {$tabla} MODIFY usuario_id INT NOT NULL");
            } catch (PDOException) {
                // Puede fallar si aún hay NULL.
            }
        }
    }

    try {
        $pdo->exec('ALTER TABLE tipos_pago DROP INDEX nombre');
    } catch (PDOException) {
        // Índice antiguo no existe.
    }

    try {
        $pdo->exec('CREATE UNIQUE INDEX uk_tipos_pago_usuario_nombre ON tipos_pago (usuario_id, nombre)');
    } catch (PDOException) {
        // Ya existe.
    }
}
