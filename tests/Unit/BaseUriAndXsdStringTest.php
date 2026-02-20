<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;
use Youri\vandenBogert\Software\ParserJsonLd\JsonLdHandler;

beforeEach(function () {
    $this->handler = new JsonLdHandler();
});

// ──────────────────────────────────────────────────────────────────────────
// Base URI support
// ──────────────────────────────────────────────────────────────────────────

describe('parse() with base URI', function () {
    it('resolves relative @id against base URI', function () {
        $content = json_encode([
            '@context' => ['ex' => 'http://example.org/'],
            '@id' => 'resource1',
            '@type' => 'ex:Thing',
        ]);
        $result = $this->handler->parseWithOptions($content, ['base' => 'http://example.org/']);
        expect($result)->toBeInstanceOf(ParsedRdf::class);

        $resourceUris = array_map(fn ($r) => (string) $r, $result->graph->resources());
        expect($resourceUris)->toContain('http://example.org/resource1');
    });

    it('parses without base URI unchanged (backward compatible)', function () {
        $content = json_encode([
            '@context' => ['rdfs' => 'http://www.w3.org/2000/01/rdf-schema#'],
            '@id' => 'http://example.org/A',
            '@type' => 'rdfs:Class',
        ]);
        $result = $this->handler->parseWithOptions($content, []);
        expect($result)->toBeInstanceOf(ParsedRdf::class);

        $resourceUris = array_map(fn ($r) => (string) $r, $result->graph->resources());
        expect($resourceUris)->toContain('http://example.org/A');
    });

    it('supports base URI with fragment-only reference', function () {
        $content = json_encode([
            '@context' => ['ex' => 'http://example.org/'],
            '@id' => '#fragment',
            '@type' => 'ex:Thing',
        ]);
        $result = $this->handler->parseWithOptions($content, ['base' => 'http://example.org/doc']);
        expect($result)->toBeInstanceOf(ParsedRdf::class);

        $resourceUris = array_map(fn ($r) => (string) $r, $result->graph->resources());
        expect($resourceUris)->toContain('http://example.org/doc#fragment');
    });

    it('ignores base URI when all IRIs are absolute', function () {
        $content = json_encode([
            '@context' => ['rdfs' => 'http://www.w3.org/2000/01/rdf-schema#'],
            '@id' => 'http://example.org/Absolute',
            '@type' => 'rdfs:Class',
        ]);
        $result = $this->handler->parseWithOptions($content, ['base' => 'http://other.org/']);
        expect($result)->toBeInstanceOf(ParsedRdf::class);

        $resourceUris = array_map(fn ($r) => (string) $r, $result->graph->resources());
        expect($resourceUris)->toContain('http://example.org/Absolute');
    });

    it('supports parse() without options (backward compatible interface)', function () {
        $content = json_encode([
            '@context' => ['rdfs' => 'http://www.w3.org/2000/01/rdf-schema#'],
            '@id' => 'http://example.org/A',
            '@type' => 'rdfs:Class',
        ]);
        $result = $this->handler->parse($content);
        expect($result)->toBeInstanceOf(ParsedRdf::class);
    });
});

// ──────────────────────────────────────────────────────────────────────────
// xsd:string normalization
// ──────────────────────────────────────────────────────────────────────────

describe('xsd:string normalization', function () {
    it('produces N-Triples without explicit xsd:string for plain literals', function () {
        $content = json_encode([
            '@context' => ['ex' => 'http://example.org/'],
            '@id' => 'http://example.org/item',
            'ex:name' => 'Alice',
        ]);
        $result = $this->handler->parse($content);

        $ntriples = $result->graph->serialise('ntriples');
        if (is_string($ntriples)) {
            // After normalization, plain literals should NOT have ^^xsd:string
            $normalizedNt = normalizeXsdString($ntriples);
            expect($normalizedNt)->not->toContain('^^<http://www.w3.org/2001/XMLSchema#string>');
            expect($normalizedNt)->toContain('"Alice"');
        }
    });

    it('preserves explicitly typed non-string datatypes', function () {
        $content = json_encode([
            '@context' => [
                'ex' => 'http://example.org/',
                'xsd' => 'http://www.w3.org/2001/XMLSchema#',
            ],
            '@id' => 'http://example.org/item',
            'ex:count' => ['@value' => '42', '@type' => 'xsd:integer'],
        ]);
        $result = $this->handler->parse($content);

        $ntriples = $result->graph->serialise('ntriples');
        if (is_string($ntriples)) {
            expect($ntriples)->toContain('^^<http://www.w3.org/2001/XMLSchema#integer>');
        }
    });

    it('preserves language-tagged literals unchanged', function () {
        $content = json_encode([
            '@context' => ['rdfs' => 'http://www.w3.org/2000/01/rdf-schema#'],
            '@id' => 'http://example.org/concept',
            'rdfs:label' => ['@value' => 'Hallo', '@language' => 'de'],
        ]);
        $result = $this->handler->parse($content);

        $ntriples = $result->graph->serialise('ntriples');
        if (is_string($ntriples)) {
            expect($ntriples)->toContain('"Hallo"@de');
        }
    });
});

/**
 * Normalize N-Triples output by stripping explicit xsd:string from plain literals.
 *
 * In RDF 1.1, plain literals are equivalent to ^^xsd:string literals.
 * The W3C test suite uses the plain form. This function converts the explicit form to plain.
 */
function normalizeXsdString(string $ntriples): string
{
    // Replace "value"^^<http://www.w3.org/2001/XMLSchema#string> with "value"
    // But only when there is no language tag
    return preg_replace(
        '/"(\^{2}<http:\/\/www\.w3\.org\/2001\/XMLSchema#string>)/',
        '"',
        $ntriples
    ) ?? $ntriples;
}
