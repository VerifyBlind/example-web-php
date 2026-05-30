# VerifyBlind PHP Example Web — Multi-stage build
# Build context: parent directory (src/)

FROM composer:2 AS deps
WORKDIR /build

# Portal composer dosyalarını kopyala
COPY example-web-php/composer.json example-web-php/

# Portal bağımlılıklarını yükle (sadece dotenv)
WORKDIR /build/example-web-php
RUN composer install --no-dev --optimize-autoloader

FROM php:8.3-apache

# Apache rewrite modülü + ports.conf ayarı (sadece 5202)
RUN a2enmod rewrite \
    && sed -i 's/Listen 80/# Listen 80/' /etc/apache2/ports.conf

# Apache yapılandırması
COPY example-web-php/apache.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

# vendor kopyala
COPY --from=deps /build/example-web-php/vendor /var/www/html/vendor

# Portal dosyalarını kopyala
COPY example-web-php/ /var/www/html/

# vendor dizinini üzerine yaz (deps stage'den)
COPY --from=deps /build/example-web-php/vendor /var/www/html/vendor

ARG LOCAL_URL_REPLACE=false
RUN if [ "$LOCAL_URL_REPLACE" = "true" ]; then \
      find /var/www/html -type f \( -name "*.html" -o -name "*.js" -o -name "*.php" \) \
        -not -path "*/vendor/*" \
        -exec sed -i \
          -e 's|https://cdn.verifyblind.com|http://cdn.verifyblind.localhost|g' \
          -e 's|https://api.verifyblind.com|http://api.verifyblind.localhost|g' \
          -e 's|https://partner.verifyblind.com|http://partner.verifyblind.localhost|g' \
          -e 's|https://admin.verifyblind.com|http://admin.verifyblind.localhost|g' \
          -e 's|https://test.verifyblind.com|http://test.verifyblind.localhost|g' \
          -e 's|https://app.verifyblind.com|http://app.verifyblind.localhost|g' \
          -e 's|https://verifyblind.com|http://verifyblind.localhost|g' \
          {} +; \
    fi

EXPOSE 5202
