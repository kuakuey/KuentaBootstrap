<?php
require_once __DIR__ . '/includes/functions.php';

[$mes, $anio] = parseMesAnio(
    isset($_GET['mes']) ? (int) $_GET['mes'] : null,
    isset($_GET['anio']) ? (int) $_GET['anio'] : null
);

$filtro = $_GET['filtro'] ?? 'todas';
if (!in_array($filtro, ['todas', 'pendientes', 'pagadas'], true)) {
    $filtro = 'todas';
}
$personaId = getPersonaFiltroActivo();
$filtroExtra = array_filter([
    'filtro' => $filtro !== 'todas' ? $filtro : null,
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $filtro = $_POST['filtro'] ?? $filtro;
    $filtroExtra = array_filter([
        'filtro' => $filtro !== 'todas' ? $filtro : null,
    ]);

    if ($action === 'pagar_seleccionadas') {
        $ids = array_map('intval', $_POST['cuentas'] ?? []);
        $fechaPago = $_POST['fecha_pago'] ?: date('Y-m-d');
        $pagadas = marcarCuentasPagadas($ids, $fechaPago);
        flash('success', $pagadas > 0
            ? "Se registraron {$pagadas} pago(s) correctamente."
            : 'No se seleccionó ningún pago válido.');
    }

    if ($action === 'marcar_pagado') {
        $id = (int) ($_POST['id'] ?? 0);
        $fechaPago = $_POST['fecha_pago'] ?: date('Y-m-d');
        $stmt = getDB()->prepare("UPDATE cuentas SET estado = 'pagado', fecha_pago = ? WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$fechaPago, $id, getUsuarioId()]);
        flash('success', 'Cuenta marcada como pagada.');
    }

    if ($action === 'marcar_pendiente') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = getDB()->prepare("UPDATE cuentas SET estado = 'pendiente', fecha_pago = NULL WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$id, getUsuarioId()]);
        flash('success', 'Cuenta marcada como pendiente.');
    }

    if ($action === 'eliminar') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $resultado = eliminarCuenta($id);
            if ($resultado === 'serie') {
                flash('success', 'Fecha eliminada en todos los meses.');
            } elseif ($resultado === 'unica') {
                flash('success', 'Cuenta eliminada.');
            } else {
                flash('error', 'No se encontró la cuenta.');
            }
        } catch (Throwable $e) {
            flash('error', 'No se pudo eliminar la cuenta.');
        }
    }

    redirect(urlMes('cuentas.php', $mes, $anio, $filtroExtra));
}

ensureMesListo($mes, $anio);

$cuentas = getCuentasMes($mes, $anio, $personaId);
$resumen = resumenMes($mes, $anio, $personaId);

if ($filtro === 'pendientes') {
    $cuentas = array_values(array_filter($cuentas, fn($c) => $c['estado'] === 'pendiente'));
} elseif ($filtro === 'pagadas') {
    $cuentas = array_values(array_filter($cuentas, fn($c) => $c['estado'] === 'pagado'));
}

$hayPagables = false;
foreach ($cuentas as $c) {
    if ($c['estado'] === 'pendiente' && !empty($c['valor_asignado']) && (float) $c['monto'] > 0) {
        $hayPagables = true;
        break;
    }
}

$pageTitle = 'Lista';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Cuentas del mes</h1>
        <p class="text-muted mb-0"><?= monthName($mes) ?> <?= $anio ?> &mdash; <?= formatMoney((float) $resumen['monto_total']) ?> total<?= $personaId ? ' · filtrado por persona' : '' ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <div class="btn-group">
            <a href="<?= urlMes('cuentas.php', $mes - 1, $anio, $filtroExtra) ?>" class="btn btn-outline-secondary">&larr;</a>
            <a href="<?= urlMes('cuentas.php', (int) date('n'), (int) date('Y'), $filtroExtra) ?>" class="btn btn-outline-secondary">Hoy</a>
            <a href="<?= urlMes('cuentas.php', $mes + 1, $anio, $filtroExtra) ?>" class="btn btn-outline-secondary">&rarr;</a>
        </div>
        <a href="cuenta-form.php?mes=<?= $mes ?>&anio=<?= $anio ?>" class="btn btn-primary">+ Nueva cuenta</a>
    </div>
</div>

<ul class="nav nav-pills mb-3 gap-1">
    <li class="nav-item">
        <a class="nav-link <?= $filtro === 'todas' ? 'active' : '' ?>" href="<?= urlMes('cuentas.php', $mes, $anio, ['filtro' => 'todas']) ?>">Todas (<?= (int) $resumen['total'] ?>)</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $filtro === 'pendientes' ? 'active' : '' ?>" href="<?= urlMes('cuentas.php', $mes, $anio, ['filtro' => 'pendientes']) ?>">Pendientes (<?= (int) $resumen['pendientes'] ?>)</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $filtro === 'pagadas' ? 'active' : '' ?>" href="<?= urlMes('cuentas.php', $mes, $anio, ['filtro' => 'pagadas']) ?>">Pagadas (<?= (int) $resumen['pagadas'] ?>)</a>
    </li>
