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

// ── What to ask is decided by the SERVER ─────────────────────────────────────────────
// The enclave signs `validations.age: true|false`, i.e. the answer to the condition it was ASKED.
// The browser body can be edited in DevTools: if this proxy forwarded the browser's `validations`,
// a visitor could ask "1+" instead of "18+" and receive a genuinely signed `age: true`.
//
// A REAL SITE sets validations from its own server configuration and ignores the browser's, e.g.:
//     $asked = ['age' => '18+', 'user_id' => true];
//
// This demo lets the visitor tick what to verify, so it accepts the browser's choice ONLY from the
// allow-list below. Whatever was asked is stored with the nonce; verify.php reads the signed result
// against that STORED condition, never against what the browser claims.
const VB_ALLOWED_AGE_CONDITIONS = ['18+'];

$in = json_decode(file_get_contents('php://input')); // objects stay objects ({} is not turned into [])
if (!is_object($in)) {
    http_response_code(400);
    echo json_encode(['error' => 'Geçersiz istek gövdesi']);
    exit;
}

$asked   = [];
$allowed = true;
$requested = $in->validations ?? new stdClass();
if (!is_object($requested)) {
    $allowed = false;
} else {
    foreach (get_object_vars($requested) as $key => $value) {
        if ($key === 'age' && is_string($value) && in_array($value, VB_ALLOWED_AGE_CONDITIONS, true)) {
            $asked['age'] = $value;
        } elseif ($key === 'user_id' && $value === true) {
            $asked['user_id'] = true;
        } else {
            $allowed = false;
            break;
        }
    }
}
if (!$allowed) {
    http_response_code(400);
    echo json_encode(['error' => 'Bu demoda yalnız 18+ ve user_id sorulabilir']);
    exit;
}

// Keep public_key, sdk_version and additional_data (the mobile SDKs call it custom_data) from the
// client; validations come from the server (see above). The widget has no bot protection: a real
// site protects this endpoint itself (session, rate limit, own bot check).
$out = new stdClass();
foreach (['public_key', 'sdk_version', 'additional_data', 'custom_data'] as $field) {
    if (property_exists($in, $field)) $out->$field = $in->$field;
}
$out->validations = (object) $asked;

$ch = curl_init("$apiUrl/api/pop/generate");
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($out),
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

// On success, remember the nonce TOGETHER WITH what was asked, so verify.php can one-time-consume it
// and read the signed result against the asked condition.
if ($httpCode === 200) {
    $decoded = json_decode($response, true);
    if (is_array($decoded) && !empty($decoded['nonce']) && is_string($decoded['nonce'])) {
        // MUST cover the full QR scan window (relay QR lifetime ~15 min; SDK keeps it scannable that
        // long via 14-min poll + auto-regen + 1-min grace). A shorter TTL 401s late-but-valid scans.
        vb_nonce_put($decoded['nonce'], 960, $asked); // 16 min = relay QR lifetime (900s) + 60s round-trip buffer
    }
}

http_response_code($httpCode);
echo $response;
