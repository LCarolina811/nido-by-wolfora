<?php

declare(strict_types=1);

/**
 * Calcula la quincena según el día del mes.
 * Día 1–14  → 'primera'
 * Día 15–31 → 'segunda'
 */
function calcular_quincena(string $fecha): string
{
    $dia = (int) date('j', strtotime($fecha));
    return $dia <= 14 ? 'primera' : 'segunda';
}

/**
 * Retorna el ID del período activo actual.
 */
function periodo_activo_id(): int
{
    $stmt = db()->query('SELECT id FROM periodos WHERE activo = 1 LIMIT 1');
    $row  = $stmt->fetch();
    return $row ? (int) $row['id'] : 0;
}

/**
 * Retorna el nombre del mes en español.
 */
function nombre_mes(int $mes): string
{
    $meses = [
        1  => 'Enero',    2  => 'Febrero',   3  => 'Marzo',
        4  => 'Abril',    5  => 'Mayo',       6  => 'Junio',
        7  => 'Julio',    8  => 'Agosto',     9  => 'Septiembre',
        10 => 'Octubre',  11 => 'Noviembre',  12 => 'Diciembre',
    ];
    return $meses[$mes] ?? '';
}

/**
 * Formatea un valor decimal como moneda colombiana.
 * Ejemplo: 1500000 → "$ 1.500.000"
 */
function formatear_moneda(float $valor): string
{
    return '$ ' . number_format($valor, 0, ',', '.');
}

/**
 * Determina el color de alerta según la fecha de pago y estado.
 */
function color_vencimiento(string $fecha_pago, string $estado): string
{
    if ($estado === 'pagado') {
        return 'success';
    }

    $hoy        = new DateTimeImmutable('today');
    $vencimiento = new DateTimeImmutable($fecha_pago);
    $diff        = (int) $hoy->diff($vencimiento)->format('%r%a');

    if ($diff < 0)       return 'danger';   // vencido
    if ($diff <= 5)      return 'warning';  // próximo a vencer
    return 'neutral';                        // pendiente normal
}
