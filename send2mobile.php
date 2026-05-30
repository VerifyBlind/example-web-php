<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>VerifyBlind - Uygulamaya Y&#246;nlendiriliyor</title>
    <style>
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f9fafb; }
        .center { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100vh; text-align: center; padding: 20px; }
        .spinner { width: 48px; height: 48px; border: 4px solid #e5e7eb; border-top-color: #2563eb; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 20px; }
        @keyframes spin { to { transform: rotate(360deg); } }
        h2 { color: #111827; margin: 0; font-size: 20px; }
        p { color: #6b7280; margin-top: 10px; }
        .error { color: #ef4444; }
    </style>
</head>
<body>
    <div class="center">
        <div id="content-loading">
            <div class="spinner"></div>
            <h2>Uygulamaya Y&#246;nlendiriliyor...</h2>
            <p>VerifyBlind uygulamas&#305; a&#231;&#305;lmazsa l&#252;tfen uygulama y&#252;kleyip tekrar deneyin.</p>
        </div>
        <div id="content-error" style="display:none;">
            <h2 class="error" id="error-message"></h2>
            <p>L&#252;tfen tekrar deneyiniz.</p>
        </div>
        <div id="verifyblind-widget-container" style="display:none;"></div>
    </div>

    <script src="https://cdn.verifyblind.com/sdk/v1/verifyblind.js"
            integrity="sha384-UgvwkIkL/hfK2Ek3hcBYnhEk0+wzRP16wxvxhS0LgwMMC5UVQ63rQJJPJl319EcO"
            crossorigin="anonymous"></script>
    <script>
        (function () {
            var params = new URLSearchParams(window.location.search);
            var payloadStr = params.get('payload');

            if (!payloadStr) {
                showError('Hata: Payload parametresi bulunamadı.');
                return;
            }

            var payloadObj;
            try {
                payloadObj = JSON.parse(decodeURIComponent(payloadStr));
            } catch (e) {
                showError('Hata: Payload geçerli bir JSON formatında değil.');
                return;
            }

            // 5 saniye sonra kapat
            var closeTimeout = setTimeout(function () { window.close(); }, 5000);

            if (!window.VerifyBlind) {
                showError('VerifyBlind SDK yüklenemedi.');
                clearTimeout(closeTimeout);
                return;
            }

            try {
                window.VerifyBlind.init({
                    containerId: 'verifyblind-widget-container',
                    captcha: false,
                    generateUrl: '/php/api/generate.php',
                    payload: payloadObj,
                    platform: 'mobile',
                    onAuthStarted: function (nonce) {
                        console.log('[Send2Mobile] Başlatıldı:', nonce);
                    },
                    onError: function (err) {
                        showError('Bir hata oluştu: ' + err);
                        clearTimeout(closeTimeout);
                    }
                });
            } catch (e) {
                showError('Başlatma Hatası: ' + e.message);
                clearTimeout(closeTimeout);
            }

            function showError(msg) {
                document.getElementById('content-loading').style.display = 'none';
                document.getElementById('error-message').textContent = msg;
                document.getElementById('content-error').style.display = 'block';
            }
        })();
    </script>
</body>
</html>
