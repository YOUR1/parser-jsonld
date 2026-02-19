<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserCore\Exceptions\ParseException;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;
use Youri\vandenBogert\Software\ParserJsonLd\JsonLdHandler;
use EasyRdf\RdfNamespace;

describe('JsonLdHandler', function () {
    beforeEach(function () {
        $this->handler = new JsonLdHandler();
    });

    describe('canHandle()', function () {
        // --- True cases ---

        it('returns true for JSON with @context (standard JSON-LD)', function () {
            $content = '{"@context": {"ex": "http://example.org/"}, "@id": "http://example.org/thing"}';
            expect($this->handler->canHandle($content))->toBeTrue();
        });

        it('returns true for content with leading whitespace before {', function () {
            $content = "  \n\t  " . '{"@context": {"ex": "http://example.org/"}}';
            expect($this->handler->canHandle($content))->toBeTrue();
        });

        it('returns true for content with trailing whitespace after }', function () {
            $content = '{"@context": {"ex": "http://example.org/"}}' . "  \n\t  ";
            expect($this->handler->canHandle($content))->toBeTrue();
        });

        it('returns true for content with @context nested deeper in JSON (false positive — key at wrong level)', function () {
            $content = '{"data": {"@context": "http://example.org/"}, "other": "value"}';
            expect($this->handler->canHandle($content))->toBeTrue();
        });

        it('returns true for content with @context only in a string value (false positive — str_contains matches anywhere)', function () {
            $content = '{"description": "This mentions @context in text but has no real JSON-LD context"}';
            expect($this->handler->canHandle($content))->toBeTrue();
        });

        it('returns true for invalid JSON that starts with { and contains @context', function () {
            $content = '{broken json @context here}';
            expect($this->handler->canHandle($content))->toBeTrue();
        });

        // --- False cases ---

        it('returns false for empty string', function () {
            expect($this->handler->canHandle(''))->toBeFalse();
        });

        it('returns false for whitespace-only content', function () {
            expect($this->handler->canHandle("   \n\t  "))->toBeFalse();
        });

        it('returns false for Turtle content', function () {
            $content = '@prefix ex: <http://example.org/> . ex:Thing a rdfs:Class .';
            expect($this->handler->canHandle($content))->toBeFalse();
        });

        it('returns false for RDF/XML content', function () {
            $content = '<?xml version="1.0"?><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"></rdf:RDF>';
            expect($this->handler->canHandle($content))->toBeFalse();
        });

        it('returns false for plain text content', function () {
            expect($this->handler->canHandle('This is just plain text'))->toBeFalse();
        });

        it('returns false for N-Triples content', function () {
            $content = '<http://example.org/subject> <http://example.org/predicate> <http://example.org/object> .';
            expect($this->handler->canHandle($content))->toBeFalse();
        });

        it('returns false for JSON array starting with [', function () {
            $content = '[{"@context": {"ex": "http://example.org/"}}]';
            expect($this->handler->canHandle($content))->toBeFalse();
        });

        it('returns false for JSON without @context', function () {
            $content = '{"name": "foo", "value": 42}';
            expect($this->handler->canHandle($content))->toBeFalse();
        });

        it('returns false for empty JSON object {}', function () {
            expect($this->handler->canHandle('{}'))->toBeFalse();
        });

        it('returns false for @Context with uppercase C (case-sensitive)', function () {
            $content = '{"@Context": {"ex": "http://example.org/"}}';
            expect($this->handler->canHandle($content))->toBeFalse();
        });

        it('returns false for HTML content', function () {
            $content = '<html><body>@context</body></html>';
            expect($this->handler->canHandle($content))->toBeFalse();
        });
    });

    describe('parse()', function () {
        it('returns ParsedRdf instance for valid JSON-LD with a class declaration', function () {
            $content = json_encode([
                '@context' => ['rdfs' => 'http://www.w3.org/2000/01/rdf-schema#', 'owl' => 'http://www.w3.org/2002/07/owl#'],
                '@id' => 'http://example.org/MyClass',
                '@type' => 'owl:Class',
                'rdfs:label' => 'My Class',
            ]);
            $result = $this->handler->parse($content);
            expect($result)->toBeInstanceOf(ParsedRdf::class);
        });

        it('has correct format property (json-ld)', function () {
            $content = json_encode([
                '@context' => ['rdfs' => 'http://www.w3.org/2000/01/rdf-schema#'],
                '@id' => 'http://example.org/A',
                '@type' => 'rdfs:Class',
            ]);
            $result = $this->handler->parse($content);
            expect($result->format)->toBe('json-ld');
        });

        it('has correct rawContent property (original input preserved)', function () {
            $content = json_encode([
                '@context' => ['rdfs' => 'http://www.w3.org/2000/01/rdf-schema#'],
                '@id' => 'http://example.org/A',
                '@type' => 'rdfs:Class',
            ]);
            $result = $this->handler->parse($content);
            expect($result->rawContent)->toBe($content);
        });

        it('has metadata with required keys: parser, format, resource_count, context', function () {
            $content = json_encode([
                '@context' => ['rdfs' => 'http://www.w3.org/2000/01/rdf-schema#'],
                '@id' => 'http://example.org/A',
                '@type' => 'rdfs:Class',
            ]);
            $result = $this->handler->parse($content);
            expect($result->metadata)->toHaveKeys(['parser', 'format', 'resource_count', 'context']);
            expect($result->metadata)->toHaveCount(4);
        });

        it('has metadata parser = jsonld_handler', function () {
            $content = json_encode([
                '@context' => ['rdfs' => 'http://www.w3.org/2000/01/rdf-schema#'],
                '@id' => 'http://example.org/A',
                '@type' => 'rdfs:Class',
            ]);
            $result = $this->handler->parse($content);
            expect($result->metadata['parser'])->toBe('jsonld_handler');
        });

        it('has metadata format = json-ld', function () {
            $content = json_encode([
                '@context' => ['rdfs' => 'http://www.w3.org/2000/01/rdf-schema#'],
                '@id' => 'http://example.org/A',
                '@type' => 'rdfs:Class',
            ]);
            $result = $this->handler->parse($content);
            expect($result->metadata['format'])->toBe('json-ld');
        });

        it('has metadata resource_count as integer matching graph resources count', function () {
            $content = json_encode([
                '@context' => ['rdfs' => 'http://www.w3.org/2000/01/rdf-schema#'],
                '@id' => 'http://example.org/A',
                '@type' => 'rdfs:Class',
            ]);
            $result = $this->handler->parse($content);
            expect($result->metadata['resource_count'])->toBeInt();
            expect($result->metadata['resource_count'])->toBe(count($result->graph->resources()));
        });

        it('has metadata context preserving the @context value from input', function () {
            $context = ['rdfs' => 'http://www.w3.org/2000/01/rdf-schema#'];
            $content = json_encode([
                '@context' => $context,
                '@id' => 'http://example.org/A',
                '@type' => 'rdfs:Class',
            ]);
            $result = $this->handler->parse($content);
            expect($result->metadata['context'])->toBe($context);
        });

        it('preserves @context as a string URL (non-resolvable triggers Throwable wrapping)', function () {
            // String URL contexts cause ml-json-ld to attempt resolution.
            // Non-resolvable URLs trigger the Throwable catch → wrapped ParseException.
            // This documents that string context metadata preservation requires successful URL resolution.
            $content = json_encode([
                '@context' => 'urn:example:non-resolvable-context',
                '@type' => 'Thing',
                '@id' => 'http://example.org/item1',
            ]);
            try {
                $result = $this->handler->parse($content);
                // If ml-json-ld resolves it, metadata preserves the string
                expect($result->metadata['context'])->toBe('urn:example:non-resolvable-context');
                expect($result->metadata['context'])->toBeString();
            } catch (ParseException $e) {
                // Non-resolvable string contexts hit the Throwable catch block
                expect($e->getMessage())->toStartWith('JSON-LD parsing failed:');
                expect($e->getPrevious())->not->toBeNull();
            }
        });

        it('preserves @context as an object with prefix mappings', function () {
            $context = [
                'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
                'owl' => 'http://www.w3.org/2002/07/owl#',
                'ex' => 'http://example.org/',
            ];
            $content = json_encode([
                '@context' => $context,
                '@id' => 'http://example.org/A',
                '@type' => 'owl:Class',
            ]);
            $result = $this->handler->parse($content);
            expect($result->metadata['context'])->toBe($context);
            expect($result->metadata['context'])->toBeArray();
        });

        it('preserves @context as an array of mixed inline contexts', function () {
            $context = [
                ['ex' => 'http://example.org/'],
                ['rdfs' => 'http://www.w3.org/2000/01/rdf-schema#'],
            ];
            $content = json_encode([
                '@context' => $context,
                '@id' => 'http://example.org/item1',
                '@type' => 'rdfs:Class',
            ]);
            $result = $this->handler->parse($content);
            expect($result->metadata['context'])->toBe($context);
            expect($result->metadata['context'])->toBeArray();
        });

        it('correctly parses JSON-LD with @type class declaration and graph contains the class resource', function () {
            $content = json_encode([
                '@context' => ['owl' => 'http://www.w3.org/2002/07/owl#', 'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#'],
                '@id' => 'http://example.org/Animal',
                '@type' => 'owl:Class',
                'rdfs:label' => 'Animal',
            ]);
            $result = $this->handler->parse($content);
            $resourceUris = array_map(fn ($r) => (string) $r, $result->graph->resources());
            expect($resourceUris)->toContain('http://example.org/Animal');
        });

        it('correctly parses JSON-LD with properties (@id, typed values)', function () {
            $content = json_encode([
                '@context' => [
                    'ex' => 'http://example.org/',
                    'xsd' => 'http://www.w3.org/2001/XMLSchema#',
                ],
                '@id' => 'http://example.org/person1',
                'ex:name' => 'Alice',
                'ex:age' => ['@value' => '30', '@type' => 'xsd:integer'],
            ]);
            $result = $this->handler->parse($content);
            $resourceUris = array_map(fn ($r) => (string) $r, $result->graph->resources());
            expect($resourceUris)->toContain('http://example.org/person1');
        });

        it('correctly parses JSON-LD with multiple resources in @graph', function () {
            $content = json_encode([
                '@context' => ['ex' => 'http://example.org/', 'owl' => 'http://www.w3.org/2002/07/owl#'],
                '@graph' => [
                    ['@id' => 'http://example.org/ClassA', '@type' => 'owl:Class'],
                    ['@id' => 'http://example.org/ClassB', '@type' => 'owl:Class'],
                ],
            ]);
            $result = $this->handler->parse($content);
            $resourceUris = array_map(fn ($r) => (string) $r, $result->graph->resources());
            expect($resourceUris)->toContain('http://example.org/ClassA');
            expect($resourceUris)->toContain('http://example.org/ClassB');
        });

        it('correctly parses JSON-LD with language-tagged values', function () {
            $content = json_encode([
                '@context' => ['rdfs' => 'http://www.w3.org/2000/01/rdf-schema#'],
                '@id' => 'http://example.org/concept',
                'rdfs:label' => ['@value' => 'Hallo', '@language' => 'de'],
            ]);
            $result = $this->handler->parse($content);
            expect($result)->toBeInstanceOf(ParsedRdf::class);
            $resourceUris = array_map(fn ($r) => (string) $r, $result->graph->resources());
            expect($resourceUris)->toContain('http://example.org/concept');
        });

        it('correctly parses JSON-LD with typed literals', function () {
            $content = json_encode([
                '@context' => [
                    'ex' => 'http://example.org/',
                    'xsd' => 'http://www.w3.org/2001/XMLSchema#',
                ],
                '@id' => 'http://example.org/item',
                'ex:count' => ['@value' => '42', '@type' => 'xsd:integer'],
            ]);
            $result = $this->handler->parse($content);
            expect($result)->toBeInstanceOf(ParsedRdf::class);
        });

        it('parses minimal valid JSON-LD with empty context', function () {
            $content = '{"@context": {}}';
            $result = $this->handler->parse($content);
            expect($result)->toBeInstanceOf(ParsedRdf::class);
            expect($result->metadata['context'])->toBe([]);
        });
    });

    describe('getFormatName()', function () {
        it('returns json-ld', function () {
            expect($this->handler->getFormatName())->toBe('json-ld');
        });

        it('returns a string type', function () {
            expect($this->handler->getFormatName())->toBeString();
        });
    });

    describe('error handling', function () {
        it('throws ParseException for invalid JSON with Invalid JSON message (no prefix, re-thrown)', function () {
            try {
                $this->handler->parse('{invalid json}');
                $this->fail('Expected ParseException');
            } catch (ParseException $e) {
                expect($e->getMessage())->toStartWith('Invalid JSON:');
                expect($e->getMessage())->not->toStartWith('JSON-LD parsing failed:');
                expect($e->getPrevious())->toBeNull();
            }
        });

        it('throws ParseException with no $previous for invalid JSON', function () {
            try {
                $this->handler->parse('not valid json at all');
                $this->fail('Expected ParseException');
            } catch (ParseException $e) {
                expect($e->getPrevious())->toBeNull();
            }
        });

        it('throws ParseException for JSON without @context with Missing @context message (no prefix, re-thrown)', function () {
            try {
                $this->handler->parse('{"name": "test"}');
                $this->fail('Expected ParseException');
            } catch (ParseException $e) {
                expect($e->getMessage())->toBe('Missing @context in JSON-LD');
                expect($e->getMessage())->not->toStartWith('JSON-LD parsing failed:');
            }
        });

        it('throws ParseException with no $previous for missing @context', function () {
            try {
                $this->handler->parse('{"name": "test"}');
                $this->fail('Expected ParseException');
            } catch (ParseException $e) {
                expect($e->getPrevious())->toBeNull();
            }
        });

        it('throws for @context as empty string — isset returns true but ml-json-ld fails (Throwable wrapped)', function () {
            // @context as "" passes isset() but causes ml-json-ld recursive context error
            // This hits the Throwable catch, NOT the ParseException catch — demonstrating the two-catch distinction
            try {
                $this->handler->parse('{"@context": ""}');
                $this->fail('Expected ParseException');
            } catch (ParseException $e) {
                expect($e->getMessage())->toStartWith('JSON-LD parsing failed:');
                expect($e->getPrevious())->not->toBeNull();
            }
        });

        it('throws for @context null because isset returns false for null', function () {
            try {
                $this->handler->parse('{"@context": null}');
                $this->fail('Expected ParseException');
            } catch (ParseException $e) {
                expect($e->getMessage())->toBe('Missing @context in JSON-LD');
                expect($e->getPrevious())->toBeNull();
            }
        });

        it('wraps EasyRdf/ml-json-ld failures with JSON-LD parsing failed prefix and $previous set', function () {
            // Empty string @context passes isset() but causes ml-json-ld to throw
            // "Recursive inclusion of remote context" — caught by Throwable catch block
            $content = json_encode(['@context' => '']);
            try {
                $this->handler->parse($content);
                $this->fail('Expected ParseException from Throwable wrapping');
            } catch (ParseException $e) {
                expect($e->getMessage())->toStartWith('JSON-LD parsing failed:');
                expect($e->getMessage())->not->toStartWith('Invalid JSON:');
                expect($e->getMessage())->not->toBe('Missing @context in JSON-LD');
                expect($e->getPrevious())->not->toBeNull();
                expect($e->getCode())->toBe(0);
            }
        });

        it('has exception code 0 for invalid JSON error', function () {
            try {
                $this->handler->parse('{bad json}');
                $this->fail('Expected ParseException');
            } catch (ParseException $e) {
                expect($e->getCode())->toBe(0);
            }
        });

        it('has exception code 0 for missing @context error', function () {
            try {
                $this->handler->parse('{"no_context": true}');
                $this->fail('Expected ParseException');
            } catch (ParseException $e) {
                expect($e->getCode())->toBe(0);
            }
        });

        it('throws for empty string with Invalid JSON message', function () {
            try {
                $this->handler->parse('');
                $this->fail('Expected ParseException');
            } catch (ParseException $e) {
                expect($e->getMessage())->toStartWith('Invalid JSON:');
                expect($e->getPrevious())->toBeNull();
            }
        });

        it('demonstrates canHandle/parse gap: content passes canHandle but fails parse', function () {
            $content = '{broken json @context}';
            expect($this->handler->canHandle($content))->toBeTrue();
            expect(fn () => $this->handler->parse($content))->toThrow(ParseException::class);
        });
    });

    describe('prefix registration side effects', function () {
        it('does not change EasyRdf namespace count after parse', function () {
            $beforeCount = count(RdfNamespace::namespaces());

            $content = json_encode([
                '@context' => [
                    'ex' => 'http://example.org/',
                    'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
                ],
                '@id' => 'http://example.org/A',
                '@type' => 'rdfs:Class',
            ]);
            $this->handler->parse($content);

            $afterCount = count(RdfNamespace::namespaces());
            expect($afterCount)->toBe($beforeCount);
        });

        it('keeps standard prefixes available after parsing', function () {
            $content = json_encode([
                '@context' => ['ex' => 'http://example.org/'],
                '@id' => 'http://example.org/thing',
            ]);
            $this->handler->parse($content);

            $namespaces = RdfNamespace::namespaces();
            expect($namespaces)->toHaveKey('rdf');
            expect($namespaces)->toHaveKey('rdfs');
            expect($namespaces)->toHaveKey('owl');
            expect($namespaces)->toHaveKey('xsd');
        });

        it('confirms JsonLdHandler has no registerPrefixesFromContent method', function () {
            $reflection = new ReflectionClass(JsonLdHandler::class);
            expect($reflection->hasMethod('registerPrefixesFromContent'))->toBeFalse();
        });
    });
});
