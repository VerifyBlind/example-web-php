<?php
/**
 * VerifyBlind PHP örneği — kurulum ve imza doğrulama öz-denetimi.
 *
 *   php tests/self-check.php
 *
 * İki işi birden yapar:
 *   1) ORTAM  — kendi sunucunuzun (özellikle paylaşımlı hosting'in) bu örneği
 *      çalıştırmak için gerekenlere sahip olup olmadığını söyler.
 *   2) İMZA   — api/pss-verify.php'yi, enclave'in ürettiğiyle aynı formatta,
 *      gerçek bir RSA-PSS imzasına karşı çalıştırır. Fixture'lar openssl ile
 *      üretilmiştir (tests/fixtures/), yani "kendi kendini onaylama" değildir.
 *
 * Çıkış kodu 0 = her şey yolunda. Sunucuya kurduktan sonra bir kez çalıştırın.
 */

require_once __DIR__ . '/../api/pss-verify.php';

$fixtures = __DIR__ . '/fixtures';
$pass = 0;
$fail = 0;

/** printf('%-46s') bayt sayar; Türkçe karakterler çok baytlı olduğu için elle hizalıyoruz. */
function vb_pad(string $s, int $width = 48): string
{
    return $s . str_repeat(' ', max(1, $width - mb_strlen($s)));
}

function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo '  ', $ok ? '[ OK ]' : '[FAIL]', '  ', vb_pad($name), $detail, "\n";
}

echo "\nVerifyBlind PHP örneği — öz-denetim\n";
echo str_repeat('=', 72), "\n";

// ---------------------------------------------------------------- 1) ORTAM
echo "\n1) ORTAM\n";

check('PHP >= 8.1', PHP_VERSION_ID >= 80100, PHP_VERSION);
check('ext-curl (VerifyBlind API çağrıları için)', extension_loaded('curl'));
check('ext-json', extension_loaded('json'));
check('phpseclib3 yüklü (composer install)', class_exists(\phpseclib3\Crypt\RSA::class));

$tmp = sys_get_temp_dir();
check('Geçici dizin yazılabilir (nonce deposu)', is_dir($tmp) && is_writable($tmp), $tmp);

// Bilgi amaçlı: bu örnek ARTIK shell'e ihtiyaç duymuyor. Kapalı olması sorun değil —
// eskiden openssl CLI'a shell_exec ile çıkıldığı için bu bir engeldi.
$shell = function_exists('shell_exec') ? 'açık' : 'kapalı (disable_functions)';
echo '  [bilgi] ', vb_pad('shell_exec', 46), "$shell — bu örnek için gerekmiyor\n";

// ------------------------------------------------------- 2) İMZA DOĞRULAMA
echo "\n2) İMZA DOĞRULAMA (RSA-PSS, SHA-256, MGF1-SHA-256, salt=32)\n";

$payload   = file_get_contents("$fixtures/payload.json");
$goodSig   = base64_decode(trim(file_get_contents("$fixtures/signature.b64")));
$salt16Sig = base64_decode(trim(file_get_contents("$fixtures/signature-salt16.b64")));
$pubKey    = trim(file_get_contents("$fixtures/enclave-pubkey.b64"));
$otherKey  = trim(file_get_contents("$fixtures/other-pubkey.b64"));

$verifier      = vb_enclave_verifier(vb_enclave_key_to_pem($pubKey));
$otherVerifier = vb_enclave_verifier(vb_enclave_key_to_pem($otherKey));

$tamperedSig = $goodSig;
$tamperedSig[100] = chr(ord($tamperedSig[100]) ^ 0x01);   // tek bit flip
$tamperedPayload = str_replace('18+', '21+', $payload);

check('Geçerli imza kabul edilir',
    vb_signature_is_valid($verifier, $payload, $goodSig) === true);
check('Bozulmuş imza reddedilir',
    vb_signature_is_valid($verifier, $payload, $tamperedSig) === false);
check('Oynanmış payload reddedilir',
    vb_signature_is_valid($verifier, $tamperedPayload, $goodSig) === false);
check('Yabancı anahtarla doğrulama reddedilir',
    vb_signature_is_valid($otherVerifier, $payload, $goodSig) === false);
check('Yanlış salt uzunluğu (16 != 32) reddedilir',
    vb_signature_is_valid($verifier, $payload, $salt16Sig) === false);
check('Boş imza reddedilir',
    vb_signature_is_valid($verifier, $payload, '') === false);

// Bozuk public key bir imza reddi değil, altyapı hatasıdır → exception beklenir.
$threw = false;
try {
    vb_enclave_verifier(vb_enclave_key_to_pem('bu-bir-anahtar-degil'));
} catch (\Throwable $e) {
    $threw = true;
}
check('Bozuk public key exception fırlatır (401 değil, 502)', $threw);

// ------------------------------------------------------------------- ÖZET
echo "\n", str_repeat('=', 72), "\n";
if ($fail === 0) {
    echo "TÜM KONTROLLER GEÇTİ ($pass/$pass) — kurulum bu örneği çalıştırabilir.\n\n";
    exit(0);
}
echo "BAŞARISIZ: $fail kontrol geçmedi ($pass geçti).\n";
echo "phpseclib3 eksikse: composer install\n\n";
exit(1);
