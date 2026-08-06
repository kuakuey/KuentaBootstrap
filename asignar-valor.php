<?php
require_once __DIR__ . '/includes/functions.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
[$mes, $anio] = parseMesAnio(
    isset($_GET['mes']) ? (int) $_GET['mes'] : null,
    isset($_GET['anio']) ? (int) $_GET['anio'] : null
);

$cuenta = $id ? getCuenta($id) : null;
$tiposPago = getTiposPago();
$errors = [];

if (!$cuenta) {
    flash('error', 'Cuenta no encontrada.');
    redirect(urlMes('calendario.php', $mes, $anio));
}

if ($cuenta['estado'] === 'pagado' && cuentaTieneValor($cuenta)) {
    flash('error', 'Esta cuenta ya fue pagada.');
    redirect(urlMes('calendario.php', $mes, $anio));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $monto = parseMonto($_POST['monto'] ?? '0');
    $tipoPagoId = $_POST['tipo_pago_id'] !== '' ? (int) $_POST['tipo_pago_id'] : null;
    $notas = trim($_POST['notas'] ?? '');

    if ($monto < 0) {
        $errors[] = 'El valor no puede ser negativo.';
    }

    if (empty($errors)) {
        asignarValorCuenta($id, $monto, $tipoPagoId, $notas !== '' ? $notas : null);
        flash('success', $monto === 0.0
            ? 'Registrado: este mes no hay pago.'
            : 'Valor asignado para ' . monthName($mes) . '.');
        redirect(urlMes('calendario.php', $mes, $anio));
    }
}

$valorMostrar = '';
if (cuentaValorAsignado($cuenta)) {
    $valorMostrar = number_format((float) $cuenta['monto'], fmod((float) $cuenta['monto'], 1.0) ? 2 : 0, ',', '.');
}

$pageTitle = 'Asignar valor';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Asignar valor del mes</h1>
        <p class="text-muted mb-0">
            <?= h($cuenta['nombre']) ?> · <?= monthName($mes) ?> <?= $anio ?> ·
            corte día <?= (int) date('j', strtotime($cuenta['fecha_vencimiento'])) ?>
        </p>
    </div>
    <a href="<?= urlMes('calendario.php', $mes, $anio) ?>" class="btn btn-outline-secondary">&larr; Calendario</a>
</div>

<div class="card shadow-sm" style="max-width: 640px;">
    <div class="card-body">
        <div class="alert alert-info">
            El valor cambia cada mes. Si no hay nada que pagar, ingresa <strong>0</strong> y quedará en verde como resuelto.
        </div>

        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $error): ?>
                        <li><?= h($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post">
            <div class="mb-3">
                <label for="monto" class="form-label">Valor a pagar este mes *</label>
                <input type="text" class="form-control" id="monto" name="monto" value="<?= h($valorMostrar) ?>" placeholder="Ej: 45,00 o 0" required autofocus>
                <div class="form-text">Usa 0 cuando no debes pagar nada este mes.</div>
            </div>

            <div class="mb-3">
                <label for="tipo_pago_id" class="form-label">Tipo de pago</label>
                <select class="form-select" id="tipo_pago_id" name="tipo_pago_id">
                    <option value="">— Sin asignar —</option>
                    <?php foreach ($tiposPago as $tipo): ?>
                        <option value="<?= (int) $tipo['id'] ?>" <?= (string) $cuenta['tipo_pago_id'] === (string) $tipo['id'] ? 'selected' : '' ?>>
                            <?= h($tipo['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="tipo-pago-chips">
                    <?php foreach ($tiposPago as $tipo): ?>
                        <button type="button"
                                class="chip-btn"
                                data-tipo-id="<?= (int) $tipo['id'] ?>"
                                style="--chip-color: <?= h($tipo['color']) ?>">
                            <?= h($tipo['nombre']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="mb-3">
                <label for="notas" class="form-label">Detalle del mes</label>
                <textarea class="form-control" id="notas" name="notas" rows="3" placeholder="Factura, cuotas, observaciones..."><?= h($cuenta['notas'] ?? '') ?></textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Guardar valor</button>
                <a href="<?= urlMes('calendario.php', $mes, $anio) ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
