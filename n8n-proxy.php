<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Accept, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$ALLOWED = ['gls-ping', 'gls-excel', 'gls-estados'];

$ep   = trim($_GET['ep']   ?? '');
$base = trim($_GET['base'] ?? '');

if (!in_array($ep, $ALLOWED, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Endpoint no permitido']);
    exit;
}

if (!filter_var($base, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'URL base inválida']);
    exit;
}

$host = parse_url($base, PHP_URL_HOST) ?? '';
if (!$host || preg_match('/^(localhost|127\.|10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)/i', $host)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'URL interna no permitida']);
    exit;
}

$url = rtrim($base, '/') . '/webhook/' . $ep;

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 120,
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 5,
]);

$body  = curl_exec($ch);
$code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err   = curl_error($ch);
curl_close($ch);

if ($err) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'No se pudo conectar con n8n: ' . $err]);
    exit;
}

http_response_code($code ?: 502);
echo $body ?: json_encode(['ok' => false, 'error' => 'Sin respuesta de n8n']);
