<?php
// POST /api/verify.php → doğrulama sonucunu verifyblind/verifyblind-php ile kontrol eder:
// enclave imzası (RSA-PSS SHA-256, salt 32), nonce'un tek seferlik tüketimi ve sorulan doğrulamalar.

// sentry-bootstrap loads .env (from outside docroot) into getenv()/$_ENV.
// It also pulls in vendor/autoload.php, which is where the VerifyBlind library comes from.
require_once __DIR__ . '/../sentry-bootstrap.php';
require_once __DIR__ . '/nonce-store.php';

use VerifyBlind\Cache\FileCache;
use VerifyBlind\Exception\AskedMismatchException;
use VerifyBlind\Exception\InvalidSignatureException;
use VerifyBlind\Exception\InvalidTokenException;
use VerifyBlind\Exception\KeyUnavailableException;
use VerifyBlind\Exception\NonceException;
use VerifyBlind\Exception\VerifyBlindException;
use VerifyBlind\Verifier;

header('Content-Type: application/json; charset=utf-8');

function vb_fail(int $status, string $error): void {
    http_response_code($status);
    echo json_encode(['error' => $error]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    vb_fail(405, 'Method Not Allowed');
}

$apiUrl = $_ENV['VERIFYBLIND_API_URL'] ?? getenv('VERIFYBLIND_API_URL') ?: 'https://api.verifyblind.com';

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body) || empty($body['token']) || !is_string($body['token'])) {
    vb_fail(400, 'token gerekli');
}

$verifier = new Verifier([
    'apiUrl' => $apiUrl,
    // Bu portal test partneri: demo kartla yapılan doğrulama da kabul edilir. Gerçek sitede kapalı kalır.
    'allowTestCards' => true,
    // Enclave anahtarı istekler arasında saklanır (yalnız açık anahtar, kimlik kodu değil).
    'cache' => new FileCache(),
]);

// Replay protection: the signed nonce must be one this portal generated; it is consumed exactly once and
// returns what WE asked at generate (never what the browser says it asked).
$asked = null;
$consume = function (string $nonce) use (&$asked): ?array {
    $asked = vb_nonce_consume($nonce);
    return $asked;
};

try {
    $result = $verifier->verify($body['token'], $consume);
} catch (InvalidTokenException $e) {
    vb_fail(400, 'Geçersiz token formatı');
} catch (InvalidSignatureException $e) {
    vb_fail(401, 'Geçersiz imza');
} catch (KeyUnavailableException $e) {
    // Anahtar alınamadı ya da okunamadı: bu bir imza reddi DEĞİL, altyapı sorunu. 401 dönmek
    // partner'ı "token bozuk" diye yanlış yöne sürükler.
    vb_fail(502, 'Enclave public key alınamadı');
} catch (NonceException $e) {
    vb_fail(401, 'Oturum süresi dolmuş veya zaten kullanılmış');
} catch (AskedMismatchException $e) {
    vb_fail(401, isset($asked['age']) ? 'Sorulan yaş koşulu eşleşmiyor' : 'Yaş sorulmadığı halde yaş sonucu geldi');
} catch (VerifyBlindException $e) {
    // Mesajlar kimlik kodu içermez; yine de istemciye yalnız sabit hata kodu dönülür.
    vb_fail($e->getHttpStatus(), $e->getErrorCode());
}

http_response_code(200);
echo json_encode(['success' => true, 'data' => $result->payload(), 'asked' => (object) $result->asked()]);
