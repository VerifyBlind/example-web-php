<?php
// POST /api/verify.php → Enclave RSA-PSS SHA-256 imzasını doğrular (openssl CLI)

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

$pemKey = "-----BEGIN PUBLIC KEY-----\n"
    . chunk_split($pubKeyBase64, 64, "\n")
    . "-----END PUBLIC KEY-----";

// Temp dosyalar: payload, imza, public key
$tmpDir  = sys_get_temp_dir();
$keyFile = tempnam($tmpDir, 'vb_key_');
$sigFile = tempnam($tmpDir, 'vb_sig_');
$datFile = tempnam($tmpDir, 'vb_dat_');

try {
    file_put_contents($keyFile, $pemKey);
    file_put_contents($sigFile, $sigBytes);
    file_put_contents($datFile, $payload);

    // RSA-PSS SHA-256, MGF1-SHA-256, saltLength=32
    $cmd = sprintf(
        'openssl dgst -sha256 -sigopt rsa_padding_mode:pss -sigopt rsa_pss_saltlen:32 -sigopt rsa_mgf1_md:sha256 -verify %s -signature %s %s 2>&1',
        escapeshellarg($keyFile),
        escapeshellarg($sigFile),
        escapeshellarg($datFile)
    );

    $output = shell_exec($cmd);
    $isValid = (trim($output) === 'Verified OK');
} finally {
    @unlink($keyFile);
    @unlink($sigFile);
    @unlink($datFile);
}

if (!$isValid) {
    http_response_code(401);
    echo json_encode(['error' => 'Geçersiz imza']);
    exit;
}

$data = json_decode($payload, true);
http_response_code(200);
echo json_encode(['success' => true, 'data' => $data]);
