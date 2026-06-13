<?php
// POST /api/generate.php → /api/pop/generate proxy

require_once __DIR__ . '/../sentry-bootstrap.php';

$dotenvPath = __DIR__ . '/../.env';
if (file_exists($dotenvPath)) {
    $lines = file($dotenvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            [$key, $val] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($val);
        }
    }
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$apiKey = $_ENV['TEST_VERIFYBLIND_API_KEY'] ?? getenv('TEST_VERIFYBLIND_API_KEY') ?: '';
$apiUrl = $_ENV['VERIFYBLIND_API_URL'] ?? getenv('VERIFYBLIND_API_URL') ?: 'https://api.verifyblind.com';

if (empty($apiKey)) {
    http_response_code(500);
    echo json_encode(['error' => 'TEST_VERIFYBLIND_API_KEY yapılandırılmamış']);
    exit;
}

$rawBody = file_get_contents('php://input');

$ch = curl_init("$apiUrl/api/pop/generate");
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $rawBody,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-API-Key: ' . $apiKey,
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(502);
    echo json_encode(['error' => 'API bağlantı hatası: ' . $curlError]);
    exit;
}

http_response_code($httpCode);
echo $response;
