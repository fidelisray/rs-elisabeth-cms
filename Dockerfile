# ==============================================================================
# STAGE 1: PHP-FPM (Application Server)
# ==============================================================================
FROM php:8.2-fpm-alpine AS fpm_app

# 1. Install Alpine dependencies
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
    bash

# 2. Configure & Install PHP Extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_pgsql mbstring exif pcntl bcmath gd intl zip

# 3. Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. Set Workdir & Copy Source Code
WORKDIR /var/www/html
COPY . .

# 5. Install Dependencies (Production Ready)
# Ini akan meng-generate folder vendor dan me-publish asset (misal: filament CSS/JS ke folder public)
RUN composer install --optimize-autoloader --no-dev

# 6. Set Permissions untuk folder krusial
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# 7. Start PHP-FPM
EXPOSE 9000
CMD ["php-fpm"]


# ==============================================================================
# STAGE 2: NGINX (Web Server)
# ==============================================================================
FROM nginx:alpine AS web_server

# 1. Set Workdir
WORKDIR /var/www/html

# 2. Copy konfigurasi kustom Nginx
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# 3. COPY folder "public" dari STAGE 1 (fpm_app)
# TRICK DEVOPS: Nginx hanya butuh folder public untuk menyajikan CSS, JS, dan Gambar (Web Assets).
# Dengan mengambil dari stage fpm_app, asset bawaan Filament yang di-generate via Composer
# akan secara otomatis terbawa tanpa masalah perizinan volume!
COPY --from=fpm_app /var/www/html/public /var/www/html/public

EXPOSE 80
