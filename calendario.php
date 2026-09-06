<?php
require_once __DIR__ . '/includes/functions.php';

[$mes, $anio] = parseMesAnio(
    isset($_GET['mes']) ? (int) $_GET['mes'] : null,
    isset($_GET['anio']) ? (int) $_GET['anio'] : null
);

$personaId = getPersonaFiltroActivo();
$personas = getPersonas(true);

ensureMesListo($mes, $anio);

$cuentasPorDia = getCuentasCalendario($mes, $anio, $personaId);
$paraPagar = getCuentasParaPagar($mes, $anio, $personaId);

$firstDay = mktime(0, 0, 0, $mes, 1, $anio);
$daysInMonth = (int) date('t', $firstDay);
$startWeekday = (int) date('N', $firstDay);
$today = (int) date('j');
$currentMonth = (int) date('n');
$currentYear = (int) date('Y');

$pageTitle = 'Calendario';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <h1 class="h3 mb-0"><?= monthName($mes) ?> <?= $anio ?></h1>
    <div class="btn-group">
        <a href="<?= urlMes('calendario.php', $mes - 1, $anio) ?>" class="btn btn-outline-secondary">&larr;</a>
        <a href="<?= urlMes('calendario.php', (int) date('n'), (int) date('Y')) ?>" class="btn btn-outline-secondary">Hoy</a>
        <a href="<?= urlMes('calendario.php', $mes + 1, $anio) ?>" class="btn btn-outline-secondary">&rarr;</a>
    </div>
</div>

<div class="persona-filter mb-4">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="text-muted small me-1">Filtrar:</span>
        <a href="<?= urlMes('calendario.php', $mes, $anio, ['persona' => 0]) ?>"
           class="persona-chip <?= $personaId === null ? 'active' : '' ?>"
           title="Calendario completo, sin filtros">
            Todos
        </a>
        <?php foreach ($personas as $persona): ?>
            <a href="<?= urlMes('calendario.php', $mes, $anio, ['persona' => (int) $persona['id']]) ?>"
               class="persona-chip <?= $personaId === (int) $persona['id'] ? 'active' : '' ?>"
               style="--persona-color: <?= h($persona['color']) ?>">
                <?= h($persona['nombre']) ?>
            </a>
        <?php endforeach; ?>
        <a href="personas.php" class="btn btn-sm btn-outline-secondary ms-auto">Administrar personas</a>
    </div>
    <?php if (empty($personas)): ?>
        <p class="text-muted small mt-2 mb-0">
            Aún no hay personas. <a href="personas.php">Agrega Cristhian, Jessy u otras</a> para filtrar el calendario.
        </p>
    <?php endif; ?>
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
                        $addParams = 'dia=' . $day . '&mes=' . $mes . '&anio=' . $anio;
                    ?>
                        <div class="calendar-day <?= $isToday ? 'today' : '' ?> day-<?= $dayVisual ?>">
                            <div class="calendar-day-header">
                                <a href="pago-fijo-form.php?<?= $addParams ?>"
                                   class="day-number"
                                   title="Agregar cuenta el día <?= $day ?>">
                                    <?= $day ?>
                                </a>
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
                                       title="<?= h($cuenta['nombre']) ?><?= !empty($cuenta['persona_nombre']) ? ' · ' . h($cuenta['persona_nombre']) : '' ?>">
                                        <span class="event-name"><?= h($cuenta['nombre']) ?></span>
                                    </a>
                                <?php endforeach; ?>
                                <a href="pago-fijo-form.php?<?= $addParams ?>"
                                   class="calendar-add-event"
                                   title="Agregar cuenta este día">+</a>
                            </div>
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
                    <p class="text-muted small mb-0">Nada pendiente<?= $personaId ? ' para esta persona' : '' ?> este mes.</p>
                <?php else: ?>
                    <ul class="sidebar-payments">
                        <?php foreach ($paraPagar as $cuenta): ?>
                            <li class="sidebar-payment-item">
                                <a href="asignar-valor.php?id=<?= (int) $cuenta['id'] ?>&mes=<?= $mes ?>&anio=<?= $anio ?>" class="sidebar-link">
                                    <strong><?= h($cuenta['nombre']) ?></strong>
                                    <span class="text-muted d-block small">
                                        Día <?= (int) date('j', strtotime($cuenta['fecha_vencimiento'])) ?>
                                        · <?= formatMoney((float) $cuenta['monto']) ?>
                                        <?php if (!empty($cuenta['persona_nombre']) && $personaId === null): ?>
                                            · <?= h($cuenta['persona_nombre']) ?>
                                        <?php endif; ?>
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

<?php require __DIR__ . '/includes/footer.php'; ?>
