<?php

namespace App\Services;

class HtmlSanitizer
{
    /** @var array<string, array<string>> Allowed tags and their permitted attributes */
    private const ALLOWED = [
        'strong' => [],
        'em' => [],
        'a' => ['href', 'target', 'rel'],
        'br' => [],
    ];

    /**
     * Sanitize HTML to only allow a strict whitelist of tags and attributes.
     */
    public function sanitize(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $allowedTags = implode('', array_map(
            fn (string $tag) => "<{$tag}>",
            array_keys(self::ALLOWED),
        ));

        $html = strip_tags($html, $allowedTags);

        // Strip disallowed attributes from remaining tags
        return preg_replace_callback(
            '/<(\w+)(\s[^>]*)?>/',
            fn (array $matches) => $this->sanitizeTag($matches),
            $html,
        );
    }

    /**
     * @param  array<int, string>  $matches
     */
    private function sanitizeTag(array $matches): string
    {
        $tag = strtolower($matches[1]);
        $attrString = $matches[2] ?? '';

        $allowedAttrs = self::ALLOWED[$tag] ?? [];

        if (empty($allowedAttrs) || trim($attrString) === '') {
            return "<{$tag}>";
        }

        // Parse and filter attributes
        preg_match_all('/(\w[\w-]*)=["\']([^"\']*)["\']/', $attrString, $attrMatches, PREG_SET_ORDER);

        $kept = [];
        foreach ($attrMatches as $attr) {
            $name = strtolower($attr[1]);
            $value = $attr[2];

            if (! in_array($name, $allowedAttrs, true)) {
                continue;
            }

            // Block javascript: URLs in href
            if ($name === 'href' && preg_match('/^\s*javascript\s*:/i', $value)) {
                continue;
            }

            $kept[] = $name.'="'.htmlspecialchars($value, ENT_QUOTES, 'UTF-8').'"';
        }

        $attrHtml = empty($kept) ? '' : ' '.implode(' ', $kept);

        return "<{$tag}{$attrHtml}>";
    }
}
