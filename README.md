# VerifyBlind — Web Entegrasyon Örneği (PHP)

**[🇹🇷 Türkçe](#türkçe) · [🇬🇧 English](#english)**

VerifyBlind'i bir PHP web sitesine nasıl entegre edeceğinizi gösteren örnek (PHP + Apache).
`example-web-nextjs` ve `example-web-dotnet` ile **aynı akışın** PHP sürümüdür.

---

## Türkçe

### Akış
1. **Sunucu-taraflı proxy** — Tarayıcı `POST /api/generate.php` çağırır; sunucu `X-API-Key`'i ekleyip
   VerifyBlind `POST /api/pop/generate`'e iletir ve bir `nonce` döner. **API anahtarı tarayıcıya hiç
   gösterilmez.** (`api/generate.php`)
2. **Doğrulama** — Kullanıcı QR'ı VerifyBlind mobil ile okutur (`send2mobile.php`); doğrulama bitince
   partner'a imzalı bir token döner.
3. **İmza kontrolü** — `api/verify.php` token'ı alır, enclave public key'i ile **RSA-PSS imzasını**
   doğrular ve nonce'u tek-kullanımlık tüketir (`api/nonce-store.php`).

### Çalıştırma
```bash
# Docker (önerilen):
docker build -t verifyblind-php .
docker run -p 8080:80 \
  -e TEST_VERIFYBLIND_API_KEY=<partner API anahtarınız> \
  -e VERIFYBLIND_API_URL=https://api.verifyblind.com \
  verifyblind-php
# → http://localhost:8080
```
Ortam değişkenleri docroot dışından `.env`'den de okunur (`sentry-bootstrap.php`).

🌐 [verifyblind.com](https://verifyblind.com) · 🧩 [Next.js örneği](https://github.com/VerifyBlind/example-web-nextjs) · 🧩 [.NET örneği](https://github.com/VerifyBlind/example-web-dotnet)

---

## English

An example of integrating VerifyBlind into a PHP website (PHP + Apache). It is the PHP version of the
**same flow** as `example-web-nextjs` and `example-web-dotnet`.

### Flow
1. **Server-side proxy** — The browser calls `POST /api/generate.php`; the server adds the `X-API-Key`
   and forwards it to VerifyBlind `POST /api/pop/generate`, returning a `nonce`. **The API key is never
   exposed to the browser.** (`api/generate.php`)
2. **Verification** — The user scans the QR with VerifyBlind mobile (`send2mobile.php`); on success a
   signed token is returned to the partner.
3. **Signature check** — `api/verify.php` takes the token, verifies the **RSA-PSS signature** with the
   enclave public key, and consumes the nonce once (`api/nonce-store.php`).

### Running
```bash
# Docker (recommended):
docker build -t verifyblind-php .
docker run -p 8080:80 \
  -e TEST_VERIFYBLIND_API_KEY=<your partner API key> \
  -e VERIFYBLIND_API_URL=https://api.verifyblind.com \
  verifyblind-php
# → http://localhost:8080
```
Environment variables can also be read from a `.env` file outside the docroot (`sentry-bootstrap.php`).

🌐 [verifyblind.com](https://verifyblind.com) · 🧩 [Next.js example](https://github.com/VerifyBlind/example-web-nextjs) · 🧩 [.NET example](https://github.com/VerifyBlind/example-web-dotnet)
