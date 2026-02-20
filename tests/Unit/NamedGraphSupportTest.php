<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;
use Youri\vandenBogert\Software\ParserJsonLd\JsonLdHandler;

beforeEach(function () {
    $this->handler = new JsonLdHandler();
});

describe('named graph support', function () {
    it('preserves named graph quads in metadata', function () {
        $content = json_encode([
            '@context' => ['ex' => 'http://example.org/'],
            '@id' => 'http://example.org/graph1',
            '@graph' => [
                ['@id' => 'http://example.org/s', 'ex:p' => 'object'],
            ],
        ]);
        $result = $this->handler->parse($content);
        expect($result)->toBeInstanceOf(ParsedRdf::class);
        expect($result->metadata)->toHaveKey('named_graphs');
        expect($result->metadata['named_graphs'])->toBeArray();
        expect($result->metadata['named_graphs'])->not->toBeEmpty();
    });

    it('stores graph identifier with named graph triples', function () {
        $content = json_encode([
            '@context' => ['ex' => 'http://example.org/'],
            '@id' => 'http://example.org/graph1',
            '@graph' => [
                ['@id' => 'http://example.org/s', 'ex:p' => 'object'],
            ],
        ]);
        $result = $this->handler->parse($content);

        $namedGraphs = $result->metadata['named_graphs'];
        expect($namedGraphs)->toHaveKey('http://example.org/graph1');
    });

    it('stores triples for each named graph', function () {
        $content = json_encode([
            '@context' => ['ex' => 'http://example.org/'],
            '@id' => 'http://example.org/graph1',
            '@graph' => [
                ['@id' => 'http://example.org/s', 'ex:p' => 'object'],
            ],
        ]);
        $result = $this->handler->parse($content);

        $graphTriples = $result->metadata['named_graphs']['http://example.org/graph1'];
        expect($graphTriples)->toBeArray();
        expect($graphTriples)->not->toBeEmpty();
        // Should contain subject, predicate, object info
        expect($graphTriples[0])->toHaveKey('subject');
        expect($graphTriples[0])->toHaveKey('predicate');
        expect($graphTriples[0])->toHaveKey('object');
    });

    it('handles multiple named graphs', function () {
        $content = json_encode([
            '@context' => ['ex' => 'http://example.org/'],
            '@graph' => [
                [
                    '@id' => 'http://example.org/graph1',
                    '@graph' => [
                        ['@id' => 'http://example.org/s1', 'ex:p1' => 'v1'],
                    ],
                ],
                [
                    '@id' => 'http://example.org/graph2',
                    '@graph' => [
                        ['@id' => 'http://example.org/s2', 'ex:p2' => 'v2'],
                    ],
                ],
            ],
        ]);
        $result = $this->handler->parse($content);

        $namedGraphs = $result->metadata['named_graphs'];
        expect($namedGraphs)->toHaveKey('http://example.org/graph1');
        expect($namedGraphs)->toHaveKey('http://example.org/graph2');
    });

    it('preserves default graph triples in graph property', function () {
        $content = json_encode([
            '@context' => ['ex' => 'http://example.org/'],
            '@graph' => [
                ['@id' => 'http://example.org/default-resource', 'ex:name' => 'test'],
            ],
        ]);
        $result = $this->handler->parse($content);

        $resourceUris = array_map(fn ($r) => (string) $r, $result->graph->resources());
        expect($resourceUris)->toContain('http://example.org/default-resource');
    });

    it('returns empty named_graphs for documents without named graphs', function () {
        $content = json_encode([
            '@context' => ['rdfs' => 'http://www.w3.org/2000/01/rdf-schema#'],
            '@id' => 'http://example.org/A',
            '@type' => 'rdfs:Class',
        ]);
        $result = $this->handler->parse($content);

        expect($result->metadata)->toHaveKey('named_graphs');
        expect($result->metadata['named_graphs'])->toBe([]);
    });
});
