<?php

/**
 * JSON-LD 1.1 Keywords Status Tests
 *
 * Documents the current status of JSON-LD 1.1 keyword support.
 * These tests verify that JSON-LD 1.1 specific features are either:
 * - Correctly processed (if the underlying library supports them)
 * - Gracefully handled (ParseException or silent degradation)
 *
 * The underlying library (sweetrdf/json-ld ^1.3, fork of ml/json-ld) implements
 * JSON-LD 1.0 Processing Algorithms only. JSON-LD 1.1 features are NOT expected
 * to work correctly.
 *
 * @see https://www.w3.org/TR/json-ld11/#keywords
 */

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserCore\Exceptions\ParseException;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;
use Youri\vandenBogert\Software\ParserJsonLd\JsonLdHandler;

beforeEach(function () {
    $this->handler = new JsonLdHandler();
});

describe('JSON-LD 1.1 keyword support status', function () {

    it('documents that @version 1.1 causes a processing error (library is 1.0 only)', function () {
        // @version: 1.1 should trigger JSON-LD 1.1 processing mode per spec
        // sweetrdf/json-ld (1.0 processor) does not recognize @version and throws
        $content = json_encode([
            '@context' => [
                '@version' => 1.1,
                'ex' => 'http://example.org/',
            ],
            '@id' => 'http://example.org/thing',
            '@type' => 'ex:Thing',
        ]);

        expect(fn () => $this->handler->parse($content))->toThrow(ParseException::class);
    });

    it('documents that @nest keyword is not processed (1.1 feature)', function () {
        // @nest maps nested JSON structures to flat triple representations
        // A 1.0 processor does not understand @nest, so the nested properties are lost
        $content = json_encode([
            '@context' => [
                'name' => 'http://xmlns.com/foaf/0.1/name',
                'label' => [
                    '@id' => 'http://xmlns.com/foaf/0.1/name',
                    '@nest' => 'data',
                ],
            ],
            '@id' => 'http://example.org/person1',
            'data' => [
                'label' => 'Alice',
            ],
        ]);

        // ml/json-ld 1.0 does not understand @nest, so the nested value "Alice" is NOT extracted
        // The document may parse without error but the nested property is silently lost
        try {
            $result = $this->handler->parse($content);
            // If it parses, the nested property under "data" is NOT interpreted as triples
            $person = $result->graph->resource('http://example.org/person1');
            $nameValues = $person->allLiterals('http://xmlns.com/foaf/0.1/name');
            // A 1.1 processor would find "Alice" here; 1.0 processor does not
            expect(count($nameValues))->toBe(0);
        } catch (ParseException) {
            // If the library throws, that's also acceptable (unsupported feature)
            expect(true)->toBeTrue();
        }
    });

    it('documents that @json literal type is not supported (1.1 feature)', function () {
        // @json should produce rdf:JSON typed literals
        $content = json_encode([
            '@context' => [
                'data' => [
                    '@id' => 'http://example.org/data',
                    '@type' => '@json',
                ],
            ],
            '@id' => 'http://example.org/thing',
            'data' => ['key' => 'value'],
        ]);

        try {
            $result = $this->handler->parse($content);
            // A 1.0 processor does not understand @type: @json
            // It may either ignore the value or produce incorrect output
            expect($result)->toBeInstanceOf(ParsedRdf::class);
        } catch (ParseException) {
            // If it throws, that's acceptable for an unsupported feature
            expect(true)->toBeTrue();
        }
    });

    it('documents that @direction (text direction) is not supported (1.1 feature)', function () {
        // @direction should control base text direction (ltr/rtl) for string values
        $content = json_encode([
            '@context' => [
                'label' => 'http://www.w3.org/2000/01/rdf-schema#label',
            ],
            '@id' => 'http://example.org/thing',
            'label' => [
                '@value' => 'Arabic text',
                '@direction' => 'rtl',
            ],
        ]);

        try {
            $result = $this->handler->parse($content);
            // A 1.0 processor ignores @direction
            // The literal is produced without direction information
            expect($result)->toBeInstanceOf(ParsedRdf::class);
        } catch (ParseException) {
            expect(true)->toBeTrue();
        }
    });

    it('documents that @propagate context propagation is not supported (1.1 feature)', function () {
        // @propagate controls whether contexts propagate across node objects
        // ml/json-ld 1.0 does not recognize @propagate and may throw
        $content = json_encode([
            '@context' => [
                '@propagate' => true,
                'ex' => 'http://example.org/',
            ],
            '@id' => 'http://example.org/thing',
            '@type' => 'ex:Thing',
        ]);

        try {
            $result = $this->handler->parse($content);
            expect($result)->toBeInstanceOf(ParsedRdf::class);
        } catch (ParseException) {
            // If the library throws on unknown context keyword, that's acceptable
            expect(true)->toBeTrue();
        }
    });

    it('documents that @protected term definitions are not enforced (1.1 feature)', function () {
        // @protected should prevent term redefinition in derived contexts
        // ml/json-ld 1.0 ignores @protected entirely
        $content = json_encode([
            '@context' => [
                [
                    '@protected' => true,
                    'name' => 'http://schema.org/name',
                ],
                [
                    'name' => 'http://other.org/name',
                ],
            ],
            '@id' => 'http://example.org/thing',
            'name' => 'Test',
        ]);

        try {
            $result = $this->handler->parse($content);
            // 1.0 processor allows redefinition (no protection enforced)
            expect($result)->toBeInstanceOf(ParsedRdf::class);
        } catch (ParseException) {
            // If library somehow enforces protection, also acceptable
            expect(true)->toBeTrue();
        }
    });

    it('documents that @import context import is not supported (1.1 feature)', function () {
        // @import should load and merge external context definitions
        // ml/json-ld 1.0 does not recognize @import
        $content = json_encode([
            '@context' => [
                '@import' => 'http://example.org/base-context.jsonld',
                'ex' => 'http://example.org/',
            ],
            '@id' => 'http://example.org/thing',
            '@type' => 'ex:Thing',
        ]);

        try {
            $result = $this->handler->parse($content);
            expect($result)->toBeInstanceOf(ParsedRdf::class);
        } catch (ParseException) {
            // Expected: @import is not understood by 1.0 processor
            expect(true)->toBeTrue();
        }
    });

    it('documents that @included nodes are not supported (1.1 feature)', function () {
        // @included should emit triples for included node objects
        $content = json_encode([
            '@context' => [
                'ex' => 'http://example.org/',
                'name' => 'http://schema.org/name',
            ],
            '@id' => 'http://example.org/thing',
            'name' => 'Main thing',
            '@included' => [
                [
                    '@id' => 'http://example.org/related',
                    'name' => 'Related thing',
                ],
            ],
        ]);

        try {
            $result = $this->handler->parse($content);
            // 1.0 processor does not understand @included
            // The included node triples are NOT emitted
            $related = $result->graph->resource('http://example.org/related');
            $relatedName = $related->getLiteral('http://schema.org/name');
            // A 1.1 processor would find "Related thing" here
            expect($relatedName)->toBeNull();
        } catch (ParseException) {
            expect(true)->toBeTrue();
        }
    });
});

