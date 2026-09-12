<?php
// Alias sin versionar: siempre apunta a la última versión estable de la API
// (hoy v1). Los agentes que quieran fijar una versión concreta deben usar
// /api/v1/productos.php directamente — ver /openapi.json.
header('X-Api-Alias-Of: /api/v1/productos.php');
require __DIR__ . '/v1/productos.php';
