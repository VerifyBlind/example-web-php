<?php
// POST /api/generate.php → /api/pop/generate proxy

// sentry-bootstrap loads .env (from outside docroot) into getenv()/$_ENV.
require_once __DIR__ . '/../sentry-bootstrap.php';
require_once __DIR__ . '/nonce-store.php';

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
        // Tarayıcının dilini ilet → VerifyBlind hata mesajlarını tr/en lokalize etsin.
        'Accept-Language: ' . ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'tr'),
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

// On success, remember the nonce so verify.php can bind & one-time-consume it.
if ($httpCode === 200) {
    $decoded = json_decode($response, true);
    if (is_array($decoded) && !empty($decoded['nonce']) && is_string($decoded['nonce'])) {
        // MUST cover the full QR scan window (relay QR lifetime ~15 min; SDK keeps it scannable that
        // long via 14-min poll + auto-regen + 1-min grace). A shorter TTL 401s late-but-valid scans.
        vb_nonce_put($decoded['nonce'], 960); // 16 min = relay QR lifetime (900s) + 60s round-trip buffer
    }
}

http_response_code($httpCode);
echo $response;
