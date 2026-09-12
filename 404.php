<?php
// Central 404/410 handler, wired via ErrorDocument in .htaccess.
// - /api/* misses get a structured JSON error (agents can't parse HTML error pages).
// - Agents asking for Accept: text/markdown get a short Markdown 404 with pointers.
// - Everyone else gets the normal styled 404.html.
http_response_code(404);
header('Vary: Accept');

$path = $_SERVER['REDIRECT_URL'] ?? parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$accept = $_SERVER['HTTP_ACCEPT'] ?? '';

if (strncmp($path, '/api/', 5) === 0) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => [
            'code' => 404,
            'status' => 'not_found',
            'message' => "No existe un endpoint en {$path}.",
            'hint' => 'Consultá /openapi.json para ver los endpoints disponibles.',
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$wantsMarkdown = stripos($accept, 'text/markdown') !== false && stripos($accept, 'text/html') === false;

if ($wantsMarkdown) {
    header('Content-Type: text/markdown; charset=utf-8');
    $pathEscaped = str_replace(['`', '['], ["'", '('], $path);
    echo <<<MD
# 404 — Página no encontrada

No existe contenido en `{$pathEscaped}`.

- [Inicio](https://intelix.com.ar/index.md)
- [Sitemap](https://intelix.com.ar/sitemap.xml)
- [llms.txt](https://intelix.com.ar/llms.txt)
- [Documentación de la API](https://intelix.com.ar/openapi.json)
MD;
    exit;
}

header('Content-Type: text/html; charset=utf-8');
readfile(__DIR__ . '/404.html');
