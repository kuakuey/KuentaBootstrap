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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $monto = parseMonto($_POST['monto'] ?? '0');
    $fechaVencimiento = $_POST['fecha_vencimiento'] ?? '';
    $tipoPagoId = $_POST['tipo_pago_id'] !== '' ? (int) $_POST['tipo_pago_id'] : null;
    $estado = $_POST['estado'] ?? 'pendiente';
    $fechaPago = $_POST['fecha_pago'] ?: null;
    $notas = trim($_POST['notas'] ?? '');
    $mesPost = (int) ($_POST['mes'] ?? $mes);
    $anioPost = (int) ($_POST['anio'] ?? $anio);

    if ($nombre === '') {
        $errors[] = 'El nombre es obligatorio.';
    }
    if ($monto <= 0) {
        $errors[] = 'El monto debe ser mayor a cero.';
    }
    if (!$fechaVencimiento) {
        $errors[] = 'La fecha de vencimiento es obligatoria.';
    }

    if ($estado === 'pagado' && !$fechaPago) {
        $fechaPago = date('Y-m-d');
    }
    if ($estado === 'pendiente') {
        $fechaPago = null;
    }

    if (empty($errors)) {
        $db = getDB();

        if ($id) {
            $stmt = $db->prepare("
                UPDATE cuentas SET
                    nombre = ?, monto = ?, fecha_vencimiento = ?, tipo_pago_id = ?,
                    estado = ?, fecha_pago = ?, notas = ?, mes = ?, anio = ?
                WHERE id = ? AND usuario_id = ?
            ");
            $stmt->execute([
                $nombre, $monto, $fechaVencimiento, $tipoPagoId,
                $estado, $fechaPago, $notas, $mesPost, $anioPost, $id, getUsuarioId()
            ]);
            flash('success', 'Cuenta actualizada correctamente.');
        } else {
            $stmt = $db->prepare("
                INSERT INTO cuentas (usuario_id, nombre, monto, fecha_vencimiento, tipo_pago_id, valor_asignado, estado, fecha_pago, notas, mes, anio)
                VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                getUsuarioId(), $nombre, $monto, $fechaVencimiento, $tipoPagoId,
                $estado, $fechaPago, $notas, $mesPost, $anioPost
            ]);
            flash('success', 'Cuenta creada correctamente.');
        }

        redirect(urlMes('calendario.php', $mesPost, $anioPost));
    }

    $cuenta = [
        'nombre' => $nombre,
        'monto' => $monto,
        'fecha_vencimiento' => $fechaVencimiento,
        'tipo_pago_id' => $tipoPagoId,
        'estado' => $estado,
        'fecha_pago' => $fechaPago,
        'notas' => $notas,
        'mes' => $mesPost,
        'anio' => $anioPost,
    ];
    $mes = $mesPost;
    $anio = $anioPost;
}

$defaults = [
    'nombre' => '',
    'monto' => '',
    'fecha_vencimiento' => sprintf('%04d-%02d-05', $anio, $mes),
    'tipo_pago_id' => $tiposPago[0]['id'] ?? '',
    'estado' => 'pendiente',
    'fecha_pago' => '',
    'notas' => '',
    'mes' => $mes,
    'anio' => $anio,
];

$data = $cuenta ? array_merge($defaults, $cuenta) : $defaults;

$pageTitle = $id ? 'Editar cuenta' : 'Nueva cuenta';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1"><?= $id ? 'Editar pago extra' : 'Pago extra del mes' ?></h1>
        <p class="text-muted mb-0">Solo para gastos puntuales. Las fechas fijas se gestionan en Fechas.</p>
    </div>
    <a href="<?= urlMes('calendario.php', $mes, $anio) ?>" class="btn btn-outline-secondary">&larr; Volver al calendario</a>
</div>

<div class="card shadow-sm" style="max-width: 720px;">
    <div class="card-body">
        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $error): ?>
                        <li><?= h($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (empty($tiposPago)): ?>
            <div class="alert alert-warning">
                No hay tipos de pago. <a href="tipos-pago.php">Agrega al menos uno</a> para seleccionar rápido.
            </div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="mes" value="<?= (int) $data['mes'] ?>">
            <input type="hidden" name="anio" value="<?= (int) $data['anio'] ?>">

            <div class="mb-3">
                <label for="nombre" class="form-label">Nombre de la cuenta *</label>
                <input type="text" class="form-control" id="nombre" name="nombre" value="<?= h($data['nombre']) ?>" placeholder="Ej: Reparación, Regalo, pago extra..." required>
                <?php if (!empty($data['pago_fijo_id'])): ?>
                    <div class="form-text">Viene de una fecha fija. Para cambiar el valor usa el calendario.</div>
                <?php else: ?>
                    <div class="form-text">Para cuotas diferidas con fecha fija, usa <a href="pagos-fijos.php">Fechas</a>.</div>
                <?php endif; ?>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="monto" class="form-label">Monto *</label>
                    <input type="text" class="form-control" id="monto" name="monto" value="<?= h($data['monto'] !== '' ? number_format((float) $data['monto'], fmod((float) $data['monto'], 1.0) ? 2 : 0, ',', '.') : '') ?>" placeholder="Ej: 85,50" required>
                </div>
                <div class="col-md-6">
                    <label for="fecha_vencimiento" class="form-label">Fecha de vencimiento *</label>
                    <input type="date" class="form-control" id="fecha_vencimiento" name="fecha_vencimiento" value="<?= h($data['fecha_vencimiento']) ?>" required>
                </div>
            </div>

            <div class="mb-3 mt-3">
                <label for="tipo_pago_id" class="form-label">Tipo de pago</label>
                <select class="form-select" id="tipo_pago_id" name="tipo_pago_id">
                    <option value="">— Sin asignar —</option>
                    <?php foreach ($tiposPago as $tipo): ?>
                        <option value="<?= (int) $tipo['id'] ?>" <?= (string) $data['tipo_pago_id'] === (string) $tipo['id'] ? 'selected' : '' ?>>
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

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="estado" class="form-label">Estado</label>
                    <select class="form-select" id="estado" name="estado">
                        <option value="pendiente" <?= $data['estado'] === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                        <option value="pagado" <?= $data['estado'] === 'pagado' ? 'selected' : '' ?>>Pagado</option>
                    </select>
                </div>
                <div class="col-md-6" id="fecha-pago-group" style="<?= $data['estado'] === 'pagado' ? '' : 'display:none' ?>">
                    <label for="fecha_pago" class="form-label">Fecha de pago</label>
                    <input type="date" class="form-control" id="fecha_pago" name="fecha_pago" value="<?= h($data['fecha_pago'] ?: date('Y-m-d')) ?>">
                </div>
            </div>

            <div class="mb-3 mt-3">
                <label for="notas" class="form-label">Detalles / notas</label>
                <textarea class="form-control" id="notas" name="notas" rows="4" placeholder="Número de factura, referencia, observaciones..."><?= h($data['notas']) ?></textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?= $id ? 'Guardar cambios' : 'Crear cuenta' ?></button>
                <a href="<?= urlMes('calendario.php', $mes, $anio) ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