</ul>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if (empty($cuentas)): ?>
            <p class="text-muted mb-0"><?= $personaId ? 'No hay cuentas de esta persona para este mes.' : 'No hay cuentas registradas para este mes.' ?></p>
        <?php else: ?>
            <?php if ($hayPagables): ?>
                <form method="post" id="form-pagar">
                    <input type="hidden" name="action" value="pagar_seleccionadas">
                    <input type="hidden" name="filtro" value="<?= h($filtro) ?>">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="select-all">
                            <label class="form-check-label" for="select-all">Seleccionar para pagar</label>
                        </div>
                        <strong id="total-seleccionado">Total: $0</strong>
                    </div>
                </form>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <?php if ($hayPagables): ?><th style="width:2rem"></th><?php endif; ?>
                            <th>Cuenta</th>
                            <th>Vencimiento</th>
                            <th>Monto</th>
                            <th class="d-none d-md-table-cell">Tipo de pago</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cuentas as $cuenta):
                            $esPagable = $cuenta['estado'] === 'pendiente'
                                && !empty($cuenta['valor_asignado'])
                                && (float) $cuenta['monto'] > 0;
                            $visual = cuentaEstadoVisual($cuenta);
                            $esFija = !empty($cuenta['pago_fijo_id']);
                            $confirmEliminar = $esFija
                                ? '¿Eliminar esta fecha en todos los meses?'
                                : '¿Eliminar esta cuenta? Si hay más meses con el mismo nombre, también se borrarán.';
                            $rowClass = $cuenta['estado'] === 'pagado' ? 'table-success' : ($visual === 'overdue' ? 'table-danger' : '');
                        ?>
                            <tr class="<?= $rowClass ?>">
                                <?php if ($hayPagables): ?>
                                    <td>
                                        <?php if ($esPagable): ?>
                                            <input type="checkbox" class="form-check-input cuenta-check" name="cuentas[]" value="<?= (int) $cuenta['id'] ?>" form="form-pagar" data-monto="<?= (float) $cuenta['monto'] ?>">
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <strong><?= h($cuenta['nombre']) ?></strong>
                                    <?php if ($esFija): ?>
                                        <span class="badge text-bg-secondary">Fijo</span>
                                    <?php endif; ?>
                                    <?php if ($cuenta['notas']): ?>
                                        <div class="text-muted small"><?= h($cuenta['notas']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= formatDate($cuenta['fecha_vencimiento']) ?></td>
                                <td class="fw-semibold"><?= formatMoney((float) $cuenta['monto']) ?></td>
                                <td class="d-none d-md-table-cell">
                                    <?php if ($cuenta['tipo_pago_nombre']): ?>
                                        <span class="badge badge-tipo" style="--badge-color: <?= h($cuenta['tipo_pago_color']) ?>">
                                            <?= h($cuenta['tipo_pago_nombre']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($cuenta['estado'] === 'pagado'): ?>
                                        <span class="badge text-bg-success">Pagado<?= $cuenta['fecha_pago'] ? ' · ' . formatDate($cuenta['fecha_pago']) : '' ?></span>
                                    <?php elseif ($visual === 'overdue'): ?>
                                        <span class="badge text-bg-danger">Vencida</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-warning text-dark">Pendiente</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <a href="cuenta-form.php?id=<?= (int) $cuenta['id'] ?>&mes=<?= $mes ?>&anio=<?= $anio ?>" class="btn btn-sm btn-outline-secondary">Editar</a>
                                        <?php if ($cuenta['estado'] === 'pendiente'): ?>
                                            <?php if ($esPagable): ?>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="action" value="marcar_pagado">
                                                    <input type="hidden" name="id" value="<?= (int) $cuenta['id'] ?>">
                                                    <input type="hidden" name="fecha_pago" value="<?= date('Y-m-d') ?>">
                                                    <button type="submit" class="btn btn-sm btn-success">Pagar</button>
                                                </form>
                                            <?php else: ?>
                                                <a href="asignar-valor.php?id=<?= (int) $cuenta['id'] ?>&mes=<?= $mes ?>&anio=<?= $anio ?>" class="btn btn-sm btn-outline-secondary">Asignar valor</a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="action" value="marcar_pendiente">
                                                <input type="hidden" name="id" value="<?= (int) $cuenta['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">Desmarcar</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" class="d-inline" onsubmit="return confirm('<?= h($confirmEliminar) ?>')">
                                            <input type="hidden" name="action" value="eliminar">
                                            <input type="hidden" name="id" value="<?= (int) $cuenta['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($hayPagables): ?>
                <div class="row g-3 align-items-end mt-3">
                    <div class="col-md-4">
                        <label for="fecha_pago" class="form-label">Fecha de pago</label>
                        <input type="date" class="form-control" id="fecha_pago" name="fecha_pago" form="form-pagar" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-8">
                        <button type="submit" form="form-pagar" class="btn btn-primary btn-lg">Registrar pagos seleccionados</button>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
