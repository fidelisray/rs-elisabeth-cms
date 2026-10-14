#!/bin/sh
set -e

# Jika container dijalankan bukan untuk php-fpm (contoh: cms_worker atau artisan command),
# langsung jalankan perintah tanpa mengulang bootstrap lengkap
if [ "$1" != "php-fpm" ]; then
    exec "$@"
fi

echo "================================================================"
echo " RS Elisabeth CMS — Container Bootstrap"
echo "================================================================"

# 1. Cache konfigurasi Laravel untuk performa optimal
echo "[1/9] Caching application configuration..."
php artisan config:cache

echo "[2/9] Caching routes..."
php artisan route:cache

echo "[3/9] Caching views..."
php artisan view:cache

echo "[4/9] Caching filament components"
php artisan filament:cache-components

echo "[5/9] Caching filament icons..."
php artisan icons:cache

# 2. Jalankan migrasi database (--force wajib untuk non-interactive/production)
echo "[6/9] Running database migrations..."
php artisan migrate --force

# 2b. Seed database (idempoten & non-destructive).
#     Seeder memakai firstOrCreate/findOrCreate sehingga aman dijalankan ulang
#     setiap kali container start. Ini menjamin akun admin, role, dan permission
#     selalu tersedia, termasuk pada deploy pertama ke database kosong.
#     Data konten (news, article, dll) TIDAK akan terhapus karena seeder bersifat
#     add-only dan tidak memakai migrate:fresh.
echo "[6b/9] Seeding database (idempotent)..."
php artisan db:seed --force

# 3. Buat symlink storage (ignore error jika sudah ada atau disk=s3)
echo "[7/9] Creating storage symlink..."
php artisan storage:link --force 2>/dev/null || true

echo "[8/9] Setting up MinIO bucket policy..."
php artisan storage:setup-minio || true

# 4. Sync seluruh isi public/ ke shared volume agar Nginx bisa menyajikannya.
#    Ini dilakukan SETELAH artisan commands selesai agar index.php dan
#    semua asset tersedia lengkap di shared volume.
echo "[9/9] Syncing public assets to shared volume for Nginx..."
cp -rp /var/www/html/public/. /var/www/html/public_shared/

# Pastikan permission storage & bootstrap/cache tetap dimiliki oleh www-data
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Sentinel file: menandai bahwa bootstrap sudah selesai (dipakai healthcheck)
touch /tmp/app_healthy

echo "================================================================"
echo " Bootstrap complete! Starting PHP-FPM..."
echo "================================================================"

# Gantikan proses ini dengan command yang diberikan (php-fpm)
exec "$@"
