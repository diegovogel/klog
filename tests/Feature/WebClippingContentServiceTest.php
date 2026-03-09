<?php

use App\Services\WebClippingContentService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->service = new WebClippingContentService;
});

describe('WebClippingContentService', function () {
    describe('extractText', function () {
        it('extracts title and content from a simple page', function () {
            Http::fake([
                '*' => Http::response('<html><head><title>Test Page</title></head><body><p>Hello world</p></body></html>'),
            ]);

            $result = $this->service->extractText('https://example.com');

            expect($result['title'])->toBe('Test Page')
                ->and($result['content'])->toBe('<p>Hello world</p>');
        });

        it('returns null for failed HTTP requests', function () {
            Http::fake([
                '*' => Http::response('Not Found', 404),
            ]);

            $result = $this->service->extractText('https://example.com/missing');

            expect($result['title'])->toBeNull()
                ->and($result['content'])->toBeNull();
        });

        it('returns null content for empty pages', function () {
            Http::fake([
                '*' => Http::response('<html><head><title>Empty</title></head><body></body></html>'),
            ]);

            $result = $this->service->extractText('https://example.com');

            expect($result['title'])->toBe('Empty')
                ->and($result['content'])->toBeNull();
        });

        it('returns null title when no title tag exists', function () {
            Http::fake([
                '*' => Http::response('<html><body><p>Content</p></body></html>'),
            ]);

            $result = $this->service->extractText('https://example.com');

            expect($result['title'])->toBeNull()
                ->and($result['content'])->toBe('<p>Content</p>');
        });

        it('decodes HTML entities in title', function () {
            Http::fake([
                '*' => Http::response('<html><head><title>Tom &amp; Jerry &mdash; Classic</title></head><body><p>Content</p></body></html>'),
            ]);

            $result = $this->service->extractText('https://example.com');

            expect($result['title'])->toBe('Tom & Jerry — Classic');
        });
    });

    describe('content extraction', function () {
        it('strips script and style elements with their contents', function () {
            Http::fake([
                '*' => Http::response('<body><script>alert(1)</script><style>.x{color:red}</style><p>Content</p></body>'),
            ]);

            $result = $this->service->extractText('https://example.com');

            expect($result['content'])->toBe('<p>Content</p>');
        });

        it('strips nav, header, footer, and aside elements', function () {
            Http::fake([
                '*' => Http::response('<body><nav>Menu</nav><header>Top</header><p>Article</p><footer>Bottom</footer><aside>Sidebar</aside></body>'),
            ]);

            $result = $this->service->extractText('https://example.com');

            expect($result['content'])->toBe('<p>Article</p>')
                ->and($result['content'])->not->toContain('Menu')
                ->and($result['content'])->not->toContain('Top')
                ->and($result['content'])->not->toContain('Bottom')
                ->and($result['content'])->not->toContain('Sidebar');
        });

        it('strips form, button, and menu elements', function () {
            Http::fake([
                '*' => Http::response('<body><p>Content</p><form><input></form><button>Click</button><menu>Nav</menu></body>'),
            ]);

            $result = $this->service->extractText('https://example.com');

            expect($result['content'])->toBe('<p>Content</p>');
        });

        it('preserves heading tags', function () {
            Http::fake([
                '*' => Http::response('<body><h1>Title</h1><h2>Subtitle</h2><p>Text</p></body>'),
            ]);

            $result = $this->service->extractText('https://example.com');

            expect($result['content'])->toContain('<h1>Title</h1>')
                ->and($result['content'])->toContain('<h2>Subtitle</h2>')
                ->and($result['content'])->toContain('<p>Text</p>');
        });

        it('preserves list tags', function () {
            Http::fake([
                '*' => Http::response('<body><ul><li>One</li><li>Two</li></ul><ol><li>First</li></ol></body>'),
            ]);

            $result = $this->service->extractText('https://example.com');

            expect($result['content'])->toContain('<ul>')
                ->and($result['content'])->toContain('<li>One</li>')
                ->and($result['content'])->toContain('<ol>');
        });

        it('preserves blockquote tags', function () {
            Http::fake([
                '*' => Http::response('<body><blockquote>A wise quote</blockquote></body>'),
            ]);

            $result = $this->service->extractText('https://example.com');

            expect($result['content'])->toContain('<blockquote>A wise quote</blockquote>');
        });

        it('strips attributes from preserved tags', function () {
            Http::fake([
                '*' => Http::response('<body><p class="intro" id="main" style="color:red">Text</p><h1 data-id="5">Title</h1></body>'),
            ]);

            $result = $this->service->extractText('https://example.com');

            expect($result['content'])->toContain('<p>Text</p>')
                ->and($result['content'])->toContain('<h1>Title</h1>')
                ->and($result['content'])->not->toContain('class')
                ->and($result['content'])->not->toContain('style')
                ->and($result['content'])->not->toContain('data-id');
        });

        it('strips non-structural tags but keeps their text', function () {
            Http::fake([
                '*' => Http::response('<body><p><strong>Bold</strong> and <span class="x">span text</span></p></body>'),
            ]);

            $result = $this->service->extractText('https://example.com');

            expect($result['content'])->toContain('Bold')
                ->and($result['content'])->toContain('span text')
                ->and($result['content'])->not->toContain('<strong>')
                ->and($result['content'])->not->toContain('<span');
        });

        it('decodes HTML entities in content', function () {
            Http::fake([
                '*' => Http::response('<body><p>Tom &amp; Jerry &mdash; classic</p></body>'),
            ]);

            $result = $this->service->extractText('https://example.com');

            expect($result['content'])->toContain('Tom & Jerry — classic');
        });

        it('collapses excessive whitespace', function () {
            Http::fake([
                '*' => Http::response('<body><p>Hello     world</p></body>'),
            ]);

            $result = $this->service->extractText('https://example.com');

            expect($result['content'])->toBe('<p>Hello world</p>');
        });

        it('removes empty tags', function () {
            Http::fake([
                '*' => Http::response('<body><p></p><p>Real content</p><div></div></body>'),
            ]);

            $result = $this->service->extractText('https://example.com');

            expect($result['content'])->toBe('<p>Real content</p>');
        });

        it('processes documents without a body tag', function () {
            Http::fake([
                '*' => Http::response('<p>Loose content</p>'),
            ]);

            $result = $this->service->extractText('https://example.com');

            expect($result['content'])->toBe('<p>Loose content</p>');
        });

        it('strips iframe and svg elements', function () {
            Http::fake([
                '*' => Http::response('<body><p>Content</p><iframe src="x"></iframe><svg><circle/></svg></body>'),
            ]);

            $result = $this->service->extractText('https://example.com');

            expect($result['content'])->toBe('<p>Content</p>');
        });

        it('strips noscript elements', function () {
            Http::fake([
                '*' => Http::response('<body><noscript>Enable JS</noscript><p>Content</p></body>'),
            ]);

            $result = $this->service->extractText('https://example.com');

            expect($result['content'])->toBe('<p>Content</p>');
        });

        it('strips encoded script tags after entity decoding', function () {
            Http::fake([
                '*' => Http::response('<body><p>&lt;script&gt;alert(1)&lt;/script&gt;</p></body>'),
            ]);

            $result = $this->service->extractText('https://example.com');

            expect($result['content'])->not->toContain('<script>')
                ->and($result['content'])->toContain('alert(1)');
        });

        it('strips encoded img tags with event handlers after entity decoding', function () {
            Http::fake([
                '*' => Http::response('<body><p>Safe text &lt;img src=x onerror=alert(1)&gt;</p></body>'),
            ]);

            $result = $this->service->extractText('https://example.com');

            expect($result['content'])->not->toContain('<img')
                ->and($result['content'])->not->toContain('onerror');
        });
    });
});
