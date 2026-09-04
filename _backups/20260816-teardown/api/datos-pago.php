<?php
// Expone SOLO el alias/CVU/titular (dato público para transferencias), nunca el
// access token — ese se queda adentro de config.php.
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/config.php';

echo json_encode(['alias' => MP_ALIAS, 'cvu' => MP_CVU, 'titular' => MP_TITULAR], JSON_UNESCAPED_UNICODE);