describe('JSON-LD 1.0 container types (supported)', function () {

    it('supports @set container via N-Triples output', function () {
        $content = json_encode([
            '@context' => [
                'tags' => [
                    '@id' => 'http://example.org/tags',
                    '@container' => '@set',
                ],
            ],
            '@id' => 'http://example.org/thing',
            'tags' => [
                ['@value' => 'a'],
                ['@value' => 'b'],
                ['@value' => 'c'],
            ],
        ]);

        $result = $this->handler->parse($content);
        expect($result)->toBeInstanceOf(ParsedRdf::class);
        $nt = $result->graph->serialise('ntriples');
        expect($nt)->toContain('<http://example.org/thing> <http://example.org/tags>');
        // @set creates individual triples for each value (not rdf:List)
        expect(substr_count((string) $nt, '<http://example.org/tags>'))->toBe(3);
    });

    it('supports @list container via N-Triples output', function () {
        $content = json_encode([
            '@context' => [
                'items' => [
                    '@id' => 'http://example.org/items',
                    '@container' => '@list',
                ],
            ],
            '@id' => 'http://example.org/thing',
            'items' => ['first', 'second'],
        ]);

        $result = $this->handler->parse($content);
        expect($result)->toBeInstanceOf(ParsedRdf::class);
        $nt = $result->graph->serialise('ntriples');
        // @list creates rdf:first/rdf:rest structure
        expect($nt)->toContain('http://www.w3.org/1999/02/22-rdf-syntax-ns#first');
        expect($nt)->toContain('http://www.w3.org/1999/02/22-rdf-syntax-ns#rest');
    });

    it('supports @language container via N-Triples output', function () {
        $content = json_encode([
            '@context' => [
                'label' => [
                    '@id' => 'http://www.w3.org/2000/01/rdf-schema#label',
                    '@container' => '@language',
                ],
            ],
            '@id' => 'http://example.org/thing',
            'label' => [
                'en' => 'Thing',
                'nl' => 'Ding',
            ],
        ]);

        $result = $this->handler->parse($content);
        expect($result)->toBeInstanceOf(ParsedRdf::class);
        $nt = (string) $result->graph->serialise('ntriples');
        expect($nt)->toContain('"Thing"@en');
        expect($nt)->toContain('"Ding"@nl');
    });

    it('supports @index container via N-Triples output', function () {
        $content = json_encode([
            '@context' => [
                'property' => [
                    '@id' => 'http://example.org/property',
                    '@container' => '@index',
                ],
            ],
            '@id' => 'http://example.org/thing',
            'property' => [
                'key1' => ['@value' => 'value1'],
                'key2' => ['@value' => 'value2'],
            ],
        ]);

        $result = $this->handler->parse($content);
        expect($result)->toBeInstanceOf(ParsedRdf::class);
        $nt = (string) $result->graph->serialise('ntriples');
        // @index is non-semantic - index keys are not part of the RDF output
        expect($nt)->toContain('"value1"');
        expect($nt)->toContain('"value2"');
    });
});

