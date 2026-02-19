<?php

declare(strict_types=1);

namespace Youri\vandenBogert\Software\ParserJsonLd;

use EasyRdf\Graph;
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
    public function canHandle(string $content): bool
    {
        $trimmed = trim($content);

        if (! str_starts_with($trimmed, '{')) {
            return false;
        }

        return str_contains($trimmed, '@context');
    }

    public function parse(string $content): ParsedRdf
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

            $graph = new Graph();
            $graph->parse($content, 'jsonld');

            $metadata = [
                'parser' => 'jsonld_handler',
                'format' => 'json-ld',
                'resource_count' => count($graph->resources()),
                'context' => $decoded['@context'] ?? null,
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

    public function getFormatName(): string
    {
        return 'json-ld';
    }
}
