<?php

/**
 * Configuración regional — Ecuador
 */
define('APP_PAIS', 'Ecuador');
define('APP_MONEDA', 'USD');
define('APP_ZONA_HORARIA', 'America/Guayaquil');

date_default_timezone_set(APP_ZONA_HORARIA);

function tiposPagoDefault(): array
{
    return [
        ['Efectivo', '#22c55e'],
        ['Transferencia', '#3b82f6'],
        ['Tarjeta débito', '#8b5cf6'],
        ['Tarjeta crédito', '#f59e0b'],
        ['Deuna', '#ec4899'],
    ];
}