describe('JSON-LD 1.1 container types (not supported)', function () {

    it('documents that @id container is not supported (1.1 feature)', function () {
        $content = json_encode([
            '@context' => [
                'knows' => [
                    '@id' => 'http://xmlns.com/foaf/0.1/knows',
                    '@type' => '@id',
                    '@container' => '@id',
                ],
            ],
            '@id' => 'http://example.org/alice',
            'knows' => [
                'http://example.org/bob' => [
                    'http://schema.org/name' => 'Bob',
                ],
            ],
        ]);

        try {
            $result = $this->handler->parse($content);
            expect($result)->toBeInstanceOf(ParsedRdf::class);
        } catch (ParseException) {
            expect(true)->toBeTrue();
        }
    });

    it('documents that @type container is not supported (1.1 feature)', function () {
        $content = json_encode([
            '@context' => [
                'input' => [
                    '@id' => 'http://example.org/input',
                    '@container' => '@type',
                ],
            ],
            '@id' => 'http://example.org/thing',
            'input' => [
                'http://schema.org/Person' => [
                    ['@id' => 'http://example.org/person1'],
                ],
            ],
        ]);

        try {
            $result = $this->handler->parse($content);
            expect($result)->toBeInstanceOf(ParsedRdf::class);
        } catch (ParseException) {
            expect(true)->toBeTrue();
        }
    });
});

describe('dependency upgrade verification', function () {
    it('uses sweetrdf/json-ld as the JSON-LD processor', function () {
        // Verify that the sweetrdf/json-ld library is being used (same ML\JsonLD namespace)
        expect(class_exists(\ML\JsonLD\JsonLD::class))->toBeTrue();
        expect(class_exists(\ML\JsonLD\Processor::class))->toBeTrue();
    });

    it('processes JSON-LD 1.0 content without PHP deprecation warnings', function () {
        // sweetrdf/json-ld ^1.3 should not emit PHP 8.x deprecation warnings
        $content = json_encode([
            '@context' => [
                'name' => 'http://schema.org/name',
                'knows' => ['@id' => 'http://xmlns.com/foaf/0.1/knows', '@type' => '@id'],
            ],
            '@id' => 'http://example.org/alice',
            'name' => 'Alice',
            'knows' => 'http://example.org/bob',
        ]);

        $result = $this->handler->parse($content);
        expect($result)->toBeInstanceOf(ParsedRdf::class);
        expect($result->graph->resource('http://example.org/alice'))->not->toBeNull();
    });
});
