<?php
// Enclave imza doğrulaması — RSA-PSS, SHA-256, MGF1-SHA-256, saltLength=32.
//
// Neden phpseclib3 ve neden PHP'nin yerleşik openssl_verify()'ı DEĞİL:
// openssl_verify() yalnızca PKCS#1 v1.5 yapar — padding parametresi bile yoktur ve
// geçerli bir RSA-PSS imzası için 0 (geçersiz) döner. phpseclib3 saf PHP'dir; ne bir
// PHP eklentisi ne de shell_exec/openssl CLI gerektirir, dolayısıyla shell'in
// disable_functions ile kapatıldığı paylaşımlı hosting'lerde de çalışır.
//
// Bu dosya hem api/verify.php hem de tests/self-check.php tarafından kullanılır:
// test edilen kod ile çalışan kod aynı olsun diye.

require_once __DIR__ . '/../vendor/autoload.php';

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;

// phpseclib varsayılan olarak salt uzunluğunu imzadan "keşfeder" ve salt=16 ile
// imzalanmış bir veriyi de kabul eder. Enclave her zaman salt=32 ürettiği için katı
// uzunluk kontrolünü zorluyoruz — eski `openssl dgst -sigopt rsa_pss_saltlen:32`
// davranışı da katıydı, onu geri çekmiyoruz.
// Statik ayar → süreç genelinde geçerli, bir kez çağrılması yeterli.
RSA::disableSaltLengthDiscovery();

/** Enclave'in base64 SPKI public key'ini PEM'e çevirir. */
function vb_enclave_key_to_pem(string $spkiBase64): string
{
    return "-----BEGIN PUBLIC KEY-----\n"
        . chunk_split(trim($spkiBase64), 64, "\n")
        . "-----END PUBLIC KEY-----";
}

/**
 * PEM public key'i enclave imza sözleşmesine ayarlanmış bir doğrulayıcıya çevirir.
 * Key okunamazsa exception fırlatır — bu bir imza reddi DEĞİL, altyapı hatasıdır.
 */
function vb_enclave_verifier(string $pemKey)
{
    return PublicKeyLoader::loadPublicKey($pemKey)
        ->withPadding(RSA::SIGNATURE_PSS)
        ->withHash('sha256')
        ->withMGFHash('sha256')
        ->withSaltLength(32);
}

/**
 * İmzayı doğrular. Bozuk/eksik imza baytları phpseclib'de exception'a yol açabilir;
 * bunlar "doğrulanmadı" demektir, hata değil.
 */
function vb_signature_is_valid($verifier, string $payload, string $signatureBytes): bool
{
    try {
        return $verifier->verify($payload, $signatureBytes) === true;
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * PCR0 yetkilendirme imzasını doğrular — enclave'in halka açık kaynaktan yeniden üretilebilir
 * şekilde derlenmiş sürüm olduğunu RELAY'E GÜVENMEDEN teyit etmenin ilk adımı.
 *
 * İmza girdisi: PCR0'ın hex gösterimi (96 karakter), UTF-8 bayt olarak.
 * Anahtar: GET /api/public/developer-key → public_key (SPKI PEM).
 *
 * DİKKAT — padding bu dosyadaki diğer doğrulamadan FARKLI: enclave'in sonuç imzası RSA-PSS'tir
 * (bu yüzden phpseclib gerekiyordu), PCR0 imzası ise RSA-PKCS#1 v1.5'tir. PHP'nin yerleşik
 * openssl_verify()'ı tam olarak PKCS#1 v1.5 yapar, dolayısıyla burada ek kütüphane GEREKMEZ.
 *
 * İkinci adım (bu fonksiyonun kapsamı dışında): $pcr0Hex değerini Enclave repo'sunun son
 * release'indeki expected_pcr.json ile karşılaştırın.
 *
 * @return bool İmza geçerliyse true. Anahtar okunamazsa false (istisna fırlatmaz).
 */
function vb_verify_pcr0_signature(string $developerPubPem, string $pcr0Hex, string $signatureB64): bool
{
    $sig = base64_decode($signatureB64, true);
    if ($sig === false) {
        return false;
    }
    return openssl_verify($pcr0Hex, $sig, $developerPubPem, OPENSSL_ALGO_SHA256) === 1;
}
