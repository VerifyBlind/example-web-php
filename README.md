# VerifyBlind — Web Entegrasyon Örneği (PHP)

**[🇹🇷 Türkçe](#türkçe) · [🇬🇧 English](#english)**

VerifyBlind'i bir PHP web sitesine nasıl entegre edeceğinizi gösteren örnek (PHP + Apache).
`example-web-nextjs` ile **aynı (tarayıcı-decrypt / PoP) akışın** PHP sürümüdür; `example-web-dotnet`
ise **sunucu-decrypt (callback/webhook)** varyantını gösterir.

---

## Türkçe

### Akış
1. **Sunucu-taraflı proxy** — Tarayıcı `POST /api/generate.php` çağırır; sunucu `X-API-Key`'i ekleyip
   VerifyBlind `POST /api/pop/generate`'e iletir ve bir `nonce` döner. **API anahtarı tarayıcıya hiç
   gösterilmez.** (`api/generate.php`)
2. **Doğrulama** — Kullanıcı QR'ı VerifyBlind mobil ile okutur; QR'ı `index.html` içinde CDN'den
   yüklenen Web SDK (`verifyblind.js`) çizer. Doğrulama bitince partner'a imzalı bir token döner.
3. **İmza kontrolü** — `api/verify.php` token'ı alır, enclave public key'i ile **RSA-PSS imzasını**
   doğrular ve nonce'u tek-kullanımlık tüketir (`api/nonce-store.php`).

> **Neden phpseclib3?** PHP'nin yerleşik `openssl_verify()` fonksiyonu yalnızca PKCS#1 v1.5
> destekler (padding parametresi yoktur), enclave ise **RSA-PSS** ile imzalar. Doğrulama bu yüzden
> saf PHP olan [phpseclib3](https://phpseclib.com/) ile yapılır — `shell_exec`/`openssl` CLI'a
> ihtiyaç yoktur, dolayısıyla shell'in `disable_functions` ile kapatıldığı paylaşımlı hosting'lerde
> de çalışır. Tek gereksinim `composer install`; PHP eklentisi gerekmez.

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

### Paylaşımlı hosting'e kurulum (Docker olmadan)

Bu örnek sıradan bir PHP hosting'de çalışır. **Gerekenler:** PHP 8.1+, `ext-curl`, `composer`.
Shell erişimi (`shell_exec`, `exec`) **gerekmez** — `disable_functions` ile kapatılmış olabilir.

```bash
composer install --no-dev --optimize-autoloader   # vendor/ üretir
php tests/self-check.php                          # kurulum + imza doğrulama denetimi
```

`self-check` her satırda `[ OK ]` veriyorsa kurulum bu örneği çalıştırabilir. Composer'ı sunucuda
çalıştıramıyorsanız `vendor/` dizinini yerelde üretip dosyalarla birlikte yükleyin.

⚠️ **`.env` dosyanızı docroot'un DIŞINA koyun** — içinde partner API anahtarınız var.
`sentry-bootstrap.php` önce `public_html`'in bir üstüne, sonra proje dizinine bakar. Docroot dışına
koyamıyorsanız, birlikte gelen `.htaccess` dotfile'ları servis edilmekten korur (Apache). **nginx
`.htaccess` okumaz**; orada karşılığını sunucu bloğuna ekleyin:

```nginx
location ~ /\.        { deny all; }   # .env, .git ...
location ~ ^/(vendor|tests)/ { deny all; }
```

🌐 [verifyblind.com](https://verifyblind.com) · 🧩 [Next.js örneği](https://github.com/VerifyBlind/example-web-nextjs) · 🧩 [.NET örneği](https://github.com/VerifyBlind/example-web-dotnet)

---

## English

An example of integrating VerifyBlind into a PHP website (PHP + Apache). It is the PHP version of the
**same (browser-decrypt / PoP) flow** as `example-web-nextjs`; `example-web-dotnet` shows the
**server-decrypt (callback/webhook)** variant.

### Flow
1. **Server-side proxy** — The browser calls `POST /api/generate.php`; the server adds the `X-API-Key`
   and forwards it to VerifyBlind `POST /api/pop/generate`, returning a `nonce`. **The API key is never
   exposed to the browser.** (`api/generate.php`)
2. **Verification** — The user scans the QR with VerifyBlind mobile; the QR itself is rendered by the
   Web SDK (`verifyblind.js`) that `index.html` loads from the CDN. On success a signed token is
   returned to the partner.
3. **Signature check** — `api/verify.php` takes the token, verifies the **RSA-PSS signature** with the
   enclave public key, and consumes the nonce once (`api/nonce-store.php`).

> **Why phpseclib3?** PHP's built-in `openssl_verify()` only supports PKCS#1 v1.5 (it has no padding
> parameter), while the enclave signs with **RSA-PSS**. Verification therefore uses the pure-PHP
> [phpseclib3](https://phpseclib.com/) — no `shell_exec`/`openssl` CLI required, so it also works on
> shared hosts where the shell is disabled via `disable_functions`. The only requirement is
> `composer install`; no PHP extension is needed.

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

### Deploying to shared hosting (without Docker)

This example runs on ordinary PHP hosting. **Requirements:** PHP 8.1+, `ext-curl`, `composer`.
Shell access (`shell_exec`, `exec`) is **not** required — it may well be disabled via
`disable_functions`.

```bash
composer install --no-dev --optimize-autoloader   # creates vendor/
php tests/self-check.php                          # checks the install + signature verification
```

If `self-check` prints `[ OK ]` on every line, the host can run this example. If you cannot run
composer on the server, generate `vendor/` locally and upload it along with the files.

⚠️ **Keep your `.env` OUTSIDE the docroot** — it holds your partner API key. `sentry-bootstrap.php`
looks one level above `public_html` first, then in the project directory. If you cannot place it
outside, the bundled `.htaccess` stops dotfiles from being served (Apache). **nginx does not read
`.htaccess`**; add the equivalent to your server block instead:

```nginx
location ~ /\.        { deny all; }   # .env, .git ...
location ~ ^/(vendor|tests)/ { deny all; }
```

🌐 [verifyblind.com](https://verifyblind.com) · 🧩 [Next.js example](https://github.com/VerifyBlind/example-web-nextjs) · 🧩 [.NET example](https://github.com/VerifyBlind/example-web-dotnet)
