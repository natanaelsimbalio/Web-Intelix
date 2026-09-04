<?php
// Mercado Pago llama a esta URL cuando cambia el estado de un pago (IPN).
// No hay panel de admin: cuando un pago se aprueba, marcamos el pedido y
// mandamos un mail a la tienda con el detalle.
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/lib/mercadopago.php';
require __DIR__ . '/lib/pedidos.php';
require __DIR__ . '/lib/smtp_mailer.php';

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
    $lineasHtml = implode('', array_map(
        fn($it) => '<tr><td>' . htmlspecialchars($it['cantidad'] . 'x ' . $it['nombre']) . '</td><td style="text-align:right">$'
            . number_format($it['subtotal'], 0, ',', '.') . '</td></tr>',
        $pedido['items']
    ));
    $totalFmt = '$' . number_format($pedido['total'], 0, ',', '.');
    $direccionTxt = $pedido['comprador']['direccion'] ?: '(retiro en local)';

    // --- Aviso interno: nuevo pedido pagado ---
    $cuerpoInterno = "<h2>Nuevo pedido pagado #{$orderId}</h2>"
        . '<p><b>Cliente:</b> ' . htmlspecialchars($pedido['comprador']['nombre']) . '<br>'
        . '<b>Teléfono:</b> ' . htmlspecialchars($pedido['comprador']['telefono']) . '<br>'
        . '<b>Email:</b> ' . htmlspecialchars($pedido['comprador']['email']) . '<br>'
        . '<b>Envío:</b> ' . htmlspecialchars($pedido['envio']['nombre']) . '<br>'
        . '<b>Dirección:</b> ' . htmlspecialchars($direccionTxt) . '</p>'
        . '<table cellpadding="6" style="border-collapse:collapse;width:100%">' . $lineasHtml . '</table>'
        . '<p><b>Total: ' . $totalFmt . '</b></p>';

    try {
        smtp_enviar_mail(SHOP_NOTIFY_EMAIL, 'Intelix', "Pedido pagado #{$orderId} — Tienda Intelix", $cuerpoInterno);
    } catch (Throwable $e) {
        pedido_actualizar((string)$orderId, ['error_mail_interno' => $e->getMessage()]);
    }

    // --- Confirmación al cliente ---
    $cuerpoCliente = '<h2>¡Gracias por tu compra en Intelix!</h2>'
        . '<p>Confirmamos tu pago del pedido <b>#' . htmlspecialchars($orderId) . '</b>.</p>'
        . '<table cellpadding="6" style="border-collapse:collapse;width:100%">' . $lineasHtml . '</table>'
        . '<p><b>Total: ' . $totalFmt . '</b></p>'
        . '<p><b>Envío:</b> ' . htmlspecialchars($pedido['envio']['nombre']) . '<br>'
        . '<b>Dirección:</b> ' . htmlspecialchars($direccionTxt) . '</p>'
        . '<p>Cualquier consulta, respondé este mail o escribinos a ' . htmlspecialchars(SHOP_NOTIFY_EMAIL) . '.</p>';

    try {
        smtp_enviar_mail(
            $pedido['comprador']['email'],
            $pedido['comprador']['nombre'],
            "Confirmamos tu pedido #{$orderId} — Tienda Intelix",
            $cuerpoCliente
        );
    } catch (Throwable $e) {
        pedido_actualizar((string)$orderId, ['error_mail_cliente' => $e->getMessage()]);
    }
}
