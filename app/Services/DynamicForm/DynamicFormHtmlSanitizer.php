<?php

namespace App\Services\DynamicForm;

use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Support\Str;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class DynamicFormHtmlSanitizer
{
    private ?HtmlSanitizer $sanitizer = null;

    /**
     * Sanitasi HTML atau dokumen array TipTap dari RichEditor untuk deskripsi formulir dan bagian,
     * lalu ubah konten kosong (seperti <p></p>, dokumen TipTap kosong, atau &nbsp;) menjadi null.
     */
    public function sanitize(mixed $input): ?string
    {
        $html = $this->normalizeToHtml($input);
        if ($html === null) {
            return null;
        }

        $trimmed = trim($html);
        if ($trimmed === '') {
            return null;
        }

        $clean = trim($this->sanitizer()->sanitize($trimmed));
        if ($clean === '' || $this->toPlainText($clean) === null) {
            return null;
        }

        // Pastikan seluruh tautan bertarget _blank selalu membawa rel keamanan.
        $clean = (string) preg_replace_callback(
            '/<a\b([^>]*)>/i',
            static function (array $matches): string {
                $attrs = $matches[1];
                if (preg_match('/\btarget\s*=\s*["\']?_blank["\']?/i', $attrs) !== 1) {
                    return $matches[0];
                }

                $attrs = (string) preg_replace('/\s*\brel\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $attrs);

                return '<a'.$attrs.' rel="noopener noreferrer">';
            },
            $clean
        );

        return $clean;
    }

    public function toPlainText(mixed $input, ?int $limit = null): ?string
    {
        $html = $this->normalizeToHtml($input);
        if ($html === null || trim($html) === '') {
            return null;
        }

        $spaced = (string) preg_replace('/<(?:br\s*\/?|\/(?:p|div|li|h[1-6]|blockquote))>/i', ' ', $html);
        $decoded = html_entity_decode(strip_tags($spaced), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalized = trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $decoded));

        if ($normalized === '') {
            return null;
        }

        return $limit !== null ? Str::limit($normalized, $limit) : $normalized;
    }

    /**
     * Ubah input mentah (string HTML atau array JSON TipTap dari Livewire state) menjadi string HTML.
     */
    private function normalizeToHtml(mixed $input): ?string
    {
        if ($input === null) {
            return null;
        }

        if (is_string($input)) {
            return $input;
        }

        if (! is_array($input)) {
            return null;
        }

        $looksLikeTipTap = (isset($input['type']) && is_string($input['type']))
            || (isset($input['content']) && is_array($input['content']));

        if (! $looksLikeTipTap) {
            return null;
        }

        try {
            return RichContentRenderer::make($input)->toUnsafeHtml();
        } catch (\Throwable) {
            return null;
        }
    }

    private function sanitizer(): HtmlSanitizer
    {
        if ($this->sanitizer !== null) {
            return $this->sanitizer;
        }

        $config = (new HtmlSanitizerConfig)
            ->allowElement('p')
            ->allowElement('br')
            ->allowElement('strong')
            ->allowElement('b')
            ->allowElement('em')
            ->allowElement('i')
            ->allowElement('u')
            ->allowElement('s')
            ->allowElement('del')
            ->allowElement('h2')
            ->allowElement('h3')
            ->allowElement('ul')
            ->allowElement('ol')
            ->allowElement('li')
            ->allowElement('blockquote')
            ->allowElement('a', ['href', 'target', 'rel'])
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowRelativeLinks(false)
            ->dropElement('script')
            ->dropElement('style')
            ->dropElement('iframe')
            ->dropElement('object')
            ->dropElement('embed')
            ->dropElement('img')
            ->dropElement('svg');

        $this->sanitizer = new HtmlSanitizer($config);

        return $this->sanitizer;
    }
}
