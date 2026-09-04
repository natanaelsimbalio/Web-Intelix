<?php
// Devuelve las opciones de envío disponibles.
header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents(__DIR__ . '/data/envios.json');
echo $raw;
