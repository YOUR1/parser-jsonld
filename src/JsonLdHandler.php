<?php

declare(strict_types=1);

namespace Youri\vandenBogert\Software\ParserJsonLd;

use EasyRdf\Graph;
use ML\JsonLD\JsonLD;
use ML\JsonLD\LanguageTaggedString;
use ML\JsonLD\Quad;
use Youri\vandenBogert\Software\ParserCore\Contracts\RdfFormatHandlerInterface;
use Youri\vandenBogert\Software\ParserCore\Exceptions\ParseException;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;

/**
 * Handler for JSON-LD format parsing.
 *
 * Detects and parses JSON-LD 1.1 content using EasyRdf with ml/json-ld,
 * returning a ParsedRdf value object. Validates JSON structure and @context
 * presence before delegating to EasyRdf for graph construction.
 */
final class JsonLdHandler implements RdfFormatHandlerInterface
{
    /**
     * JSON-LD keywords used for format detection.
     *
     * These are the keywords that, when found at the top level of a JSON object
     * (or first element of a JSON array), indicate the content is JSON-LD.
     *
     * @var list<string>
     */
    private const JSONLD_KEYWORDS = [
        '@context',
        '@id',
        '@type',
        '@graph',
        '@value',
        '@list',
        '@set',
        '@reverse',
        '@language',
    ];

    public function canHandle(string $content): bool
    {
        $trimmed = trim($content);

        if ($trimmed === '' || ($trimmed[0] !== '{' && $trimmed[0] !== '[')) {
            return false;
        }

        $decoded = json_decode($trimmed, true);
        if (! is_array($decoded)) {
            return false;
        }

        // For arrays, inspect the first element
        if (array_is_list($decoded)) {
            if ($decoded === []) {
                return false;
            }
            $inspectTarget = $decoded[0];
            if (! is_array($inspectTarget)) {
                return false;
            }
        } else {
            $inspectTarget = $decoded;
        }

        return $this->hasJsonLdSignals($inspectTarget);
    }

    /**
     * Check if an associative array has JSON-LD signals (keywords or full IRI keys).
     *
     * @param array<string, mixed> $data
     */
    private function hasJsonLdSignals(array $data): bool
    {
        /** @var array<string> $keys */
        $keys = array_keys($data);

        // Check for JSON-LD keywords
        foreach ($keys as $key) {
            if (in_array($key, self::JSONLD_KEYWORDS, true)) {
                return true;
            }
        }

        // Check for full IRI keys (http:// or https://)
        foreach ($keys as $key) {
            if (preg_match('#^https?://#', $key) === 1) {
                return true;
            }
        }

        return false;
    }

    public function parse(string $content): ParsedRdf
    {
        return $this->parseWithOptions($content, []);
    }

