<?php
// POST /api/verify.php → Enclave RSA-PSS SHA-256 imzasını doğrular (phpseclib3)

// sentry-bootstrap loads .env (from outside docroot) into getenv()/$_ENV.
// It also pulls in vendor/autoload.php, which is where phpseclib3 comes from.
require_once __DIR__ . '/../sentry-bootstrap.php';
require_once __DIR__ . '/nonce-store.php';
// İmza doğrulaması — phpseclib3'ün neden gerektiği bu dosyada anlatılıyor.
// Aynı fonksiyonları tests/self-check.php de kullanır.
require_once __DIR__ . '/pss-verify.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$apiUrl = $_ENV['VERIFYBLIND_API_URL'] ?? getenv('VERIFYBLIND_API_URL') ?: 'https://api.verifyblind.com';

$rawBody = file_get_contents('php://input');
$body = json_decode($rawBody, true);

if (empty($body['token'])) {
    http_response_code(400);
    echo json_encode(['error' => 'token gerekli']);
    exit;
}

$signed = json_decode(base64_decode($body['token']), true);
if (!$signed || empty($signed['payload']) || empty($signed['signature'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Geçersiz token formatı']);
    exit;
}

$payload  = $signed['payload'];
$sigBytes = base64_decode($signed['signature']);

// Enclave public key'i al
$ch = curl_init("$apiUrl/api/public/enclave-key");
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
$pubKeyBase64 = trim(curl_exec($ch));
$keyHttpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($keyHttpCode !== 200 || empty($pubKeyBase64)) {
    http_response_code(502);
    echo json_encode(['error' => 'Enclave public key alınamadı']);
    exit;
}

// Enclave imza sözleşmesi: RSA-PSS, SHA-256, MGF1-SHA-256, saltLength=32
try {
    $verifier = vb_enclave_verifier(vb_enclave_key_to_pem($pubKeyBase64));
} catch (\Throwable $e) {
    // Key'in kendisi okunamıyor: bu bir imza reddi DEĞİL, altyapı sorunu. 401 dönmek
    // partner'ı "token bozuk" diye yanlış yöne sürükler.
    http_response_code(502);
    echo json_encode(['error' => 'Enclave public key okunamadı']);
    exit;
}

$isValid = vb_signature_is_valid($verifier, $payload, $sigBytes);

if (!$isValid) {
    http_response_code(401);
    echo json_encode(['error' => 'Geçersiz imza']);
    exit;
}

$data = json_decode($payload, true);

// Replay protection: bind the signed nonce to a session this portal generated and
// consume it exactly once.
$sessionNonce = is_array($data) ? ($data['nonce'] ?? '') : '';
if (!is_string($sessionNonce) || $sessionNonce === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Geçersiz oturum (nonce yok)']);
    exit;
}
if (!vb_nonce_consume($sessionNonce)) {
    http_response_code(401);
    echo json_encode(['error' => 'Oturum süresi dolmuş veya zaten kullanılmış']);
    exit;
}

http_response_code(200);
echo json_encode(['success' => true, 'data' => $data]);
