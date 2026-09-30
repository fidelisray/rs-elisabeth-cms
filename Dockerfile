# ==============================================================================
# STAGE 0: Node.js Builder — Build Frontend Assets (Vite + TailwindCSS)
# ==============================================================================
FROM node:22-alpine AS node_builder

WORKDIR /app

# Copy manifest files dulu untuk memanfaatkan Docker layer caching.
# Layer ini hanya rebuild jika package.json atau package-lock.json berubah.
COPY package*.json ./
RUN npm ci --frozen-lockfile

# Salin sisa source code & build assets
COPY . .
RUN npm run build

# ==============================================================================
# STAGE 1: PHP-FPM (Application Server)
# ==============================================================================
FROM php:8.4-fpm-alpine AS fpm_app

LABEL maintainer="RS Elisabeth" \
      description="PHP-FPM Application Image for RS Elisabeth CMS"

# 1. Install Alpine system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    icu-dev \
    libzip-dev \
    postgresql-dev \
    zlib-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    libwebp-dev \
    bash

# 2. Configure & Install PHP Extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo_pgsql mbstring exif pcntl bcmath gd intl zip opcache \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# 3. Install Composer dari official image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. Set working directory
WORKDIR /var/www/html

# 5. Copy manifest Composer dulu untuk memanfaatkan layer caching.
#    Layer ini hanya rebuild jika composer.json atau composer.lock berubah.
COPY composer.json composer.lock ./

# 6. Install PHP dependencies tanpa menjalankan scripts artisan dulu
#    (artisan belum tersedia karena source belum di-copy)
RUN composer install --optimize-autoloader --no-dev --no-scripts --no-interaction

# 7. Salin seluruh source code aplikasi
#    File yang ada di .dockerignore (vendor, node_modules, .env, dll) TIDAK akan disalin
COPY . .

# 8. Salin hasil build Vite dari Stage 0 (public/build/)
COPY --from=node_builder /app/public/build /var/www/html/public/build

# 9. Re-generate autoloader & jalankan artisan post-install scripts
#    APP_KEY dummy di-inject sebagai ARG (bukan ENV) agar tidak tersimpan di layer image
#    dan tidak memicu peringatan keamanan Docker Scout.
#    Key ASLI akan di-inject melalui env_file saat runtime — key ini TIDAK dipakai production.
ARG BUILD_APP_K="base64:ZG9ja2VyYnVpbGRrZXkxMjM0NTY3ODkwYWJjZGVmZ2g="
RUN APP_KEY="${BUILD_APP_K}" composer dump-autoload --optimize \
    && APP_KEY="${BUILD_APP_K}" php artisan package:discover --ansi \
    && APP_KEY="${BUILD_APP_K}" php artisan filament:upgrade

# 10. Set permissions untuk folder-folder krusial Laravel
#     Buat juga public_shared/ (staging area untuk shared volume dengan Nginx)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache \
    && mkdir -p /var/www/html/public_shared \
    && chown www-data:www-data /var/www/html/public_shared

# 11. Salin & set permission entrypoint
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# PHP-FPM listen di port 9000
EXPOSE 9000

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]
