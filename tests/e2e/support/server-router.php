<?php

declare(strict_types=1);

/**
 * Router untuk PHP built-in server khusus Playwright E2E.
 *
 * Menggunakan `php -S` secara langsung (bukan `php artisan serve`) agar seluruh
 * variabel environment terisolasi (MEDIA_DISK, RS_API_ENABLED, APP_CONFIG_CACHE,
 * WEB_FRONTEND_WEBHOOK_URL, dsb.) diwarisi penuh tanpa difilter ServeCommand.
 */
$rootPath = dirname(__DIR__, 3);
$publicPath = $rootPath.DIRECTORY_SEPARATOR.'public';
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

if ($uri !== '/' && is_file($publicPath.$uri)) {
    return false;
}

// Layani berkas MEDIA_DISK=public (/storage/*) langsung dari storage/app/public
// agar pengujian E2E bekerja deterministik di Windows maupun Linux tanpa bergantung symlink.
if (str_starts_with($uri, '/storage/')) {
    $storagePublicRoot = realpath($rootPath.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public');
    $relativePath = ltrim(substr($uri, strlen('/storage/')), '/');
    $candidate = $storagePublicRoot !== false
        ? realpath($storagePublicRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath))
        : false;

    if ($storagePublicRoot !== false && $candidate !== false && str_starts_with($candidate, $storagePublicRoot) && is_file($candidate)) {
        $ext = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
        header('Content-Type: '.$mime);
        header('Content-Length: '.(string) filesize($candidate));
        readfile($candidate);

        return true;
    }
}

require_once $publicPath.DIRECTORY_SEPARATOR.'index.php';
