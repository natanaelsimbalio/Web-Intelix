<?php
// Crea una preferencia de pago en Mercado Pago a partir del carrito que
// manda el navegador. Los precios NUNCA se toman del cliente: se
// recalculan acá desde productos.json para que nadie pueda pagar de menos
// editando el localStorage o la request.
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/config.php';
require __DIR__ . '/lib/mercadopago.php';
require __DIR__ . '/lib/pedidos.php';
require __DIR__ . '/lib/cotizacion.php';

function responder_error(string $mensaje, int $http = 400) {
    http_response_code($http);
    echo json_encode(['error' => $mensaje]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_error('Método no permitido', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    responder_error('Body inválido');
}

$itemsCarrito = $input['items'] ?? [];
$envioId = $input['envio_id'] ?? '';
$comprador = $input['comprador'] ?? [];

if (!is_array($itemsCarrito) || count($itemsCarrito) === 0) {
    responder_error('El carrito está vacío');
}

$nombre = trim((string)($comprador['nombre'] ?? ''));
$telefono = trim((string)($comprador['telefono'] ?? ''));
$email = trim((string)($comprador['email'] ?? ''));
$direccion = trim((string)($comprador['direccion'] ?? ''));

if ($nombre === '' || $telefono === '' || $email === '') {
    responder_error('Faltan datos de contacto (nombre, teléfono, email)');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    responder_error('Email inválido');
}

// --- Catálogo real (fuente de verdad de precios) ---
$catalogoRaw = json_decode(file_get_contents(__DIR__ . '/data/productos.json'), true);
$catalogo = [];
foreach ($catalogoRaw['productos'] ?? [] as $p) {
    $catalogo[$p['sku']] = $p;
}

// --- Envíos disponibles ---
$enviosRaw = json_decode(file_get_contents(__DIR__ . '/data/envios.json'), true);
$envios = [];
foreach ($enviosRaw['opciones'] ?? [] as $e) {
    $envios[$e['id']] = $e;
}
if (!isset($envios[$envioId])) {
    responder_error('Método de envío inválido');
}
$envioElegido = $envios[$envioId];

if ($envioElegido['id'] !== 'retiro' && $direccion === '') {
    responder_error('Falta la dirección de envío');
}

// --- Cotización oficial vigente (para productos cargados en USD) ---
$cotizacion = obtener_cotizacion_oficial(FALLBACK_USD_ARS);

// --- Reconstruir items desde el catálogo, ignorando precio/nombre del cliente ---
$itemsMP = [];
$totalPedido = 0;
$detallePedido = [];

foreach ($itemsCarrito as $item) {
    $sku = (string)($item['sku'] ?? '');
    $cantidad = (int)($item['cantidad'] ?? 0);

    if (!isset($catalogo[$sku]) || !$catalogo[$sku]['activo']) {
        responder_error("Producto no disponible: $sku");
    }
    if ($cantidad < 1 || $cantidad > 50) {
        responder_error("Cantidad inválida para $sku");
    }
    if ($cantidad > ($catalogo[$sku]['stock'] ?? PHP_INT_MAX)) {
        responder_error("No queda stock suficiente de {$catalogo[$sku]['nombre']}");
    }

    $producto = $catalogo[$sku];
    $moneda = $producto['moneda'] ?? 'ARS';
    $precioUnitarioArs = $moneda === 'USD'
        ? (int)round($producto['precio'] * $cotizacion['valor'])
        : (int)round($producto['precio']);

    $subtotal = $precioUnitarioArs * $cantidad;
    $totalPedido += $subtotal;

    $itemsMP[] = [
        'title' => $producto['nombre'],
        'quantity' => $cantidad,
        'unit_price' => (float)$precioUnitarioArs,
        'currency_id' => 'ARS',
    ];

    $detallePedido[] = [
        'sku' => $sku,
        'nombre' => $producto['nombre'],
        'cantidad' => $cantidad,
        'precio_original' => $producto['precio'],
        'moneda_original' => $moneda,
        'precio_unitario_ars' => $precioUnitarioArs,
        'subtotal' => $subtotal,
    ];
}

// Envío como línea aparte
if ($envioElegido['precio'] > 0) {
    $itemsMP[] = [
        'title' => 'Envío: ' . $envioElegido['nombre'],
        'quantity' => 1,
        'unit_price' => (float)$envioElegido['precio'],
        'currency_id' => 'ARS',
    ];
}
$totalPedido += $envioElegido['precio'];

// --- Guardar el pedido ANTES de mandar a pagar, con estado pendiente ---
$orderId = pedido_generar_id();
pedido_guardar($orderId, [
    'order_id' => $orderId,
    'estado' => 'pendiente',
    'creado' => date('c'),
    'comprador' => [
        'nombre' => $nombre,
        'telefono' => $telefono,
        'email' => $email,
        'direccion' => $direccion,
    ],
    'envio' => $envioElegido,
    'items' => $detallePedido,
    'cotizacion_oficial_usada' => $cotizacion,
    'total' => $totalPedido,
    'mp_payment_id' => null,
]);

// --- Crear preferencia en Mercado Pago ---
$preferencia = [
    'items' => $itemsMP,
    'payer' => [
        'name' => $nombre,
        'email' => $email,
    ],
    'external_reference' => $orderId,
    'back_urls' => [
        'success' => SITE_URL . '/tienda/exito.html?pedido=' . $orderId,
        'failure' => SITE_URL . '/tienda/error.html?pedido=' . $orderId,
        'pending' => SITE_URL . '/tienda/pendiente.html?pedido=' . $orderId,
    ],
    'auto_return' => 'approved',
    'notification_url' => SITE_URL . '/api/webhook.php',
    'statement_descriptor' => 'INTELIX',
];

try {
    $resultado = mp_crear_preferencia($preferencia, MP_ACCESS_TOKEN);
} catch (Throwable $e) {
    pedido_actualizar($orderId, ['estado' => 'error_creacion', 'error' => $e->getMessage()]);
    responder_error('No se pudo iniciar el pago. Intentá de nuevo en unos minutos.', 502);
}

pedido_actualizar($orderId, ['mp_preference_id' => $resultado['id'] ?? null]);

echo json_encode([
    'order_id' => $orderId,
    'init_point' => $resultado['init_point'] ?? null,
]);
