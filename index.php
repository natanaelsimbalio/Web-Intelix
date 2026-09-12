<?php
// Content negotiation for the homepage: agents asking for Accept: text/markdown
// get the Markdown summary (index.md); everyone else gets the normal HTML.
// See acceptmarkdown.com — Vary: Accept must be sent on every response here
// so caches don't serve the wrong variant to the next requester.
header('Vary: Accept');

$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
$wantsMarkdown = stripos($accept, 'text/markdown') !== false && stripos($accept, 'text/html') === false;

if ($wantsMarkdown) {
    header('Content-Type: text/markdown; charset=utf-8');
    readfile(__DIR__ . '/index.md');
    exit;
}

header('Content-Type: text/html; charset=utf-8');
readfile(__DIR__ . '/index.html');
