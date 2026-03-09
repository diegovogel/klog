<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WebClippingContentService
{
    /** @var array<int, string> Elements to strip along with their contents */
    private const STRIP_ELEMENTS = [
        'script', 'style', 'nav', 'header', 'footer',
        'aside', 'noscript', 'iframe', 'svg', 'form',
        'button', 'menu',
    ];

    /** @var string Allowed structural tags for strip_tags() */
    private const ALLOWED_TAGS = '<h1><h2><h3><h4><h5><h6><p><ul><ol><li><br><blockquote>';

    /**
     * Fetch a URL and extract its readable text content as minimal HTML.
     *
     * @return array{title: ?string, content: ?string}
     */
    public function extractText(string $url): array
    {
        $response = Http::timeout(15)
            ->connectTimeout(10)
            ->withUserAgent('Klog/1.0 (personal archiver)')
            ->get($url);

        if (! $response->successful()) {
            return ['title' => null, 'content' => null];
        }

        $html = $response->body();

        return [
            'title' => $this->extractTitle($html),
            'content' => $this->extractContent($html),
        ];
    }

    /**
     * Extract the <title> tag content.
     */
    private function extractTitle(string $html): ?string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $matches)) {
            $title = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            return $title !== '' ? $title : null;
        }

        return null;
    }

    /**
     * Extract readable content as minimal structural HTML.
     */
    private function extractContent(string $html): ?string
    {
        // Isolate <body> content if present
        if (preg_match('/<body[^>]*>(.*)<\/body>/si', $html, $matches)) {
            $html = $matches[1];
        }

        // Remove elements that should be stripped entirely (tag + contents)
        foreach (self::STRIP_ELEMENTS as $tag) {
            $html = preg_replace(
                '/<'.$tag.'\b[^>]*>.*?<\/'.$tag.'>/si',
                '',
                $html
            );
            // Handle self-closing variants
            $html = preg_replace(
                '/<'.$tag.'\b[^>]*\/?\s*>/si',
                '',
                $html
            );
        }

        // Strip all tags except structural ones (also removes all attributes)
        $html = strip_tags($html, self::ALLOWED_TAGS);

        // Remove attributes from remaining tags (strip_tags keeps them)
        $html = preg_replace('/<(\w+)\s[^>]*>/', '<$1>', $html);

        // Decode HTML entities
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Re-apply strip_tags after entity decoding to catch any encoded tags
        // that were decoded into real tags (e.g., &lt;script&gt; → <script>)
        $html = strip_tags($html, self::ALLOWED_TAGS);

        // Collapse runs of whitespace (spaces/tabs) within text to single spaces
        $html = preg_replace('/[^\S\n]+/', ' ', $html);

        // Remove blank lines and excessive newlines
        $html = preg_replace('/\n\s*\n/', "\n", $html);

        // Trim whitespace around tags and the whole string
        $html = preg_replace('/>\s+</', '><', $html);
        $html = trim($html);

        // Remove empty tags (e.g., <p></p>, <li></li>)
        $html = preg_replace('/<(\w+)><\/\1>/', '', $html);
        $html = trim($html);

        return $html !== '' ? $html : null;
    }
}
