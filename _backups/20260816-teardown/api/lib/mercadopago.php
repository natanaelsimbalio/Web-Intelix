<?php
// Wrapper mínimo sobre la API REST de Mercado Pago vía cURL.
// No usa el SDK oficial para no depender de Composer (no hay build step
// en este hosting): son dos llamadas HTTP simples.

function mp_request(string $method, string $path, ?array $body, string $accessToken): array {
    $ch = curl_init('https://api.mercadopago.com' . $path);
    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
    ];

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException('Error de conexión con Mercado Pago: ' . $err);
    }

    $data = json_decode($raw, true);

    return ['status' => $status, 'data' => $data];
}

function mp_crear_preferencia(array $preferencia, string $accessToken): array {
    $res = mp_request('POST', '/checkout/preferences', $preferencia, $accessToken);
    if ($res['status'] < 200 || $res['status'] >= 300) {
        throw new RuntimeException('Mercado Pago rechazó la preferencia (HTTP ' . $res['status'] . '): ' . json_encode($res['data']));
    }
    return $res['data'];
}

function mp_obtener_pago(string $paymentId, string $accessToken): array {
    $res = mp_request('GET', '/v1/payments/' . rawurlencode($paymentId), null, $accessToken);
    if ($res['status'] < 200 || $res['status'] >= 300) {
        throw new RuntimeException('No se pudo consultar el pago (HTTP ' . $res['status'] . ')');
    }
    return $res['data'];
}
