<?php

use App\Services\HtmlSanitizer;

beforeEach(function () {
    $this->sanitizer = new HtmlSanitizer;
});

describe('HtmlSanitizer', function () {
    it('returns null for null input', function () {
        expect($this->sanitizer->sanitize(null))->toBeNull();
    });

    it('returns null for empty string', function () {
        expect($this->sanitizer->sanitize(''))->toBeNull();
    });

    it('returns null for whitespace-only input', function () {
        expect($this->sanitizer->sanitize('   '))->toBeNull();
    });

    it('preserves allowed tags', function () {
        $html = '<strong>bold</strong> and <em>italic</em>';

        expect($this->sanitizer->sanitize($html))->toBe($html);
    });

    it('preserves links with allowed attributes', function () {
        $html = '<a href="https://example.com" target="_blank" rel="noopener noreferrer">link</a>';

        expect($this->sanitizer->sanitize($html))->toBe($html);
    });

    it('preserves br tags', function () {
        $html = 'line one<br>line two';

        expect($this->sanitizer->sanitize($html))->toBe($html);
    });

    it('strips disallowed tags', function () {
        $html = '<script>alert("xss")</script><strong>safe</strong>';

        expect($this->sanitizer->sanitize($html))->toBe('alert("xss")<strong>safe</strong>');
    });

    it('strips disallowed attributes from allowed tags', function () {
        $html = '<strong onclick="alert(1)">bold</strong>';

        expect($this->sanitizer->sanitize($html))->toBe('<strong>bold</strong>');
    });

    it('strips disallowed attributes from links', function () {
        $html = '<a href="https://example.com" onclick="alert(1)" class="bad">link</a>';

        expect($this->sanitizer->sanitize($html))->toBe('<a href="https://example.com">link</a>');
    });

    it('blocks javascript: URLs in href', function () {
        $html = '<a href="javascript:alert(1)">click me</a>';

        expect($this->sanitizer->sanitize($html))->toBe('<a>click me</a>');
    });

    it('blocks javascript: URLs with whitespace', function () {
        $html = '<a href="  javascript:alert(1)">click me</a>';

        expect($this->sanitizer->sanitize($html))->toBe('<a>click me</a>');
    });

    it('preserves plain text', function () {
        $html = 'just some text';

        expect($this->sanitizer->sanitize($html))->toBe($html);
    });

    it('strips img tags', function () {
        $html = '<img src="x" onerror="alert(1)">text';

        expect($this->sanitizer->sanitize($html))->toBe('text');
    });

    it('strips div and span tags but keeps content', function () {
        $html = '<div><span>hello</span></div>';

        expect($this->sanitizer->sanitize($html))->toBe('hello');
    });

    it('blocks data: URLs in href', function () {
        $html = '<a href="data:text/html,<script>alert(1)</script>">click me</a>';

        expect($this->sanitizer->sanitize($html))->toBe('<a>click me</a>');
    });

    it('blocks vbscript: URLs in href', function () {
        $html = '<a href="vbscript:MsgBox(1)">click me</a>';

        expect($this->sanitizer->sanitize($html))->toBe('<a>click me</a>');
    });

    it('preserves http:// URLs in href', function () {
        $html = '<a href="http://example.com">link</a>';

        expect($this->sanitizer->sanitize($html))->toBe($html);
    });

    it('preserves https:// URLs in href', function () {
        $html = '<a href="https://example.com">link</a>';

        expect($this->sanitizer->sanitize($html))->toBe($html);
    });
});
