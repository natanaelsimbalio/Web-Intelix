<?php
// Devuelve el catálogo público con los precios ya convertidos a ARS
// (los productos en USD se convierten a la cotización oficial cacheada).
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/config.php';
require __DIR__ . '/lib/cotizacion.php';

$raw = json_decode(file_get_contents(__DIR__ . '/data/productos.json'), true);
$activos = array_values(array_filter($raw['productos'] ?? [], function ($p) {
    return !empty($p['activo']);
}));

$cotizacion = obtener_cotizacion_oficial(FALLBACK_USD_ARS);

foreach ($activos as &$p) {
    $moneda = $p['moneda'] ?? 'ARS';
    if ($moneda === 'USD') {
        $p['precio_ars'] = (int)round($p['precio'] * $cotizacion['valor']);
        $p['precio_usd'] = $p['precio'];
    } else {
        $p['precio_ars'] = (int)round($p['precio']);
    }
}
unset($p);

echo json_encode([
    'productos' => $activos,
    'cotizacion_oficial' => $cotizacion['valor'],
    'cotizacion_fecha' => $cotizacion['fecha'],
], JSON_UNESCAPED_UNICODE);
