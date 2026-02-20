<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserCore\Exceptions\ParseException;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;
use Youri\vandenBogert\Software\ParserJsonLd\JsonLdHandler;

beforeEach(function () {
    $this->handler = new JsonLdHandler();
});

describe('remote context security', function () {
    it('throws ParseException when disableRemoteContexts is true and document references remote context', function () {
        $content = json_encode([
            '@context' => 'http://example.org/remote-context.jsonld',
            '@id' => 'http://example.org/thing',
            '@type' => 'Thing',
        ]);

        expect(fn () => $this->handler->parseWithOptions($content, [
            'disableRemoteContexts' => true,
        ]))->toThrow(ParseException::class, 'Remote context resolution is disabled');
    });

    it('throws ParseException when disableRemoteContexts is true and array context has remote URL', function () {
        $content = json_encode([
            '@context' => [
                'http://example.org/remote-context.jsonld',
                ['ex' => 'http://example.org/'],
            ],
            '@id' => 'http://example.org/thing',
        ]);

        expect(fn () => $this->handler->parseWithOptions($content, [
            'disableRemoteContexts' => true,
        ]))->toThrow(ParseException::class, 'Remote context resolution is disabled');
    });

    it('throws ParseException when disableRemoteContexts is true and context has https URL', function () {
        $content = json_encode([
            '@context' => 'https://schema.org/',
            '@id' => 'http://example.org/thing',
            '@type' => 'Thing',
        ]);

        expect(fn () => $this->handler->parseWithOptions($content, [
            'disableRemoteContexts' => true,
        ]))->toThrow(ParseException::class, 'Remote context resolution is disabled');
    });

    it('does not throw when disableRemoteContexts is true and context is inline object', function () {
        $content = json_encode([
            '@context' => ['ex' => 'http://example.org/'],
            '@id' => 'http://example.org/thing',
            '@type' => 'ex:Thing',
        ]);

        $result = $this->handler->parseWithOptions($content, [
            'disableRemoteContexts' => true,
        ]);
        expect($result)->toBeInstanceOf(ParsedRdf::class);
    });

    it('does not throw when disableRemoteContexts is false and context has remote URL', function () {
        // This test will attempt to fetch the remote context and fail because the URL is unreachable,
        // but it should NOT throw the "Remote context resolution is disabled" message.
        $content = json_encode([
            '@context' => 'http://example.org/nonexistent-context.jsonld',
            '@id' => 'http://example.org/thing',
            '@type' => 'Thing',
        ]);

        try {
            $this->handler->parseWithOptions($content, [
                'disableRemoteContexts' => false,
            ]);
            // If it passes (unlikely), that's fine too
        } catch (ParseException $e) {
            // The error should be about loading failure, NOT about disabled contexts
            expect($e->getMessage())->not->toContain('Remote context resolution is disabled');
        }
    });

    it('does not throw when disableRemoteContexts is not set and context has remote URL', function () {
        // Default should be false (allow remote contexts)
        $content = json_encode([
            '@context' => 'http://example.org/nonexistent-context.jsonld',
            '@id' => 'http://example.org/thing',
            '@type' => 'Thing',
        ]);

        try {
            $this->handler->parseWithOptions($content, []);
            // If it passes, that's fine
        } catch (ParseException $e) {
            // The error should be about loading failure, NOT about disabled contexts
            expect($e->getMessage())->not->toContain('Remote context resolution is disabled');
        }
    });

    it('detects remote context in nested array context entries', function () {
        $content = json_encode([
            '@context' => [
                ['ex' => 'http://example.org/'],
                'https://example.org/remote.jsonld',
            ],
            '@id' => 'http://example.org/thing',
        ]);

        expect(fn () => $this->handler->parseWithOptions($content, [
            'disableRemoteContexts' => true,
        ]))->toThrow(ParseException::class, 'Remote context resolution is disabled');
    });
});

describe('remote context error handling', function () {
    it('wraps ml/json-ld loading failure in ParseException', function () {
        // Non-dereferenceable URI scheme causes ml/json-ld to throw
        $content = json_encode([
            '@context' => 'http://example.org/nonexistent-context.jsonld',
            '@id' => 'http://example.org/thing',
            '@type' => 'Thing',
        ]);

        expect(fn () => $this->handler->parseWithOptions($content, []))
            ->toThrow(ParseException::class);
    });
});
