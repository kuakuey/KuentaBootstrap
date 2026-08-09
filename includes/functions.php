<?php

require_once __DIR__ . '/../config/locale.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function formatMoney(float $amount): string
{
    // Ecuador usa dólar (USD). Formato: $1.234,56
    return '$' . number_format($amount, 2, ',', '.');
}

function formatMoneyShort(float $amount): string
{
    if (fmod($amount, 1.0) === 0.0) {
        return '$' . number_format($amount, 0, ',', '.');
    }
    return formatMoney($amount);
}

function parseMonto(string $raw): float
{
    $raw = trim($raw);
    if ($raw === '') {
        return 0;
    }

    // Quita símbolo $ y espacios; soporta 1.234,56 o 1234.56
    $raw = str_replace(['$', ' '], '', $raw);
    if (str_contains($raw, ',') && str_contains($raw, '.')) {
        $raw = str_replace('.', '', $raw);
        $raw = str_replace(',', '.', $raw);
    } elseif (str_contains($raw, ',')) {
        $raw = str_replace(',', '.', $raw);
    }

    return (float) preg_replace('/[^\d.]/', '', $raw);
}

function formatMoneyOrPending(array $cuenta): string
{
    if (!cuentaValorAsignado($cuenta)) {
        return 'Sin valor';
    }

    if ((float) $cuenta['monto'] === 0.0) {
        return 'Sin pago ($0)';
    }

    return formatMoneyShort((float) $cuenta['monto']);
}

function cuentaValorAsignado(array $cuenta): bool
{
    return !empty($cuenta['valor_asignado']);
}

function cuentaTieneValor(array $cuenta): bool
{
    return cuentaValorAsignado($cuenta) && (float) $cuenta['monto'] > 0;
}

function cuentaSinPago(array $cuenta): bool
{
    return cuentaValorAsignado($cuenta) && (float) $cuenta['monto'] === 0.0;
}

function isCuentaVencida(array $cuenta): bool
{
    if ($cuenta['estado'] === 'pagado') {
        return false;
    }

    $hoy = date('Y-m-d');
    return ($cuenta['fecha_vencimiento'] ?? '') < $hoy;
}

function cuentaEstadoVisual(array $cuenta): string
{
    if ($cuenta['estado'] === 'pagado' || cuentaSinPago($cuenta)) {
        return 'paid';
    }

    if (!cuentaValorAsignado($cuenta)) {
        return 'unassigned';
    }

    if (isCuentaVencida($cuenta)) {
        return 'overdue';
    }

    return 'pending';
}

