<?php
// Catálogo público de productos activos — endpoint versionado (v1).
// Ver /openapi.json para la política de versionado, deprecación y rate limit.
// Sin precios ni stock: la tienda funciona a consulta por WhatsApp.
require __DIR__ . '/../lib/ratelimit.php';

header('Content-Type: application/json; charset=utf-8');
header('X-API-Version: 1');
apply_rate_limit(30, 60);

function error_json($code, $status, $message, $hint) {
    http_response_code($code);
    echo json_encode([
        'error' => [
            'code' => $code,
            'status' => $status,
            'message' => $message,
            'hint' => $hint,
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = @file_get_contents(__DIR__ . '/../data/productos.json');
if ($raw === false) {
    error_json(500, 'internal_error', 'No se pudo leer el catálogo de productos.', 'Reintentá más tarde o contactá a contacto@intelix.com.ar.');
}

$data = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE || !isset($data['productos'])) {
    error_json(500, 'internal_error', 'El catálogo de productos tiene un formato inválido.', 'Reintentá más tarde o contactá a contacto@intelix.com.ar.');
}

$activos = array_values(array_filter($data['productos'], function ($p) {
    return !empty($p['activo']);
}));

$publico = array_map(function ($p) {
    return [
        'sku' => $p['sku'],
        'nombre' => $p['nombre'],
        'descripcion' => $p['descripcion'],
        'imagenes' => $p['imagenes'],
    ];
}, $activos);

echo json_encode(['productos' => $publico], JSON_UNESCAPED_UNICODE);