    /**
     * Parse JSON-LD content with optional configuration.
     *
     * Supported options:
     * - 'base' (string): Base URI for resolving relative IRIs
     * - 'disableRemoteContexts' (bool): When true, prevents fetching remote @context URLs (security, NFR3)
     *
     * @param array<string, mixed> $options
     */
    public function parseWithOptions(string $content, array $options = []): ParsedRdf
    {
        try {
            // Validate JSON structure
            $decoded = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new ParseException('Invalid JSON: ' . json_last_error_msg());
            }

            if (! isset($decoded['@context'])) {
                throw new ParseException('Missing @context in JSON-LD');
            }

            // Check for remote context URLs when disableRemoteContexts is enabled
            $disableRemote = isset($options['disableRemoteContexts']) && $options['disableRemoteContexts'] === true;
            if ($disableRemote) {
                $this->assertNoRemoteContexts($decoded['@context']);
            }

            $baseUri = isset($options['base']) && is_string($options['base']) ? $options['base'] : null;

            $graph = $baseUri !== null ? new Graph($baseUri) : new Graph();
            $graph->parse($content, 'jsonld', $baseUri);

            // Extract named graph quads directly from ml/json-ld
            $namedGraphs = $this->extractNamedGraphs($content, $baseUri);

            $metadata = [
                'parser' => 'jsonld_handler',
                'format' => 'json-ld',
                'resource_count' => count($graph->resources()),
                'context' => $decoded['@context'] ?? null,
                'named_graphs' => $namedGraphs,
            ];

            return new ParsedRdf(
                graph: $graph,
                format: 'json-ld',
                rawContent: $content,
                metadata: $metadata,
            );

        } catch (ParseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ParseException('JSON-LD parsing failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Assert that a @context value does not reference any remote URLs.
     *
     * Throws ParseException if a remote context URL (http:// or https://) is detected.
     * This is a security feature (NFR3) to prevent SSRF and data exfiltration.
     *
     * @param mixed $context The decoded @context value from the JSON-LD document
     *
     * @throws ParseException If a remote context URL is found
     */
    private function assertNoRemoteContexts(mixed $context): void
    {
        if (is_string($context)) {
            if (preg_match('#^https?://#', $context) === 1) {
                throw new ParseException(
                    "Remote context resolution is disabled ('{$context}' cannot be fetched)"
                );
            }

            return;
        }

        if (is_array($context)) {
            // In a JSON-LD @context array, only top-level string entries that are
            // HTTP(S) URLs are remote context references. Associative arrays
            // (inline context definitions like {"ex": "http://example.org/"})
            // are NOT remote contexts. We only check list items (numeric keys).
            if (array_is_list($context)) {
                foreach ($context as $item) {
                    if (is_string($item) && preg_match('#^https?://#', $item) === 1) {
                        throw new ParseException(
                            "Remote context resolution is disabled ('{$item}' cannot be fetched)"
                        );
                    }
                }
            }
            // If context is an associative array, it's an inline context definition - no remote URLs
        }
    }

    /**
     * Extract named graph quads from JSON-LD content using ml/json-ld directly.
     *
     * @return array<string, list<array{subject: string, predicate: string, object: string|array{value: string, type?: string, language?: string}}>>
     */
    private function extractNamedGraphs(string $content, ?string $baseUri): array
    {
        try {
            $toRdfOptions = [];
            if ($baseUri !== null) {
                $toRdfOptions['base'] = $baseUri;
            }

            /** @var Quad[] $quads */
            $quads = JsonLD::toRdf($content, $toRdfOptions);

            $namedGraphs = [];
            foreach ($quads as $quad) {
                /** @var \ML\IRI\IRI|null $graphIri Quad::getGraph() returns null for default graph despite PHPDoc */
                $graphIri = $quad->getGraph();
                if ($graphIri === null) {
                    continue; // Default graph, already handled by EasyRdf
                }

                $graphUri = (string) $graphIri;
                $subject = (string) $quad->getSubject();
                $predicate = (string) $quad->getProperty();

                $object = $quad->getObject();
                if ($object instanceof \ML\IRI\IRI) {
                    $objectValue = (string) $object;
                } elseif ($object instanceof LanguageTaggedString) {
                    $objectValue = [
                        'value' => $object->getValue(),
                        'language' => $object->getLanguage(),
                    ];
                } else {
                    /** @var \ML\JsonLD\TypedValue $object */
                    $objectValue = [
                        'value' => $object->getValue(),
                        'type' => $object->getType(),
                    ];
                }

                if (! isset($namedGraphs[$graphUri])) {
                    $namedGraphs[$graphUri] = [];
                }

                $namedGraphs[$graphUri][] = [
                    'subject' => $subject,
                    'predicate' => $predicate,
                    'object' => $objectValue,
                ];
            }

            return $namedGraphs;
        } catch (\Throwable) {
            // If named graph extraction fails, return empty
            // The main parse via EasyRdf already succeeded
            return [];
        }
    }

    public function getFormatName(): string
    {
        return 'json-ld';
    }
}
