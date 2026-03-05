<?php
/**
 * GLS Dashboard — n8n Proxy
 * Bypasses browser CORS by proxying n8n webhook calls server-side.
 */
set_time_limit(0);                          // sin límite — n8n puede tardar 90+ s
ignore_user_abort(true);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Content-Type: application/json; charset=utf-8');

// Sin URL → diagnóstico: confirma que PHP funciona
$url = isset($_GET['url']) ? trim($_GET['url']) : '';
if (!$url) {
    echo json_encode([
        'status'  => 'ok',
        'php'     => PHP_VERSION,
        'curl'    => function_exists('curl_init'),
        'message' => 'Proxy PHP activo'
    ]);
    exit;
}

if (!preg_match('#^https?://#i', $url)) {
    http_response_code(400);
    echo json_encode(['error' => 'URL inválida']);
    exit;
}

// ── cURL (preferido) ──────────────────────────────────────────────────────────
if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 120,          // 2 minutos
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    $body     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err) {
        http_response_code(502);
        echo json_encode(['error' => 'cURL: ' . $err]);
        exit;
    }
    http_response_code($httpCode ?: 502);
    echo $body;
    exit;
}

// ── Fallback: file_get_contents ───────────────────────────────────────────────
$ctx = stream_context_create([
    'http' => [
        'method'          => 'GET',
        'header'          => "Accept: application/json\r\n",
        'timeout'         => 120,
        'ignore_errors'   => true,
    ],
    'ssl' => [
        'verify_peer'      => false,
        'verify_peer_name' => false,
    ],
]);
$body = @file_get_contents($url, false, $ctx);
if ($body === false) {
    http_response_code(502);
    echo json_encode(['error' => 'file_get_contents falló — cURL no disponible y stream falló']);
    exit;
}
$code = 200;
if (isset($http_response_header)) {
    foreach ($http_response_header as $h) {
        if (preg_match('#HTTP/\d+\.\d+\s+(\d+)#', $h, $m)) $code = (int)$m[1];
    }
}
http_response_code($code);
echo $body;
