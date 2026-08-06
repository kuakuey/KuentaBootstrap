<?php

require_once __DIR__ . '/config/install.php';

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$action = $_GET['accion'] ?? $_POST['accion'] ?? '';
$log = [];
$error = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' || $action === 'importar') {
    try {
        if ($action === 'importar_url') {
            $url = trim($_POST['sql_url'] ?? $_GET['sql_url'] ?? '');
            if ($url === '') {
                throw new InvalidArgumentException('Debes indicar una URL con archivo SQL.');
            }
            $log = importSqlFromUrl($url);
        } else {
            $log = runInstall();
        }
        $success = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$status = getInstallStatus();
$pageTitle = 'Importar base de datos';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?> — Cuentas Hogar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-body-tertiary">
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">Kuenta</a>
        </div>
    </nav>

    <main class="container py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h1 class="h3 mb-1">Importar base de datos</h1>
                <p class="text-muted mb-0">Instala las tablas en la base configurada en <code>config/db.php</code></p>
            </div>
            <?php if ($status['ready']): ?>
                <a href="index.php" class="btn btn-primary">Ir al inicio</a>
            <?php endif; ?>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h2 class="h5 mb-3">Estado actual</h2>
                        <ul class="list-group list-group-flush mb-3">
                            <li class="list-group-item d-flex justify-content-between align-items-start px-0">
                                <div>
                                    <strong>MySQL</strong>
                                    <div class="small text-muted"><?= h($status['mysql']['message']) ?></div>
                                </div>
                                <span class="badge <?= $status['mysql']['ok'] ? 'text-bg-success' : 'text-bg-danger' ?>">
                                    <?= $status['mysql']['ok'] ? 'OK' : 'Error' ?>
                                </span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-start px-0">
                                <div>
                                    <strong>Base de datos</strong>
                                    <div class="small text-muted"><?= h($status['database']['message']) ?></div>
                                </div>
                                <span class="badge <?= $status['database']['ok'] ? 'text-bg-success' : 'text-bg-danger' ?>">
                                    <?= $status['database']['ok'] ? 'OK' : 'Error' ?>
                                </span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-start px-0">
                                <div>
                                    <strong>Tablas</strong>
                                    <div class="small text-muted"><?= h($status['tables']['message']) ?></div>
                                </div>
                                <span class="badge <?= $status['tables']['ok'] ? 'text-bg-success' : 'text-bg-danger' ?>">
                                    <?= $status['tables']['ok'] ? 'OK' : 'Error' ?>
                                </span>
                            </li>
                        </ul>

                        <?php if ($status['ready']): ?>
                            <div class="alert alert-success mb-3">
                                Sistema listo: <?= (int) $status['tipos_pago'] ?> tipos de pago,
                                <?= (int) $status['cuentas'] ?> cuentas registradas.
                            </div>
                        <?php endif; ?>

                        <div class="bg-body-tertiary rounded p-3">
                            <h3 class="h6">Configuración en uso</h3>
                            <p class="mb-1"><strong>Host:</strong> <?= h(DB_HOST) ?></p>
                            <p class="mb-1"><strong>Base de datos:</strong> <?= h(DB_NAME) ?></p>
                            <p class="mb-1"><strong>Usuario:</strong> <?= h(DB_USER) ?></p>
                            <p class="text-muted small mb-0">Edita <code>config/db.php</code> si necesitas cambiar credenciales.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h2 class="h5 mb-3">Importar</h2>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= h($error) ?></div>
                        <?php endif; ?>

                        <?php if ($success && $log): ?>
                            <div class="alert alert-success">Importación completada correctamente.</div>
                            <ul class="small">
                                <?php foreach ($log as $entry): ?>
                                    <li><?= h($entry) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <form method="post" class="mb-4">
                            <input type="hidden" name="accion" value="importar">
                            <p>
                                Instala las tablas en la base definida en <code>config/db.php</code>
                                (<strong>(<?= h(DB_NAME) ?>)</strong>.
                                Si el usuario puede crear bases (XAMPP), la crea; en hosting usa la base ya existente.
                            </p>
                            <button type="submit" class="btn btn-primary">Importar base de datos</button>
                        </form>

                        <hr>

                        <form method="post">
                            <input type="hidden" name="accion" value="importar_url">
                            <div class="mb-3">
                                <label for="sql_url" class="form-label">Importar SQL desde URL</label>
                                <input
                                    type="url"
                                    class="form-control"
                                    id="sql_url"
                                    name="sql_url"
                                    placeholder="https://ejemplo.com/schema.sql"
                                    value="<?= h($_POST['sql_url'] ?? '') ?>"
                                >
                                <div class="form-text">Pega la URL pública de un archivo .sql</div>
                            </div>
                            <button type="submit" class="btn btn-outline-secondary">Descargar e importar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-4">
            <div class="card-body">
                <h2 class="h5 mb-3">Enlaces rápidos</h2>
                <div class="d-grid gap-2">
                    <a href="importar.php?accion=importar" class="btn btn-outline-secondary">
                        http://localhost/KuentaBootstrap/importar.php?accion=importar
                    </a>
                    <a href="http://localhost/phpmyadmin" class="btn btn-outline-secondary" target="_blank" rel="noopener">
                        Abrir phpMyAdmin
                    </a>
                </div>
            </div>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
