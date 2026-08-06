<?php
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = getDB()->prepare('UPDATE pagos_fijos SET activo = CASE WHEN activo = 1 THEN 0 ELSE 1 END WHERE id = ? AND usuario_id = ?');
        $stmt->execute([$id, getUsuarioId()]);
        flash('success', 'Estado actualizado.');
    }

    if ($action === 'eliminar') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            if (eliminarPagoFijo($id)) {
                flash('success', 'Fecha de pago y todas sus fechas del calendario eliminadas.');
            } else {
                flash('error', 'No se encontró la fecha de pago.');
            }
        } catch (Throwable $e) {
            flash('error', 'No se pudo eliminar la fecha de pago.');
        }
    }

    if ($action === 'limpiar_huerfana') {
        $nombre = trim($_POST['nombre'] ?? '');
        $dia = (int) ($_POST['dia'] ?? 0);
        if ($nombre !== '' && $dia > 0) {
            $borradas = eliminarSerieHuerfana($nombre, $dia);
            flash('success', $borradas > 0
                ? "Se eliminaron {$borradas} fecha(s) sueltas de «{$nombre}»."
                : 'No había fechas sueltas para eliminar.');
        } else {
            flash('error', 'Datos incompletos para limpiar.');
        }
    }

    if ($action === 'limpiar_todas_huerfanas') {
        $borradas = eliminarTodasSeriesHuerfanas();
        flash('success', $borradas > 0
            ? "Se eliminaron {$borradas} fecha(s) sueltas del calendario."
            : 'No se encontraron fechas sueltas.');
    }

    if ($action === 'generar_mes') {
        [$mes, $anio] = parseMesAnio(
            isset($_POST['mes']) ? (int) $_POST['mes'] : null,
            isset($_POST['anio']) ? (int) $_POST['anio'] : null
        );
        $creados = syncPagosFijosMes($mes, $anio);
        flash('success', $creados > 0
            ? "Se crearon {$creados} fechas en " . monthName($mes) . " {$anio}."
            : 'Ese mes ya tenía todas las fechas.');
        redirect(urlMes('calendario.php', $mes, $anio));
    }

    redirect('pagos-fijos.php');
}

$pagos = getPagosFijos(false);
$seriesHuerfanas = getSeriesHuerfanas();
$totalHuerfanas = array_sum(array_column($seriesHuerfanas, 'total'));
$mes = (int) date('n');
$anio = (int) date('Y');

$pageTitle = 'Fechas de pago';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Fechas de pago</h1>
        <p class="text-muted mb-0">Paso 1: define en qué día del mes toca pagar cada cuenta</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="pago-fijo-form.php" class="btn btn-primary">+ Nueva fecha</a>
        <a href="<?= urlMes('calendario.php', $mes, $anio) ?>" class="btn btn-outline-secondary">Calendario</a>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <strong>Tu flujo</strong>
        <ol class="mb-0 mt-2">
            <li>Registras aquí las <strong>fechas fijas</strong> (día de corte).</li>
            <li>En el <strong>calendario</strong>, mes a mes, asignas el valor cuando toca.</li>
            <li>En la <strong>Lista</strong>, seleccionas qué cuentas vas a pagar.</li>
            <li>En el calendario pasan a <span class="color-hint green">verde</span> (o <span class="color-hint red">rojo</span> si se vencieron).</li>
        </ol>
    </div>
</div>

<?php if (!empty($seriesHuerfanas)): ?>
<div class="card shadow-sm border-warning mb-4">
    <div class="card-header bg-warning-subtle d-flex justify-content-between align-items-center">
        <h2 class="h6 mb-0">Fechas sueltas detectadas</h2>
        <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar todas las fechas sueltas del calendario (<?= (int) $totalHuerfanas ?>)?')">
            <input type="hidden" name="action" value="limpiar_todas_huerfanas">
            <button type="submit" class="btn btn-sm btn-danger">Limpiar todas (<?= (int) $totalHuerfanas ?>)</button>
        </form>
    </div>
    <div class="card-body">
        <p class="text-muted">
            Parece que se borró solo un mes y quedaron registros en otros. Aquí puedes eliminar el resto de una vez.
        </p>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Día</th>
                        <th>Cuenta</th>
                        <th>Registros</th>
                        <th>Rango</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($seriesHuerfanas as $serie): ?>
                        <tr>
                            <td><strong>Día <?= (int) $serie['dia'] ?></strong></td>
                            <td><strong><?= h($serie['nombre']) ?></strong></td>
                            <td><?= (int) $serie['total'] ?> meses</td>
                            <td class="text-muted">
                                <?= h(monthName((int) $serie['desde_mes'])) ?> <?= (int) $serie['desde_anio'] ?>
                                —
                                <?= h(monthName((int) $serie['hasta_mes'])) ?> <?= (int) $serie['hasta_anio'] ?>
                            </td>
                            <td>
                                <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar las <?= (int) $serie['total'] ?> fechas sueltas de «<?= h($serie['nombre']) ?>»?')">
                                    <input type="hidden" name="action" value="limpiar_huerfana">
                                    <input type="hidden" name="nombre" value="<?= h($serie['nombre']) ?>">
                                    <input type="hidden" name="dia" value="<?= (int) $serie['dia'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar serie</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if (empty($pagos)): ?>
            <p class="text-muted">Aún no hay fechas de pago.</p>
            <a href="pago-fijo-form.php" class="btn btn-primary">Agregar la primera</a>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Día</th>
                            <th>Cuenta</th>
                            <th>Tipo habitual</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pagos as $pago): ?>
                            <tr class="<?= $pago['activo'] ? '' : 'table-secondary' ?>">
                                <td><strong>Día <?= (int) $pago['dia_pago'] ?></strong></td>
                                <td>
                                    <strong><?= h($pago['nombre']) ?></strong>
                                    <?php if ($pago['notas']): ?>
                                        <div class="text-muted small"><?= h($pago['notas']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($pago['tipo_pago_nombre']): ?>
                                        <span class="badge badge-tipo" style="--badge-color: <?= h($pago['tipo_pago_color']) ?>">
                                            <?= h($pago['tipo_pago_nombre']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $pago['activo']
                                        ? '<span class="badge text-bg-success">Activa</span>'
                                        : '<span class="badge text-bg-secondary">Inactiva</span>' ?>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <a href="pago-fijo-form.php?id=<?= (int) $pago['id'] ?>" class="btn btn-sm btn-outline-secondary">Editar</a>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?= (int) $pago['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                <?= $pago['activo'] ? 'Desactivar' : 'Activar' ?>
                                            </button>
                                        </form>
                                        <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar esta fecha de pago y todas sus fechas del calendario?')">
                                            <input type="hidden" name="action" value="eliminar">
                                            <input type="hidden" name="id" value="<?= (int) $pago['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
