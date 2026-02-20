<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserJsonLd\JsonLdHandler;

beforeEach(function () {
    $this->handler = new JsonLdHandler();
});

// ──────────────────────────────────────────────────────────────────────────
// TRUE cases: valid JSON-LD that should be detected
// ──────────────────────────────────────────────────────────────────────────

describe('canHandle() detects valid JSON-LD', function () {
    it('detects top-level array with @id', function () {
        $content = '[{"@id": "http://example.org/resource"}]';
        expect($this->handler->canHandle($content))->toBeTrue();
    });

    it('detects top-level array with @type', function () {
        $content = '[{"@type": "http://schema.org/Thing"}]';
        expect($this->handler->canHandle($content))->toBeTrue();
    });

    it('detects contextless JSON-LD with @id', function () {
        $content = '{"@id": "http://example.org/foo"}';
        expect($this->handler->canHandle($content))->toBeTrue();
    });

    it('detects contextless JSON-LD with full IRI property keys', function () {
        $content = '{"http://xmlns.com/foaf/0.1/name": "Alice"}';
        expect($this->handler->canHandle($content))->toBeTrue();
    });

    it('detects JSON-LD with @graph keyword', function () {
        $content = '{"@graph": [{"@id": "http://example.org/a"}]}';
        expect($this->handler->canHandle($content))->toBeTrue();
    });

    it('detects JSON-LD with @type keyword without @context', function () {
        $content = '{"@type": "http://schema.org/Person", "http://schema.org/name": "Alice"}';
        expect($this->handler->canHandle($content))->toBeTrue();
    });

    it('detects JSON-LD array with nested keywords', function () {
        $content = '[{"@id": "http://example.org/a", "@type": "http://schema.org/Thing"}]';
        expect($this->handler->canHandle($content))->toBeTrue();
    });

    it('detects JSON-LD with @value keyword', function () {
        $content = '{"@value": "hello", "@language": "en"}';
        expect($this->handler->canHandle($content))->toBeTrue();
    });

    it('detects JSON-LD with @context and top-level array', function () {
        $content = '[{"@context": {"ex": "http://example.org/"}, "@id": "ex:thing"}]';
        expect($this->handler->canHandle($content))->toBeTrue();
    });

    it('detects JSON-LD with https IRI keys', function () {
        $content = '{"https://schema.org/name": "Alice"}';
        expect($this->handler->canHandle($content))->toBeTrue();
    });
});

// ──────────────────────────────────────────────────────────────────────────
// FALSE cases: non-JSON-LD content that should be rejected
// ──────────────────────────────────────────────────────────────────────────

describe('canHandle() rejects non-JSON-LD', function () {
    it('rejects plain JSON object', function () {
        $content = '{"name": "Alice", "age": 30}';
        expect($this->handler->canHandle($content))->toBeFalse();
    });

    it('rejects JSON array of plain objects', function () {
        $content = '[{"name": "Alice"}]';
        expect($this->handler->canHandle($content))->toBeFalse();
    });

    it('rejects JSON with non-JSON-LD @ keys', function () {
        $content = '{"@timestamp": "2026-01-01", "@version": 1}';
        expect($this->handler->canHandle($content))->toBeFalse();
    });

    it('rejects empty JSON object', function () {
        expect($this->handler->canHandle('{}'))->toBeFalse();
    });

    it('rejects empty JSON array', function () {
        expect($this->handler->canHandle('[]'))->toBeFalse();
    });

    it('rejects JSON with numeric keys', function () {
        $content = '{"0": "first", "1": "second"}';
        expect($this->handler->canHandle($content))->toBeFalse();
    });

    it('rejects invalid JSON', function () {
        expect($this->handler->canHandle('{not json}'))->toBeFalse();
    });

    it('rejects empty string', function () {
        expect($this->handler->canHandle(''))->toBeFalse();
    });

    it('rejects XML content', function () {
        expect($this->handler->canHandle('<?xml version="1.0"?><root/>'))->toBeFalse();
    });

    it('rejects Turtle content', function () {
        $content = '@prefix ex: <http://example.org/> .';
        expect($this->handler->canHandle($content))->toBeFalse();
    });

    it('rejects N-Triples content', function () {
        $content = '<http://example.org/s> <http://example.org/p> <http://example.org/o> .';
        expect($this->handler->canHandle($content))->toBeFalse();
    });

    it('rejects array of empty objects', function () {
        expect($this->handler->canHandle('[{}, {}]'))->toBeFalse();
    });

    it('rejects JSON with @version that is not a JSON-LD keyword in this context', function () {
        // @version alone without other JSON-LD keywords is not sufficient
        // Note: @version IS a JSON-LD 1.1 keyword but only valid inside @context
        $content = '{"@version": 1.1, "name": "test"}';
        expect($this->handler->canHandle($content))->toBeFalse();
    });
});
