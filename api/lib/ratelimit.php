<?php
// Shared rate limiter for the public API. Emits the IETF RateLimit header
// fields (draft-ietf-httpapi-ratelimit-headers) on every response, and
// returns 429 + Retry-After once a client goes over the window.
//
// Fixed-window counter, one file per (IP, window). File-based because this
// runs on shared PHP hosting with no guaranteed Redis/Memcached — good
// enough for a public read-only catalog endpoint, not meant for high QPS.

function apply_rate_limit(int $limit = 30, int $window = 60): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $dir = __DIR__ . '/../data/ratelimit';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }

    $windowStart = intdiv(time(), $window) * $window;
    $reset = ($windowStart + $window) - time();
    $file = $dir . '/' . md5($ip) . '_' . $windowStart . '.count';

    $count = 1;
    $fp = @fopen($file, 'c+');
    if ($fp !== false) {
        flock($fp, LOCK_EX);
        $existing = (int) fread($fp, 20);
        $count = $existing + 1;
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, (string) $count);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        // Occasional cleanup of stale window files so this directory
        // doesn't grow unbounded on a shared host with no cron access.
        if (random_int(1, 50) === 1) {
            foreach ((glob($dir . '/*.count') ?: []) as $old) {
                if (@filemtime($old) < time() - 3600) {
                    @unlink($old);
                }
            }
        }
    }
    // If the counter file can't be opened (permissions, disk), fail open:
    // don't block the API over a rate-limit bookkeeping error.

    $remaining = max(0, $limit - $count);
    header("RateLimit-Limit: $limit");
    header("RateLimit-Remaining: $remaining");
    header("RateLimit-Reset: $reset");
    header("RateLimit-Policy: $limit;w=$window");

    if ($count > $limit) {
        http_response_code(429);
        header('Retry-After: ' . $reset);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error' => [
                'code' => 429,
                'status' => 'rate_limited',
                'message' => "Límite de {$limit} solicitudes por {$window} segundos excedido.",
                'hint' => "Esperá {$reset}s y reintentá. Ver /openapi.json para la política de rate limiting.",
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
