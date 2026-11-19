<?php

namespace App\Services\DynamicForm;

use App\Enums\DynamicFormStatus;
use App\Models\DynamicForm;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DynamicFormShareService
{
    public function canShare(DynamicForm $form): bool
    {
        return ! $form->trashed()
            && $form->status !== DynamicFormStatus::Draft
            && $form->published_version_id !== null;
    }

    /**
     * Selesaikan URL dasar publik Web Frontend dengan fallback ke origin webhook_url.
     */
    public function resolvePublicBaseUrl(): ?string
    {
        $configured = trim((string) config('web_frontend.public_url', ''));
        if ($configured !== '') {
            $parsed = parse_url($configured);
            $scheme = strtolower((string) ($parsed['scheme'] ?? ''));

            if (is_array($parsed) && in_array($scheme, ['http', 'https'], true) && ! empty($parsed['host'])) {
                return rtrim($configured, '/');
            }
        }

        $webhookUrl = trim((string) config('web_frontend.webhook_url', ''));
        if ($webhookUrl === '') {
            return null;
        }

        $parsed = parse_url($webhookUrl);
        $scheme = strtolower((string) ($parsed['scheme'] ?? ''));

        if (! is_array($parsed) || ! in_array($scheme, ['http', 'https'], true) || empty($parsed['host'])) {
            return null;
        }

        $base = $scheme.'://'.$parsed['host'];
        if (! empty($parsed['port'])) {
            $base .= ':'.$parsed['port'];
        }

        return $base;
    }

    public function relativeWebPath(DynamicForm $form): string
    {
        $slug = trim((string) $form->slug);

        return $slug === '' ? '/formulir' : '/formulir/'.rawurlencode($slug);
    }

    public function publicUrl(DynamicForm $form): ?string
    {
        $base = $this->resolvePublicBaseUrl();
        if ($base === null || trim((string) $form->slug) === '') {
            return null;
        }

        return $base.$this->relativeWebPath($form);
    }

    public function qrUrl(DynamicForm $form): ?string
    {
        $publicUrl = $this->publicUrl($form);
        if ($publicUrl === null) {
            return null;
        }

        return $publicUrl.'?source=qr_barcode';
    }

    public function downloadFilename(DynamicForm $form, string $extension): string
    {
        $safeSlug = Str::slug((string) ($form->slug ?: $form->title ?: 'formulir'));
        if ($safeSlug === '') {
            $safeSlug = 'formulir';
        }

        $cleanExt = ltrim(strtolower($extension), '.');

        return "qr-{$safeSlug}.{$cleanExt}";
    }

    public function renderQrSvg(string $url, bool $withXmlHeader = true): string
    {
        $target = trim($url);
        if ($target === '') {
            throw new InvalidArgumentException('URL QR Code tidak boleh kosong.');
        }

        $options = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'eccLevel' => EccLevel::M,
            'addQuietzone' => true,
            'quietzoneSize' => 4,
            'outputBase64' => false,
            'svgAddXmlHeader' => $withXmlHeader,
            'connectPaths' => true,
            'drawLightModules' => true,
        ]);

        $matrix = (new QRCode($options))->addByteSegment($target)->getQRMatrix();
        $outputClass = $options->outputInterface;

        return (string) (new $outputClass($options, $matrix))->dump();
    }

    public function renderQrSvgDataUri(string $url): string
    {
        $svg = $this->renderQrSvg($url, withXmlHeader: true);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    public function renderQrPng(string $url): string
    {
        $target = trim($url);
        if ($target === '') {
            throw new InvalidArgumentException('URL QR Code tidak boleh kosong.');
        }

        // Hitung skala integer agar dimensi PNG stabil di kisaran ~1000px untuk segala panjang slug.
        $probeOptions = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'eccLevel' => EccLevel::M,
            'addQuietzone' => true,
            'quietzoneSize' => 4,
        ]);

        $matrix = (new QRCode($probeOptions))->addByteSegment($target)->getQRMatrix();
        $moduleCount = max(1, $matrix->getSize());
        $scale = max(10, (int) round(1000 / $moduleCount));

        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'eccLevel' => EccLevel::M,
            'addQuietzone' => true,
            'quietzoneSize' => 4,
            'scale' => $scale,
            'outputBase64' => false,
            'imageTransparent' => false,
            'bgColor' => [255, 255, 255],
        ]);

        $outputClass = $options->outputInterface;

        return (string) (new $outputClass($options, $matrix))->dump();
    }

    /**
     * @return array<string, mixed>
     */
    public function buildModalData(DynamicForm $form): array
    {
        $baseUrl = $this->resolvePublicBaseUrl();
        $hasSlug = trim((string) $form->slug) !== '';
        $publicUrl = $this->publicUrl($form);
        $qrUrl = $this->qrUrl($form);

        return [
            'formTitle' => $form->title,
            'formSlug' => $form->slug,
            'relativeWebPath' => $this->relativeWebPath($form),
            'baseUrl' => $baseUrl,
            'isConfigured' => $baseUrl !== null,
            'hasSlug' => $hasSlug,
            'publicUrl' => $publicUrl,
            'qrUrl' => $qrUrl,
            'qrPreviewDataUri' => $qrUrl !== null ? $this->renderQrSvgDataUri($qrUrl) : null,
            'isPublicListed' => (bool) $form->is_public_listed,
            'formStatus' => $form->status,
            'availability' => $form->evaluateAvailability($form->exists ? null : 0),
        ];
    }
}
