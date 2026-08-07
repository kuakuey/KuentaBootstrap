<?php
require_once __DIR__ . '/includes/functions.php';

[$mes, $anio] = parseMesAnio(
    isset($_GET['mes']) ? (int) $_GET['mes'] : null,
    isset($_GET['anio']) ? (int) $_GET['anio'] : null
);

ensureMesListo($mes, $anio);

$cuentasPorDia = getCuentasCalendario($mes, $anio);
$paraPagar = getCuentasParaPagar($mes, $anio);

$firstDay = mktime(0, 0, 0, $mes, 1, $anio);
$daysInMonth = (int) date('t', $firstDay);
$startWeekday = (int) date('N', $firstDay);
$today = (int) date('j');
$currentMonth = (int) date('n');
$currentYear = (int) date('Y');

$estadoLabel = [
    'unassigned' => 'Sin valor',
    'pending' => 'Pendiente',
    'overdue' => 'Vencida',
    'paid' => 'Pagada',
];

$pageTitle = 'Calendario';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <h1 class="h3 mb-0"><?= monthName($mes) ?> <?= $anio ?></h1>
    <div class="btn-group">
        <a href="<?= urlMes('calendario.php', $mes - 1, $anio) ?>" class="btn btn-outline-secondary">&larr;</a>
        <a href="<?= urlMes('calendario.php', (int) date('n'), (int) date('Y')) ?>" class="btn btn-outline-secondary">Hoy</a>
        <a href="<?= urlMes('calendario.php', $mes + 1, $anio) ?>" class="btn btn-outline-secondary">&rarr;</a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-9 min-w-0">
        <div class="card shadow-sm calendar-card">
            <div class="card-body calendar-card-body">
                <div class="calendar-grid">
                    <div class="calendar-weekday">Lun</div>
                    <div class="calendar-weekday">Mar</div>
                    <div class="calendar-weekday">Mié</div>
                    <div class="calendar-weekday">Jue</div>
                    <div class="calendar-weekday">Vie</div>
                    <div class="calendar-weekday">Sáb</div>
                    <div class="calendar-weekday">Dom</div>

                    <?php for ($i = 1; $i < $startWeekday; $i++): ?>
                        <div class="calendar-day empty"></div>
                    <?php endfor; ?>

                    <?php for ($day = 1; $day <= $daysInMonth; $day++):
                        $isToday = ($day === $today && $mes === $currentMonth && $anio === $currentYear);
                        $dayCuentas = $cuentasPorDia[$day] ?? [];
                        $totalDia = array_sum(array_map(fn($c) => cuentaTieneValor($c) ? (float) $c['monto'] : 0, $dayCuentas));
                        $dayVisual = 'normal';
                        foreach ($dayCuentas as $c) {
                            $v = cuentaEstadoVisual($c);
                            if ($v === 'overdue') { $dayVisual = 'overdue'; break; }
                            if ($v === 'pending' && $dayVisual !== 'overdue') { $dayVisual = 'pending'; }
                            if ($v === 'unassigned' && !in_array($dayVisual, ['overdue', 'pending'], true)) {
                                $dayVisual = 'unassigned';
                            }
                            if ($v === 'paid' && $dayVisual === 'normal') { $dayVisual = 'paid'; }
                        }
                        $dayModalId = 'day-detail-' . $day;
                    ?>
                        <div class="calendar-day <?= $isToday ? 'today' : '' ?> day-<?= $dayVisual ?>">
                            <?php if (!empty($dayCuentas)): ?>
                                <button type="button"
                                        class="btn-day-hit"
                                        data-bs-toggle="modal"
                                        data-bs-target="#dayInfoModal"
                                        data-day-title="Día <?= $day ?> · <?= monthName($mes) ?>"
                                        data-day-source="#<?= $dayModalId ?>"
                                        aria-label="Ver cuentas del día <?= $day ?>"></button>
                            <?php endif; ?>
                            <div class="calendar-day-header">
                                <a href="pago-fijo-form.php?dia=<?= $day ?>&mes=<?= $mes ?>&anio=<?= $anio ?>"
                                   class="day-number"
                                   title="Agregar cuenta el día <?= $day ?>">
                                    <?= $day ?>
                                </a>
                                <?php if ($totalDia > 0): ?>
                                    <span class="day-total"><?= formatMoneyShort($totalDia) ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="calendar-events calendar-events-desktop">
                                <?php foreach ($dayCuentas as $cuenta):
                                    $visual = cuentaEstadoVisual($cuenta);
                                    $link = !cuentaValorAsignado($cuenta) || ($visual === 'pending' || $visual === 'overdue')
                                        ? 'asignar-valor.php?id=' . (int) $cuenta['id'] . '&mes=' . $mes . '&anio=' . $anio
                                        : 'cuenta-form.php?id=' . (int) $cuenta['id'] . '&mes=' . $mes . '&anio=' . $anio;
                                ?>
                                    <a href="<?= $link ?>"
                                       class="calendar-event event-<?= $visual ?>"
                                       title="<?= h($cuenta['nombre']) ?> — <?= formatMoneyOrPending($cuenta) ?>">
                                        <span class="event-name"><?= h($cuenta['nombre']) ?></span>
                                        <span class="event-amount"><?= formatMoneyOrPending($cuenta) ?></span>
                                    </a>
                                <?php endforeach; ?>
                                <a href="pago-fijo-form.php?dia=<?= $day ?>&mes=<?= $mes ?>&anio=<?= $anio ?>"
                                   class="calendar-add-event"
                                   title="Agregar cuenta este día">+</a>
                            </div>

                            <?php if (!empty($dayCuentas)): ?>
                                <div class="d-none" id="<?= $dayModalId ?>">
                                    <ul class="list-group list-group-flush day-detail-list">
                                        <?php foreach ($dayCuentas as $cuenta):
                                            $visual = cuentaEstadoVisual($cuenta);
                                            $link = !cuentaValorAsignado($cuenta) || ($visual === 'pending' || $visual === 'overdue')
                                                ? 'asignar-valor.php?id=' . (int) $cuenta['id'] . '&mes=' . $mes . '&anio=' . $anio
                                                : 'cuenta-form.php?id=' . (int) $cuenta['id'] . '&mes=' . $mes . '&anio=' . $anio;
                                            $badgeClass = match ($visual) {
                                                'paid' => 'text-bg-success',
                                                'overdue' => 'text-bg-danger',
                                                'pending' => 'text-bg-warning text-dark',
                                                default => 'text-bg-secondary',
                                            };
                                        ?>
                                            <li class="list-group-item px-0">
                                                <div class="d-flex justify-content-between align-items-start gap-2">
                                                    <div>
                                                        <strong><?= h($cuenta['nombre']) ?></strong>
                                                        <div class="small text-muted"><?= formatMoneyOrPending($cuenta) ?></div>
                                                    </div>
                                                    <span class="badge <?= $badgeClass ?>"><?= h($estadoLabel[$visual] ?? $visual) ?></span>
                                                </div>
                                                <a href="<?= $link ?>" class="btn btn-sm btn-primary mt-2">Abrir</a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <a href="pago-fijo-form.php?dia=<?= $day ?>&mes=<?= $mes ?>&anio=<?= $anio ?>" class="btn btn-outline-secondary w-100 mt-2">
                                        + Agregar cuenta este día
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>

    <aside class="col-lg-3">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h2 class="h6 mb-0">Por pagar</h2>
                <?php if (!empty($paraPagar)): ?>
                    <a href="<?= urlMes('cuentas.php', $mes, $anio, ['filtro' => 'pendientes']) ?>" class="small">Ver lista</a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($paraPagar)): ?>
                    <p class="text-muted small mb-0">Nada pendiente este mes.</p>
                <?php else: ?>
                    <ul class="sidebar-payments">
                        <?php foreach ($paraPagar as $cuenta): ?>
                            <li class="sidebar-payment-item">
                                <a href="asignar-valor.php?id=<?= (int) $cuenta['id'] ?>&mes=<?= $mes ?>&anio=<?= $anio ?>" class="sidebar-link">
                                    <strong><?= h($cuenta['nombre']) ?></strong>
                                    <span class="text-muted d-block small">
                                        Día <?= (int) date('j', strtotime($cuenta['fecha_vencimiento'])) ?>
                                        · <?= formatMoney((float) $cuenta['monto']) ?>
                                    </span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="<?= urlMes('cuentas.php', $mes, $anio, ['filtro' => 'pendientes']) ?>" class="btn btn-primary w-100">Ir a la lista</a>
                <?php endif; ?>
            </div>
        </div>
    </aside>
</div>

<div class="modal fade" id="dayInfoModal" tabindex="-1" aria-labelledby="dayInfoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="dayInfoModalLabel">Detalle del día</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="dayInfoModalBody"></div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
