<?php

require_once __DIR__ . '/db.php';

function getServerPDO(): PDO
{
    $dsn = sprintf('mysql:host=%s;charset=%s', DB_HOST, DB_CHARSET);

    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function testMysqlConnection(): array
{
    try {
        // Primero intenta conectar a la BD configurada (hosting suele exigirlo).
        if (canConnectToConfiguredDatabase()) {
            return ['ok' => true, 'message' => 'Conexión a MySQL correcta (' . DB_NAME . ').'];
        }

        getServerPDO();
        return ['ok' => true, 'message' => 'Conexión a MySQL correcta.'];
    } catch (PDOException $e) {
        return ['ok' => false, 'message' => 'No se pudo conectar a MySQL: ' . $e->getMessage()];
    }
}

function canConnectToConfiguredDatabase(): bool
{
    try {
        getDatabasePDO();
        return true;
    } catch (PDOException) {
        return false;
    }
}

function databaseExists(): bool
{
    if (canConnectToConfiguredDatabase()) {
        return true;
    }

    try {
        $pdo = getServerPDO();
        $stmt = $pdo->prepare('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
        $stmt->execute([DB_NAME]);
        return (bool) $stmt->fetch();
    } catch (PDOException) {
        return false;
    }
}

function getInstallStatus(): array
{
    $status = [
        'mysql' => testMysqlConnection(),
        'database' => ['ok' => false, 'message' => 'Base de datos no encontrada.'],
        'tables' => ['ok' => false, 'message' => 'Tablas no encontradas.'],
        'tipos_pago' => 0,
        'cuentas' => 0,
        'ready' => false,
    ];

    if (!$status['mysql']['ok']) {
        return $status;
    }

    if (!databaseExists()) {
        $status['database'] = [
            'ok' => false,
            'message' => 'No se encuentra la base "' . DB_NAME . '". Créala en el panel del hosting o ajusta DB_NAME en config/db.php.',
        ];
        return $status;
    }

    $status['database'] = ['ok' => true, 'message' => 'Base de datos "' . DB_NAME . '" encontrada.'];

    try {
        $pdo = getDatabasePDO();
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $required = ['usuarios', 'tipos_pago', 'pagos_fijos', 'cuentas'];
        $missing = array_diff($required, $tables);

        if (empty($missing)) {
            $status['tables'] = ['ok' => true, 'message' => 'Tablas listas.'];
            $status['tipos_pago'] = (int) $pdo->query('SELECT COUNT(*) FROM tipos_pago')->fetchColumn();
            $status['cuentas'] = (int) $pdo->query('SELECT COUNT(*) FROM cuentas')->fetchColumn();
            $status['ready'] = true;
        } else {
            $status['tables'] = [
                'ok' => false,
                'message' => 'Faltan tablas: ' . implode(', ', $missing),
            ];
        }
    } catch (PDOException $e) {
        $status['database'] = ['ok' => false, 'message' => $e->getMessage()];
    }

    return $status;
}

/**
 * Intenta crear la BD configurada en DB_NAME.
 * En hosting compartido suele fallar: si ya existe y es accesible, continúa.
 */
function ensureDatabase(): string
{
    if (canConnectToConfiguredDatabase()) {
        return 'Usando base de datos existente "' . DB_NAME . '".';
    }

    try {
        $pdo = getServerPDO();
        $name = str_replace('`', '``', DB_NAME);
        $pdo->exec(sprintf(
            'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            $name
        ));
    } catch (PDOException $e) {
        throw new RuntimeException(
            'No se pudo crear la base "' . DB_NAME . '". ' .
            'En hosting compartido créala en el panel (hPanel, cPanel, etc.) ' .
            'y pon el nombre exacto en config/db.php (DB_NAME). ' .
            'Detalle: ' . $e->getMessage()
        );
    }

    if (!canConnectToConfiguredDatabase()) {
        throw new RuntimeException(
            'La base "' . DB_NAME . '" se creó o ya existía, pero el usuario "' . DB_USER . '" no puede acceder. ' .
            'Asigna ese usuario a la base en el panel del hosting.'
        );
    }

    return 'Base de datos "' . DB_NAME . '" creada o verificada.';
}

function isSchemaMetaStatement(string $statement): bool
{
    return stripos($statement, 'USE ') === 0
        || stripos($statement, 'CREATE DATABASE') === 0;
}

function runSqlStatements(array $statements): array
{
    $log = [];
    $dbPdo = getDatabasePDO();

    foreach ($statements as $statement) {
        if ($statement === '' || isSchemaMetaStatement($statement)) {
            if (stripos($statement, 'CREATE DATABASE') === 0) {
                $log[] = 'CREATE DATABASE del archivo omitido; se usa DB_NAME de config/db.php.';
            }
            continue;
        }

        if (stripos($statement, 'INSERT INTO tipos_pago') === 0 && databaseHasTiposPago()) {
            $log[] = 'Tipos de pago ya existían, inserción omitida.';
            continue;
        }

        $dbPdo->exec($statement);
        $log[] = describeStatement($statement);
    }

    return $log;
}

function parseSqlFile(string $sql): array
{
    $sql = preg_replace('/--.*$/m', '', $sql);
    return array_values(array_filter(array_map('trim', explode(';', $sql))));
}

function runSchemaFromFile(): array
{
    $path = __DIR__ . '/../database/schema.sql';
    if (!is_readable($path)) {
        throw new RuntimeException('No se encontró database/schema.sql');
    }

    return runSqlStatements(parseSqlFile(file_get_contents($path)));
}

function getDatabasePDO(): PDO
{
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);

    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function databaseHasTiposPago(): bool
{
    if (!canConnectToConfiguredDatabase()) {
        return false;
    }

    try {
        $pdo = getDatabasePDO();
        $pdo->query('SELECT 1 FROM tipos_pago LIMIT 1');
        return (int) $pdo->query('SELECT COUNT(*) FROM tipos_pago')->fetchColumn() > 0;
    } catch (PDOException) {
        return false;
    }
}

function describeStatement(string $statement): string
{
    if (stripos($statement, 'CREATE TABLE IF NOT EXISTS usuarios') === 0) {
        return 'Tabla usuarios creada o verificada.';
    }
    if (stripos($statement, 'CREATE TABLE IF NOT EXISTS tipos_pago') === 0) {
        return 'Tabla tipos_pago creada o verificada.';
    }
    if (stripos($statement, 'CREATE TABLE IF NOT EXISTS pagos_fijos') === 0) {
        return 'Tabla pagos_fijos creada o verificada.';
    }
    if (stripos($statement, 'CREATE TABLE IF NOT EXISTS cuentas') === 0) {
        return 'Tabla cuentas creada o verificada.';
    }
    if (stripos($statement, 'INSERT INTO tipos_pago') === 0) {
        return 'Tipos de pago iniciales insertados.';
    }

    return 'Consulta ejecutada correctamente.';
}

function runInstall(): array
{
    $log = [];

    $connection = testMysqlConnection();
    if (!$connection['ok']) {
        throw new RuntimeException($connection['message']);
    }
    $log[] = $connection['message'];

    $log[] = ensureDatabase();
    $log = array_merge($log, runSchemaFromFile());

    require_once __DIR__ . '/database.php';
    getDB();

    $final = getInstallStatus();
    $log[] = 'Importación completada en "' . DB_NAME . '": '
        . $final['tipos_pago'] . ' tipos de pago, '
        . $final['cuentas'] . ' cuentas.';

    return $log;
}

function importSqlFromUrl(string $url): array
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new InvalidArgumentException('La URL no es válida.');
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => 15,
            'follow_location' => 1,
            'max_redirects' => 3,
        ],
    ]);

    $sql = @file_get_contents($url, false, $context);
    if ($sql === false) {
        throw new RuntimeException('No se pudo descargar el archivo SQL desde la URL.');
    }

    $log = [ensureDatabase(), 'Archivo descargado desde URL.'];
    $log = array_merge($log, runSqlStatements(parseSqlFile($sql)));

    return $log;
}
