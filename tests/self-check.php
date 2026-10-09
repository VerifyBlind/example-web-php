<?php
/**
 * VerifyBlind PHP örneği — kurulum öz-denetimi.
 *
 *   php tests/self-check.php
 *
 * Kendi sunucunuzun (özellikle paylaşımlı hosting'in) bu örneği çalıştırmak için gerekenlere
 * sahip olup olmadığını söyler. İmza doğrulamasını verifyblind/verifyblind-php paketi yapar;
 * onun testleri paketin kendi deposundadır.
 *
 * Çıkış kodu 0 = her şey yolunda. Sunucuya kurduktan sonra bir kez çalıştırın.
 */

$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

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
check('VerifyBlind paketi yüklü (composer install)', class_exists(\VerifyBlind\Verifier::class));

$tmp = sys_get_temp_dir();
check('Geçici dizin yazılabilir (nonce + anahtar önbelleği)', is_dir($tmp) && is_writable($tmp), $tmp);

// Bilgi amaçlı: bu örnek ARTIK shell'e ihtiyaç duymuyor. Kapalı olması sorun değil —
// eskiden openssl CLI'a shell_exec ile çıkıldığı için bu bir engeldi.
$shell = function_exists('shell_exec') ? 'açık' : 'kapalı (disable_functions)';
echo '  [bilgi] ', vb_pad('shell_exec', 46), "$shell — bu örnek için gerekmiyor\n";

// ------------------------------------------------------------------- ÖZET
echo "\n", str_repeat('=', 72), "\n";
if ($fail === 0) {
    echo "TÜM KONTROLLER GEÇTİ ($pass/$pass) — kurulum bu örneği çalıştırabilir.\n\n";
    exit(0);
}
echo "BAŞARISIZ: $fail kontrol geçmedi ($pass geçti).\n";
echo "VerifyBlind paketi eksikse: composer install\n\n";
exit(1);