function marcarCuentasPagadas(array $ids, ?string $fechaPago = null): int
{
    if (empty($ids)) {
        return 0;
    }

    $fechaPago = $fechaPago ?: date('Y-m-d');
    $ids = array_map('intval', $ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = getDB()->prepare("
        UPDATE cuentas
        SET estado = 'pagado', fecha_pago = ?
        WHERE usuario_id = ? AND id IN ($placeholders) AND estado = 'pendiente' AND monto > 0
    ");
    $stmt->execute(array_merge([$fechaPago, getUsuarioId()], $ids));

    return $stmt->rowCount();
}

/**
 * Elimina una fecha fija y todas las cuentas registradas en todos los meses.
 */
function eliminarPagoFijo(int $pagoFijoId): bool
{
    $usuarioId = getUsuarioId();
    $db = getDB();

    $stmt = $db->prepare('SELECT id FROM pagos_fijos WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$pagoFijoId, $usuarioId]);
    if (!$stmt->fetch()) {
        return false;
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare('DELETE FROM cuentas WHERE pago_fijo_id = ? AND usuario_id = ?');
        $stmt->execute([$pagoFijoId, $usuarioId]);
        $stmt = $db->prepare('DELETE FROM pagos_fijos WHERE id = ? AND usuario_id = ?');
        $stmt->execute([$pagoFijoId, $usuarioId]);
        $db->commit();
        return true;
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Elimina una cuenta del mes. Si pertenece a una fecha fija, elimina
 * la plantilla y todas las cuentas registradas en todos los meses.
 * Si es huérfana (sin fecha fija) pero hay más con el mismo nombre/día,
 * elimina toda esa serie residual.
 *
 * @return string 'serie' | 'unica' | 'nada'
 */
function eliminarCuenta(int $id): string
{
    $usuarioId = getUsuarioId();
    $db = getDB();

    $stmt = $db->prepare('SELECT id, nombre, pago_fijo_id, fecha_vencimiento FROM cuentas WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$id, $usuarioId]);
    $cuenta = $stmt->fetch();

    if (!$cuenta) {
        return 'nada';
    }

    $pagoFijoId = $cuenta['pago_fijo_id'] ? (int) $cuenta['pago_fijo_id'] : 0;

    if ($pagoFijoId > 0) {
        return eliminarPagoFijo($pagoFijoId) ? 'serie' : 'nada';
    }

    $dia = (int) date('j', strtotime($cuenta['fecha_vencimiento']));
    $borradas = eliminarSerieHuerfana($cuenta['nombre'], $dia);

    if ($borradas > 1) {
        return 'serie';
    }

    if ($borradas === 1) {
        return 'unica';
    }

    return 'nada';
}

/**
 * Series sueltas: cuentas sin fecha fija (o con enlace roto) que se
 * repiten en varios meses — quedaron al borrar solo el mes actual.
 */
function getSeriesHuerfanas(): array
{
    $stmt = getDB()->prepare("
        SELECT
            c.nombre,
            DAY(c.fecha_vencimiento) AS dia,
            COUNT(*) AS total,
            MIN(c.anio * 100 + c.mes) AS periodo_desde,
            MAX(c.anio * 100 + c.mes) AS periodo_hasta
        FROM cuentas c
        LEFT JOIN pagos_fijos p ON p.id = c.pago_fijo_id
        WHERE c.usuario_id = ?
          AND p.id IS NULL
        GROUP BY c.nombre, DAY(c.fecha_vencimiento)
        HAVING COUNT(*) >= 2
        ORDER BY c.nombre ASC, dia ASC
    ");
    $stmt->execute([getUsuarioId()]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $desde = (int) $row['periodo_desde'];
        $hasta = (int) $row['periodo_hasta'];
        $row['desde_mes'] = $desde % 100;
        $row['desde_anio'] = intdiv($desde, 100);
        $row['hasta_mes'] = $hasta % 100;
        $row['hasta_anio'] = intdiv($hasta, 100);
    }
    unset($row);

    return $rows;
}

function eliminarSerieHuerfana(string $nombre, int $dia): int
{
    $stmt = getDB()->prepare("
        DELETE c FROM cuentas c
        LEFT JOIN pagos_fijos p ON p.id = c.pago_fijo_id
        WHERE c.usuario_id = ?
          AND p.id IS NULL
          AND c.nombre = ?
          AND DAY(c.fecha_vencimiento) = ?
    ");
    $stmt->execute([getUsuarioId(), $nombre, $dia]);
    return $stmt->rowCount();
}

function eliminarTodasSeriesHuerfanas(): int
{
    $series = getSeriesHuerfanas();
    $total = 0;

    foreach ($series as $serie) {
        $total += eliminarSerieHuerfana($serie['nombre'], (int) $serie['dia']);
    }

    return $total;
}

function getPersonas(bool $soloActivos = true): array
{
    $sql = 'SELECT * FROM personas WHERE usuario_id = ?';
    if ($soloActivos) {
        $sql .= ' AND activo = 1';
    }
    $sql .= ' ORDER BY nombre ASC';
    $stmt = getDB()->prepare($sql);
    $stmt->execute([getUsuarioId()]);
    return $stmt->fetchAll();
}

function getPersona(int $id): ?array
{
    $stmt = getDB()->prepare('SELECT * FROM personas WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$id, getUsuarioId()]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function parsePersonaFiltro(?int $personaId): ?int
{
    if ($personaId === null || $personaId <= 0) {
        return null;
    }
    return getPersona($personaId) ? $personaId : null;
}

function sqlFiltroPersona(string $alias, ?int $personaId): string
{
    if ($personaId === null) {
        return '';
    }
    // Incluye la persona elegida y las cuentas compartidas (sin persona = para todos).
    return " AND ({$alias}.persona_id = " . (int) $personaId . " OR {$alias}.persona_id IS NULL)";
}

function getCuentasParaPagar(int $mes, int $anio, ?int $personaId = null): array
{
    $sql = "
        SELECT c.*, t.nombre AS tipo_pago_nombre, t.color AS tipo_pago_color,
               pe.nombre AS persona_nombre, pe.color AS persona_color
        FROM cuentas c
        LEFT JOIN tipos_pago t ON c.tipo_pago_id = t.id
        LEFT JOIN personas pe ON c.persona_id = pe.id
        WHERE c.usuario_id = ? AND c.mes = ? AND c.anio = ?
          AND c.estado = 'pendiente'
          AND c.valor_asignado = 1
          AND c.monto > 0
    ";
    $sql .= sqlFiltroPersona('c', $personaId);
    $sql .= ' ORDER BY c.fecha_vencimiento ASC, c.nombre ASC';
    $stmt = getDB()->prepare($sql);
    $stmt->execute([getUsuarioId(), $mes, $anio]);
    return $stmt->fetchAll();
}

function getCuentasSinValor(int $mes, int $anio): array
{
    $stmt = getDB()->prepare("
        SELECT c.*, t.nombre AS tipo_pago_nombre, t.color AS tipo_pago_color
        FROM cuentas c
        LEFT JOIN tipos_pago t ON c.tipo_pago_id = t.id
        WHERE c.usuario_id = ? AND c.mes = ? AND c.anio = ?
          AND c.estado = 'pendiente'
          AND c.valor_asignado = 0
        ORDER BY c.fecha_vencimiento ASC, c.nombre ASC
    ");
    $stmt->execute([getUsuarioId(), $mes, $anio]);
    return $stmt->fetchAll();
}

function formatDate(?string $date): string
{
    if (!$date) {
        return '—';
    }
    $months = [
        1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr',
        5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'ago',
        9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic',
    ];
    $ts = strtotime($date);
    return (int) date('j', $ts) . ' ' . $months[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

function monthName(int $month): string
{
    $names = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];
    return $names[$month] ?? '';
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function getTiposPago(bool $soloActivos = true): array
{
    $db = getDB();
    $sql = 'SELECT * FROM tipos_pago WHERE usuario_id = ?';
    $params = [getUsuarioId()];
    if ($soloActivos) {
        $sql .= ' AND activo = 1';
    }
    $sql .= ' ORDER BY nombre ASC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getTipoPago(int $id): ?array
{
    $stmt = getDB()->prepare('SELECT * FROM tipos_pago WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$id, getUsuarioId()]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function getCuentasMes(int $mes, int $anio, ?int $personaId = null): array
{
    $sql = "
        SELECT c.*, t.nombre AS tipo_pago_nombre, t.color AS tipo_pago_color,
               pe.nombre AS persona_nombre, pe.color AS persona_color
        FROM cuentas c
        LEFT JOIN tipos_pago t ON c.tipo_pago_id = t.id
        LEFT JOIN personas pe ON c.persona_id = pe.id
        WHERE c.usuario_id = ? AND c.mes = ? AND c.anio = ?
    ";
    $sql .= sqlFiltroPersona('c', $personaId);
    $sql .= ' ORDER BY c.fecha_vencimiento ASC, c.nombre ASC';
    $stmt = getDB()->prepare($sql);
    $stmt->execute([getUsuarioId(), $mes, $anio]);
    return $stmt->fetchAll();
}

function getCuenta(int $id): ?array
{
    $stmt = getDB()->prepare("
        SELECT c.*, t.nombre AS tipo_pago_nombre, t.color AS tipo_pago_color,
               pe.nombre AS persona_nombre, pe.color AS persona_color
        FROM cuentas c
        LEFT JOIN tipos_pago t ON c.tipo_pago_id = t.id
        LEFT JOIN personas pe ON c.persona_id = pe.id
        WHERE c.id = ? AND c.usuario_id = ?
    ");
    $stmt->execute([$id, getUsuarioId()]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function getCuentasCalendario(int $mes, int $anio, ?int $personaId = null): array
{
    $sql = "
        SELECT c.*, t.nombre AS tipo_pago_nombre, t.color AS tipo_pago_color,
               pe.nombre AS persona_nombre, pe.color AS persona_color
        FROM cuentas c
        LEFT JOIN tipos_pago t ON c.tipo_pago_id = t.id
        LEFT JOIN personas pe ON c.persona_id = pe.id
        WHERE c.usuario_id = ? AND c.mes = ? AND c.anio = ?
    ";
    $sql .= sqlFiltroPersona('c', $personaId);
    $sql .= ' ORDER BY c.fecha_vencimiento ASC';
    $stmt = getDB()->prepare($sql);
    $stmt->execute([getUsuarioId(), $mes, $anio]);
    $grouped = [];
    foreach ($stmt->fetchAll() as $cuenta) {
        $day = (int) date('j', strtotime($cuenta['fecha_vencimiento']));
        $grouped[$day][] = $cuenta;
    }
    return $grouped;
}

function resumenMes(int $mes, int $anio): array
{
    $stmt = getDB()->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN estado = 'pagado' THEN 1 ELSE 0 END) AS pagadas,
            SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) AS pendientes,
            COALESCE(SUM(CASE WHEN monto > 0 THEN monto ELSE 0 END), 0) AS monto_total,
            COALESCE(SUM(CASE WHEN estado = 'pagado' THEN monto ELSE 0 END), 0) AS monto_pagado,
            COALESCE(SUM(CASE WHEN estado = 'pendiente' AND monto > 0 THEN monto ELSE 0 END), 0) AS monto_pendiente,
            SUM(CASE WHEN estado = 'pendiente' AND valor_asignado = 0 THEN 1 ELSE 0 END) AS sin_valor
        FROM cuentas
        WHERE usuario_id = ? AND mes = ? AND anio = ?
    ");
    $stmt->execute([getUsuarioId(), $mes, $anio]);
    return $stmt->fetch();
}

function parseMesAnio(?int $mes = null, ?int $anio = null): array
{
    $mes = $mes ?? (int) date('n');
    $anio = $anio ?? (int) date('Y');

    if ($mes < 1) {
        $mes = 12;
        $anio--;
    } elseif ($mes > 12) {
        $mes = 1;
        $anio++;
    }

    return [$mes, $anio];
}

function urlMes(string $page, int $mes, int $anio, array $extra = []): string
{
    $params = array_merge(['mes' => $mes, 'anio' => $anio], $extra);
    return $page . '?' . http_build_query($params);
}

function diaPagoEnMes(int $diaPago, int $mes, int $anio): int
{
    $ultimoDia = (int) date('t', mktime(0, 0, 0, $mes, 1, $anio));
    return min($diaPago, $ultimoDia);
}

function fechaVencimientoFija(int $diaPago, int $mes, int $anio): string
{
    $dia = diaPagoEnMes($diaPago, $mes, $anio);
    return sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
}

function getPagosFijos(bool $soloActivos = true): array
{
    $sql = "
        SELECT p.*, t.nombre AS tipo_pago_nombre, t.color AS tipo_pago_color,
               pe.nombre AS persona_nombre, pe.color AS persona_color
        FROM pagos_fijos p
        LEFT JOIN tipos_pago t ON p.tipo_pago_id = t.id
        LEFT JOIN personas pe ON p.persona_id = pe.id
        WHERE p.usuario_id = ?
    ";
    if ($soloActivos) {
        $sql .= ' AND p.activo = 1';
    }
    $sql .= ' ORDER BY p.dia_pago ASC, p.nombre ASC';
    $stmt = getDB()->prepare($sql);
    $stmt->execute([getUsuarioId()]);
    return $stmt->fetchAll();
}

function getPagoFijo(int $id): ?array
{
    $stmt = getDB()->prepare("
        SELECT p.*, t.nombre AS tipo_pago_nombre, t.color AS tipo_pago_color,
               pe.nombre AS persona_nombre, pe.color AS persona_color
        FROM pagos_fijos p
        LEFT JOIN tipos_pago t ON p.tipo_pago_id = t.id
        LEFT JOIN personas pe ON p.persona_id = pe.id
        WHERE p.id = ? AND p.usuario_id = ?
    ");
    $stmt->execute([$id, getUsuarioId()]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function pagoMontoEsFijo(array $pago): bool
{
    return ($pago['tipo_monto'] ?? 'variable') === 'fijo';
}

function syncPagosFijosMes(int $mes, int $anio): int
{
    $db = getDB();
    $pagosFijos = getPagosFijos(true);
    $creados = 0;

    $check = $db->prepare('
        SELECT id, valor_asignado, estado FROM cuentas
        WHERE usuario_id = ? AND pago_fijo_id = ? AND mes = ? AND anio = ?
        LIMIT 1
    ');

    $insert = $db->prepare("
        INSERT INTO cuentas (
            usuario_id, nombre, monto, fecha_vencimiento, persona_id, tipo_pago_id, pago_fijo_id,
            valor_asignado, estado, fecha_pago, notas, mes, anio
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $fillFijo = $db->prepare("
        UPDATE cuentas
        SET monto = ?, valor_asignado = 1, estado = ?, fecha_pago = ?
        WHERE id = ? AND usuario_id = ? AND estado = 'pendiente' AND valor_asignado = 0
    ");

    foreach ($pagosFijos as $pago) {
        $esFijo = pagoMontoEsFijo($pago);
        $monto = $esFijo ? (float) $pago['monto'] : 0.0;
        $valorAsignado = $esFijo ? 1 : 0;
        $sinPago = $esFijo && $monto === 0.0;
        $estado = $sinPago ? 'pagado' : 'pendiente';
        $fechaPago = $sinPago ? date('Y-m-d') : null;

        $check->execute([getUsuarioId(), (int) $pago['id'], $mes, $anio]);
        $existente = $check->fetch();
        if ($existente) {
            if ($esFijo && empty($existente['valor_asignado']) && $existente['estado'] === 'pendiente') {
                $fillFijo->execute([$monto, $estado, $fechaPago, (int) $existente['id'], getUsuarioId()]);
            }
            continue;
        }

        $fecha = fechaVencimientoFija((int) $pago['dia_pago'], $mes, $anio);
        $insert->execute([
            getUsuarioId(),
            $pago['nombre'],
            $monto,
            $fecha,
            $pago['persona_id'] ?: null,
            $pago['tipo_pago_id'],
            $pago['id'],
            $valorAsignado,
            $estado,
            $fechaPago,
            $pago['notas'],
            $mes,
            $anio,
        ]);
        $creados++;
    }

    return $creados;
}

/**
 * Aplica el valor fijo a meses pendientes (no pagados) vinculados a la fecha de pago.
 */
function propagarMontoFijo(int $pagoFijoId, float $monto): void
{
    $sinPago = $monto === 0.0;
    $estado = $sinPago ? 'pagado' : 'pendiente';
    $fechaPago = $sinPago ? date('Y-m-d') : null;

    $stmt = getDB()->prepare("
        UPDATE cuentas
        SET monto = ?, valor_asignado = 1, estado = ?, fecha_pago = ?
        WHERE usuario_id = ?
          AND pago_fijo_id = ?
          AND estado = 'pendiente'
    ");
    $stmt->execute([$monto, $estado, $fechaPago, getUsuarioId(), $pagoFijoId]);
}

function propagarPersonaPagoFijo(int $pagoFijoId, ?int $personaId): void
{
    $stmt = getDB()->prepare('
        UPDATE cuentas
        SET persona_id = ?
        WHERE usuario_id = ? AND pago_fijo_id = ?
    ');
    $stmt->execute([$personaId, getUsuarioId(), $pagoFijoId]);
}

function ensureMesListo(int $mes, int $anio): void
{
    syncPagosFijosMes($mes, $anio);
}

function syncPagosFijosDesde(int $mesInicio, int $anioInicio, int $cantidadMeses = 36): int
{
    $total = 0;
    $mes = $mesInicio;
    $anio = $anioInicio;

    for ($i = 0; $i < $cantidadMeses; $i++) {
        $total += syncPagosFijosMes($mes, $anio);
        $mes++;
        if ($mes > 12) {
            $mes = 1;
            $anio++;
        }
    }

    return $total;
}

function avanzarMes(int $mes, int $anio): array
{
    $mes++;
    if ($mes > 12) {
        $mes = 1;
        $anio++;
    }
    return [$mes, $anio];
}

function marcarCuentaPagada(int $id, ?string $fechaPago = null): void
{
    $fechaPago = $fechaPago ?: date('Y-m-d');
    $stmt = getDB()->prepare("UPDATE cuentas SET estado = 'pagado', fecha_pago = ? WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$fechaPago, $id, getUsuarioId()]);
}

function getProximosPagosMes(int $mes, int $anio, int $limite = 10): array
{
    $cuentas = getCuentasMes($mes, $anio);
    $pendientes = array_values(array_filter(
        $cuentas,
        fn($c) => $c['estado'] === 'pendiente' && cuentaTieneValor($c)
    ));
    return array_slice($pendientes, 0, $limite);
}

function asignarValorCuenta(int $id, float $monto, ?int $tipoPagoId = null, ?string $notas = null): void
{
    $cuenta = getCuenta($id);
    if (!$cuenta) {
        throw new InvalidArgumentException('Cuenta no encontrada.');
    }

    if ($monto < 0) {
        throw new InvalidArgumentException('El valor no puede ser negativo.');
    }

    $sinPago = $monto === 0.0;
    $estado = $sinPago ? 'pagado' : 'pendiente';
    $fechaPago = $sinPago ? date('Y-m-d') : null;

    $stmt = getDB()->prepare('
        UPDATE cuentas
        SET monto = ?, valor_asignado = 1, tipo_pago_id = COALESCE(?, tipo_pago_id),
            notas = COALESCE(?, notas), estado = ?, fecha_pago = ?
        WHERE id = ? AND usuario_id = ?
    ');
    $stmt->execute([$monto, $tipoPagoId, $notas, $estado, $fechaPago, $id, getUsuarioId()]);
}

