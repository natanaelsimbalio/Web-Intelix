<?php
// Mercado Pago llama a esta URL cuando cambia el estado de un pago (IPN).
// No hay panel de admin: cuando un pago se aprueba, marcamos el pedido y
// mandamos un mail a la tienda con el detalle.
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/lib/mercadopago.php';
require __DIR__ . '/lib/pedidos.php';

http_response_code(200); // Responder rápido y OK siempre, MP reintenta si no.

$paymentId = $_GET['data_id'] ?? $_GET['id'] ?? null;
$topic = $_GET['type'] ?? $_GET['topic'] ?? null;

if ($topic !== 'payment' || !$paymentId) {
    exit; // Ignoramos otros tipos de notificación (merchant_order, etc.)
}

try {
    $pago = mp_obtener_pago((string)$paymentId, MP_ACCESS_TOKEN);
} catch (Throwable $e) {
    exit;
}

$orderId = $pago['external_reference'] ?? null;
$estadoPago = $pago['status'] ?? null; // approved, pending, rejected, etc.

if (!$orderId) exit;

$pedido = pedido_leer((string)$orderId);
if ($pedido === null) exit;

// Evitar reprocesar/re-notificar si ya estaba en ese estado.
if (($pedido['estado'] ?? '') === $estadoPago) exit;

pedido_actualizar((string)$orderId, [
    'estado' => $estadoPago,
    'mp_payment_id' => $paymentId,
    'actualizado' => date('c'),
]);

if ($estadoPago === 'approved') {
    $lineas = array_map(
        fn($it) => "- {$it['cantidad']}x {$it['nombre']} (\$" . number_format($it['subtotal'], 0, ',', '.') . ')',
        $pedido['items']
    );

    $cuerpo = "Nuevo pedido pagado — #{$orderId}\n\n"
        . "Cliente: {$pedido['comprador']['nombre']}\n"
        . "Teléfono: {$pedido['comprador']['telefono']}\n"
        . "Email: {$pedido['comprador']['email']}\n"
        . "Envío: {$pedido['envio']['nombre']}\n"
        . "Dirección: " . ($pedido['comprador']['direccion'] ?: '(retiro)') . "\n\n"
        . "Productos:\n" . implode("\n", $lineas) . "\n\n"
        . "Total: \$" . number_format($pedido['total'], 0, ',', '.') . "\n";

    mail(SHOP_NOTIFY_EMAIL, "Pedido pagado #{$orderId} — Tienda Intelix", $cuerpo, 'From: tienda@intelix.com.ar');
}
