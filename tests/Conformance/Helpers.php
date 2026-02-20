<?php

/**
 * Shared helper functions for W3C JSON-LD conformance tests.
 *
 * This file is included by both positive and negative test files.
 * All functions use function_exists() guards to prevent redeclaration.
 */

declare(strict_types=1);

if (!function_exists('parseToRdfManifest')) {
    function parseToRdfManifest(string $manifestPath): array
    {
        $json = file_get_contents($manifestPath);
        if ($json === false) {
            throw new RuntimeException("Failed to read manifest: {$manifestPath}");
        }

        $manifest = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $tests = [];
        foreach ($manifest['sequence'] as $entry) {
            $types = $entry['@type'] ?? [];
            $tests[] = [
                'id' => $entry['@id'] ?? '',
                'name' => $entry['name'] ?? '',
                'purpose' => $entry['purpose'] ?? '',
                'types' => $types,
                'input' => $entry['input'] ?? null,
                'expect' => $entry['expect'] ?? null,
                'expectErrorCode' => $entry['expectErrorCode'] ?? null,
                'specVersion' => $entry['option']['specVersion'] ?? null,
                'processingMode' => $entry['option']['processingMode'] ?? null,
                'produceGeneralizedRdf' => $entry['option']['produceGeneralizedRdf'] ?? false,
                'rdfDirection' => $entry['option']['rdfDirection'] ?? null,
            ];
        }

        return $tests;
    }
}

if (!function_exists('w3cFixturePath')) {
    function w3cFixturePath(string $relativePath): string
    {
        return dirname(__DIR__) . '/Fixtures/W3c/' . $relativePath;
    }
}

if (!function_exists('w3cFixture')) {
    function w3cFixture(string $relativePath): string
    {
        $path = w3cFixturePath($relativePath);
        if (!file_exists($path)) {
            throw new RuntimeException("Fixture not found: {$path}");
        }

        return file_get_contents($path);
    }
}

if (!function_exists('hasTopLevelContext')) {
    function hasTopLevelContext(string $jsonContent): bool
    {
        $decoded = json_decode($jsonContent, true);
        if (!is_array($decoded)) {
            return false;
        }

        return array_key_exists('@context', $decoded);
    }
}

if (!function_exists('hasRemoteContext')) {
    function hasRemoteContext(string $jsonContent): bool
    {
        $decoded = json_decode($jsonContent, true);
        if (!is_array($decoded)) {
            return false;
        }

        if (!isset($decoded['@context'])) {
            return false;
        }

        $context = $decoded['@context'];

        // String context that looks like a URL
        if (is_string($context) && preg_match('#^https?://#', $context)) {
            return true;
        }

        // Array of contexts -- check if any element is a remote URL string
        if (is_array($context)) {
            foreach ($context as $item) {
                if (is_string($item) && preg_match('#^https?://#', $item)) {
                    return true;
                }
            }
        }

        return false;
    }
}

if (!function_exists('normalizeNTriples')) {
    /**
     * Normalize N-Triples/N-Quads output for comparison.
     * Returns sorted array of non-empty trimmed lines.
     * Strips explicit ^^xsd:string from plain literals (RDF 1.1 equivalence).
     */
    function normalizeNTriples(string $nt): array
    {
        // Strip explicit xsd:string datatype from plain literals
        // In RDF 1.1, "value"^^<xsd:string> is equivalent to "value"
        $nt = stripXsdString($nt);

        $lines = explode("\n", $nt);
        $lines = array_map('trim', $lines);
        $lines = array_filter($lines, fn(string $line) => $line !== '' && !str_starts_with($line, '#'));
        $lines = array_values($lines);
        sort($lines);

        return $lines;
    }
}

if (!function_exists('stripXsdString')) {
    /**
     * Strip explicit ^^<http://www.w3.org/2001/XMLSchema#string> from N-Triples literals.
     *
     * Converts "value"^^<http://www.w3.org/2001/XMLSchema#string> to "value".
     * This normalizes RDF 1.1 plain literal equivalence for comparison.
     * Language-tagged literals and other datatypes are not affected.
     */
    function stripXsdString(string $ntriples): string
    {
        return str_replace(
            '"^^<http://www.w3.org/2001/XMLSchema#string>',
            '"',
            $ntriples
        );
    }
}

if (!function_exists('isNamedGraphQuad')) {
    /**
     * Check if an N-Quads line belongs to a named graph (has 4 terms before the dot).
     * A default graph triple has format: <s> <p> <o> .
     * A named graph quad has format: <s> <p> <o> <g> .
     */
    function isNamedGraphQuad(string $line): bool
    {
        $trimmed = trim($line);
        if (!str_ends_with($trimmed, ' .')) {
            return false;
        }
        $withoutDot = substr($trimmed, 0, -2);

        // Count top-level terms (not inside quotes or angle brackets)
        $termCount = 0;
        $inUri = false;
        $inLiteral = false;
        $escaped = false;
        $afterLiteral = false; // tracks if we just closed a literal (for ^^ and @lang handling)

        for ($i = 0, $len = strlen($withoutDot); $i < $len; $i++) {
            $ch = $withoutDot[$i];

            if ($escaped) {
                $escaped = false;
                continue;
            }

            if ($ch === '\\') {
                $escaped = true;
                continue;
            }

            if ($ch === '<' && !$inLiteral) {
                if (!$inUri && !$afterLiteral) {
                    // Only count as new term if not a datatype URI after a literal
                    $termCount++;
                }
                $inUri = true;
                continue;
            }

            if ($ch === '>' && !$inLiteral) {
                $inUri = false;
                continue;
            }

            if ($ch === '"' && !$inUri) {
                if (!$inLiteral) {
                    // Opening quote - new literal term
                    $inLiteral = true;
                    $termCount++;
                    $afterLiteral = false;
                } else {
                    // Closing quote
                    $inLiteral = false;
                    $afterLiteral = true;
                }
                continue;
            }

            if ($ch === '_' && !$inUri && !$inLiteral && $i + 1 < $len && $withoutDot[$i + 1] === ':') {
                $termCount++;
                $afterLiteral = false;
                // skip past the bnode label
                while ($i < $len && $withoutDot[$i] !== ' ') {
                    $i++;
                }
                $i--; // will be incremented by for loop
                continue;
            }

            // Reset afterLiteral when we hit a space (term boundary) - but only if not in a URI or literal
            if ($ch === ' ' && !$inUri && !$inLiteral) {
                $afterLiteral = false;
            }
        }

        return $termCount >= 4;
    }
}

if (!function_exists('containsBlankNode')) {
    /**
     * Check if an N-Triples line contains a blank node (subject or object).
     * Only matches _: outside of quoted strings to avoid false positives
     * on literal values containing the text "_:".
     */
    function containsBlankNode(string $line): bool
    {
        // Match _: only outside of quoted strings
        $inQuote = false;
        $escaped = false;
        $len = strlen($line);

        for ($i = 0; $i < $len; $i++) {
            $ch = $line[$i];

            if ($escaped) {
                $escaped = false;
                continue;
            }

            if ($ch === '\\') {
                $escaped = true;
                continue;
            }

            if ($ch === '"') {
                $inQuote = !$inQuote;
                continue;
            }

            if (!$inQuote && $ch === '_' && $i + 1 < $len && $line[$i + 1] === ':') {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('filterDefaultGraphTriples')) {
    /**
     * Filter expected .nq output to only default graph triples.
     */
    function filterDefaultGraphTriples(array $lines): array
    {
        return array_values(array_filter($lines, fn(string $line) => !isNamedGraphQuad($line)));
    }
}
