<?php
/**
 * GLS Dashboard — n8n Proxy
 * Bypasses browser CORS by proxying n8n webhook calls server-side.
 */
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

$url = isset($_GET['url']) ? trim($_GET['url']) : '';

if (!$url || !preg_match('#^https?://#i', $url)) {
    http_response_code(400);
    echo json_encode(['error' => 'URL inválida']);
    exit;
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    CURLOPT_SSL_VERIFYPEER => false,
]);

$body     = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error    = curl_error($ch);
curl_close($ch);

if ($error) {
    http_response_code(502);
    echo json_encode(['error' => 'cURL: ' . $error]);
    exit;
}

http_response_code($httpCode ?: 502);
echo $body;
