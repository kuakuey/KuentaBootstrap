<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/auth.php';

$currentPage = basename($_SERVER['PHP_SELF'], '.php');

function navActive(string $page, array $pages): string
{
    return in_array($page, $pages, true) ? 'active' : '';
}

function flashBootstrapType(string $type): string
{
    return match ($type) {
        'error' => 'danger',
        'success', 'warning', 'info', 'danger' => $type,
        default => 'secondary',
    };
}
?>
<!DOCTYPE html>
<html lang="es-EC">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle ?? 'Cuentas Hogar') ?> — Cuentas Hogar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-body-tertiary">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="calendario.php">Kuenta</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Menú">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?= navActive($currentPage, ['index', 'calendario', 'asignar-valor']) ?>" href="calendario.php">Calendario</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= navActive($currentPage, ['pagos-fijos', 'pago-fijo-form']) ?>" href="pagos-fijos.php">Fechas</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= navActive($currentPage, ['cuentas', 'cuenta-form', 'pagar']) ?>" href="cuentas.php">Lista</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= navActive($currentPage, ['personas']) ?>" href="personas.php">Personas</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= navActive($currentPage, ['tipos-pago']) ?>" href="tipos-pago.php">Tipos de pago</a>
                    </li>
                </ul>
                <?php $usuario = getUsuarioActual(); if ($usuario): ?>
                    <div class="d-flex align-items-center gap-2 text-white">
                        <span class="small opacity-75"><?= h($usuario['nombre']) ?></span>
                        <a href="logout.php" class="btn btn-sm btn-outline-light">Salir</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main class="container py-4">
        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?= h(flashBootstrapType($flash['type'])) ?> alert-dismissible fade show" role="alert">
                <?= h($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>
