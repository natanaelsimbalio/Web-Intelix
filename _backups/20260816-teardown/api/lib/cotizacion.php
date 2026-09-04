<?php
// Cotización oficial del dólar (promedio compra/venta del BCRA vía dolarapi.com,
// gratuita y sin registro). Se cachea en un archivo para no depender de la API
// en cada visita/pago: si dolarapi está caída, se usa el último valor guardado
// aunque tenga unas horas; si ni siquiera hay caché, se usa el valor fijo de
// emergencia definido en config.php (FALLBACK_USD_ARS).

define('COTIZACION_CACHE_FILE', __DIR__ . '/../data/cotizacion.json');
define('COTIZACION_CACHE_TTL', 1800); // 30 minutos

function obtener_cotizacion_oficial(float $fallback): array {
    $cache = null;
    if (file_exists(COTIZACION_CACHE_FILE)) {
        $cache = json_decode(file_get_contents(COTIZACION_CACHE_FILE), true);
    }

    $fresca = $cache && (time() - ($cache['obtenido_en'] ?? 0)) < COTIZACION_CACHE_TTL;
    if ($fresca) {
        return ['valor' => (float)$cache['valor'], 'fuente' => 'cache', 'fecha' => $cache['fecha']];
    }

    $nueva = fetch_cotizacion_oficial();
    if ($nueva !== null) {
        $registro = ['valor' => $nueva, 'obtenido_en' => time(), 'fecha' => date('c')];
        file_put_contents(COTIZACION_CACHE_FILE, json_encode($registro));
        return ['valor' => $nueva, 'fuente' => 'dolarapi', 'fecha' => $registro['fecha']];
    }

    if ($cache && isset($cache['valor'])) {
        return ['valor' => (float)$cache['valor'], 'fuente' => 'cache_vieja', 'fecha' => $cache['fecha']];
    }

    return ['valor' => $fallback, 'fuente' => 'fallback_manual', 'fecha' => date('c')];
}

function fetch_cotizacion_oficial(): ?float {
    $ch = curl_init('https://dolarapi.com/v1/dolares/oficial');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 6,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    if ($raw === false) return null;

    $data = json_decode($raw, true);
    if (!isset($data['compra'], $data['venta'])) return null;

    // Promedio compra/venta = "cotización oficial promedio".
    return round((((float)$data['compra']) + ((float)$data['venta'])) / 2, 2);
}
