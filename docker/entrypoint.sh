#!/bin/sh
set -e

# Jika container dijalankan bukan untuk php-fpm (contoh: cms_worker, one-off
# `docker compose run ... php artisan migrate`, atau artisan command),
# langsung jalankan perintah tanpa mengulang bootstrap lengkap.
if [ "$1" != "php-fpm" ]; then
    exec "$@"
fi

# Hapus sentinel dari boot sebelumnya. Container yang di-restart mempertahankan
# filesystem-nya, sehingga tanpa ini healthcheck bisa "healthy" sebelum
# bootstrap yang baru selesai.
rm -f /tmp/app_healthy

echo "================================================================"
echo " RS Elisabeth CMS — Container Bootstrap"
echo "================================================================"

# CATATAN PENTING: entrypoint ini TIDAK menjalankan `migrate` maupun `db:seed`.
# Keduanya dijalankan sekali per rilis sebagai one-off container SEBELUM
# container aplikasi diganti (lihat IMPLEMENTATION_PLAN.md bagian 11.3):
#
#   docker compose run --rm cms_app php artisan migrate --force
#   docker compose run --rm cms_app php artisan db:seed --force
#
# Alasannya: migrate/seed yang berjalan di setiap boot memicu race antar
# replika dan mengulang logika yang seharusnya hanya sekali per rilis.

# 1. Cache konfigurasi Laravel untuk performa optimal
echo "[1/7] Caching application configuration..."
php artisan config:cache

echo "[2/7] Caching routes..."
php artisan route:cache

echo "[3/7] Caching views..."
php artisan view:cache

echo "[4/7] Caching filament components"
php artisan filament:cache-components

echo "[5/7] Caching filament icons..."
php artisan icons:cache

# 2. Buat symlink storage (ignore error jika sudah ada atau disk=s3)
echo "[6/7] Creating storage symlink..."
php artisan storage:link --force 2>/dev/null || true

echo "[6b/7] Setting up MinIO bucket policy..."
php artisan storage:setup-minio || true

# 3. Sync seluruh isi public/ ke shared volume agar Nginx bisa menyajikannya.
#    Ini dilakukan SETELAH artisan commands selesai agar index.php dan
#    semua asset tersedia lengkap di shared volume.
echo "[7/7] Syncing public assets to shared volume for Nginx..."
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
