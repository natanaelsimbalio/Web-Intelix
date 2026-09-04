<?php
// Persistencia simple de pedidos en un archivo JSON por pedido dentro de
// api/orders/ (bloqueado a acceso web por .htaccess). Sin base de datos:
// alcanza para el volumen de una tienda chica y es fácil de inspeccionar
// a mano por FTP/File Manager si hace falta.

define('ORDERS_DIR', __DIR__ . '/../orders');

function pedido_generar_id(): string {
    return date('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
}

function pedido_guardar(string $orderId, array $pedido): void {
    $path = ORDERS_DIR . '/' . preg_replace('/[^a-zA-Z0-9\-]/', '', $orderId) . '.json';
    file_put_contents($path, json_encode($pedido, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function pedido_leer(string $orderId): ?array {
    $path = ORDERS_DIR . '/' . preg_replace('/[^a-zA-Z0-9\-]/', '', $orderId) . '.json';
    if (!file_exists($path)) return null;
    $raw = file_get_contents($path);
    return json_decode($raw, true);
}

function pedido_actualizar(string $orderId, array $cambios): void {
    $pedido = pedido_leer($orderId);
    if ($pedido === null) return;
    pedido_guardar($orderId, array_merge($pedido, $cambios));
}
