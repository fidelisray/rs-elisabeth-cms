#!/bin/sh
# ==============================================================================
# docker/entrypoint.sh — RS Elisabeth CMS Bootstrap Script
#
# Script ini dijalankan SETIAP KALI container start (sebelum php-fpm).
# Tugas: cache, migrasi database, symlink storage, dan sync assets ke Nginx.
# ==============================================================================
set -e

echo "================================================================"
echo " RS Elisabeth CMS — Container Bootstrap"
echo "================================================================"

# 1. Cache konfigurasi Laravel untuk performa optimal
echo "[1/8] Caching application configuration..."
php artisan config:cache

echo "[2/8] Caching routes..."
php artisan route:cache

echo "[3/8] Caching views..."
php artisan view:cache

echo "[4/8] Caching filament components"
php artisan filament:cache-components

echo "[5/8] Caching filament icons..."
php artisan icons:cache

# 2. Jalankan migrasi database (--force wajib untuk non-interactive/production)
echo "[6/8] Running database migrations..."
php artisan migrate --force

# 3. Buat symlink storage (ignore error jika sudah ada atau disk=s3)
echo "[7/8] Creating storage symlink..."
php artisan storage:link --force 2>/dev/null || true

# 4. Sync seluruh isi public/ ke shared volume agar Nginx bisa menyajikannya.
#    Ini dilakukan SETELAH artisan commands selesai agar index.php dan
#    semua asset tersedia lengkap di shared volume.
echo "[8/8] Syncing public assets to shared volume for Nginx..."
cp -rp /var/www/html/public/. /var/www/html/public_shared/

# Sentinel file: menandai bahwa bootstrap sudah selesai (dipakai healthcheck)
touch /tmp/app_healthy

echo "================================================================"
echo " Bootstrap complete! Starting PHP-FPM..."
echo "================================================================"

# Gantikan proses ini dengan command yang diberikan (php-fpm)
exec "$@"
