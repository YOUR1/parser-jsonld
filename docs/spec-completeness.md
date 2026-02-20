# Spec Completeness

> Assessment of parser-jsonld implementation coverage against W3C JSON-LD 1.1.
> Last updated: 2026-02-20 (after Epic 11 completion)

## Scope

This library provides a single **JSON-LD format handler** (`JsonLdHandler`) that detects and parses
JSON-LD content into a `ParsedRdf` value object. It implements the `RdfFormatHandlerInterface` contract
from `parser-core`.

Actual JSON-LD-to-RDF conversion is delegated to **EasyRdf** + **sweetrdf/json-ld ^1.3** (a maintained
fork of ml/json-ld, same `ML\JsonLD\` namespace), which implements the JSON-LD 1.0 Processing Algorithms.
JSON-LD 1.1 features are therefore only supported to the extent that sweetrdf/json-ld handles them.

Reference: [W3C JSON-LD 1.1](https://www.w3.org/TR/json-ld11/)

## Summary

| Spec Area | Implemented | Total | Coverage |
|---|---|---|---|
| Handler Interface Contract | 3 | 3 | 100% |
| Format Detection | 5 | 5 | 100% |
| JSON-LD Keywords (1.0 core) | 11 | 11 | 100% |
| JSON-LD Keywords (1.1 additions) | 0 | 10 | 0% |
| Context Handling | 6 | 7 | 86% |
| Parsing to ParsedRdf | 6 | 6 | 100% |
| Error Handling | 4 | 4 | 100% |
| W3C Conformance -- toRdf Positive | 79 | 345 | 23% |
| W3C Conformance -- toRdf Negative | 90 | 106 | 85% |
| W3C Conformance -- Positive Syntax | 0 | 16 | 0% |
| **Overall (handler code)** | | | **~95%** |
| **Overall (W3C toRdf suite)** | | | **~36%** |

---

## Handler Interface Contract

Reference: `parser-core` `RdfFormatHandlerInterface`

| Feature | Status | Location | Tests |
|---|---|---|---|
| `canHandle(string $content): bool` | implemented | `JsonLdHandler:44-71` | `CanHandleDetectionTest` (23 cases), `Characterization` (17 cases) |
| `parse(string $content): ParsedRdf` | implemented | `JsonLdHandler:100-103` (delegates to `parseWithOptions`) | `Characterization` (17 cases), `Unit` (4 cases) |
| `parseWithOptions(string $content, array $options): ParsedRdf` | implemented | `JsonLdHandler:114-161` | `BaseUriAndXsdStringTest` (8 cases), `RemoteContextTest` (8 cases), `NamedGraphSupportTest` (6 cases) |
| `getFormatName(): string` | implemented | `JsonLdHandler:266-269` | `Unit:17-19`, `Characterization` |

---

## Format Detection (`canHandle`)

Reference: [JSON-LD 1.1 -- Section 9 (JSON-LD Grammar)](https://www.w3.org/TR/json-ld11/#json-ld-grammar)

| Feature | Status | Location | Tests |
|---|---|---|---|
| Detects JSON object starting with `{` | implemented | `JsonLdHandler:48` | `CanHandleDetectionTest`, `Characterization` |
| Detects JSON-LD keywords at top level | implemented | `JsonLdHandler:78-98` (`hasJsonLdSignals`) | `CanHandleDetectionTest` (10 true cases) |
| JSON-LD arrays (top-level `[`) | implemented | `JsonLdHandler:58-65` | `CanHandleDetectionTest` (array detection) |
| Full IRI keys (http:// or https://) | implemented | `JsonLdHandler:91-95` | `CanHandleDetectionTest` (IRI key detection) |
| Content without `@context` (bare node objects with keywords) | implemented | `JsonLdHandler:78-98` | `CanHandleDetectionTest` (contextless JSON-LD with @id, @type) |

**Notes:**

- `canHandle` now uses proper JSON decoding and top-level key inspection (no more substring matching).
- Valid JSON-LD with only full IRI keys (no @ keywords) is now detected.
- JSON-LD arrays (`[{...}]`) are now supported.
- Documents without any JSON-LD signals are correctly rejected.

---

## JSON-LD Keywords -- Core (1.0)

Reference: [JSON-LD 1.1 -- Section 9.16 (Keywords)](https://www.w3.org/TR/json-ld11/#keywords)

These keywords are supported via delegation to EasyRdf + sweetrdf/json-ld:

| Feature | Status | Location | Tests |
|---|---|---|---|
| `@context` | supported | `JsonLdHandler:122-124` (validated), EasyRdf `Parser\JsonLd:79` | `Characterization`, `Unit`, `BaseUriAndXsdStringTest` |
| `@id` (node identifiers) | supported | via EasyRdf/sweetrdf-json-ld | `Characterization`, W3C tests |
| `@type` (type coercion + `rdf:type`) | supported | via EasyRdf/sweetrdf-json-ld | `Characterization`, W3C tests |
| `@value` (value objects) | supported | via EasyRdf/sweetrdf-json-ld | `Characterization` |
| `@language` (language tags) | supported | via EasyRdf/sweetrdf-json-ld | `Characterization`, `JsonLd11KeywordsTest` (@language container) |
| `@graph` (named graphs) | supported | EasyRdf (default graph) + `extractNamedGraphs()` (named graphs in metadata) | `NamedGraphSupportTest` (6 tests) |
| `@list` (ordered collections) | supported | via EasyRdf/sweetrdf-json-ld | `JsonLd11KeywordsTest`, W3C tests |
| `@set` (unordered collections) | supported | via EasyRdf/sweetrdf-json-ld | `JsonLd11KeywordsTest`, W3C tests |
| `@reverse` (reverse properties) | supported | via EasyRdf/sweetrdf-json-ld | W3C tests |
| `@base` (base IRI) | supported | via `parseWithOptions(['base' => ...])` | `BaseUriAndXsdStringTest` (5 tests) |
| `@vocab` (default vocabulary) | supported | via EasyRdf/sweetrdf-json-ld | W3C tests |

### Named Graph Support

Named graph quads are now preserved via `JsonLdHandler::extractNamedGraphs()`. EasyRdf's JSON-LD parser
still only populates the default graph, but named graph triples are extracted directly from
`ML\JsonLD\JsonLD::toRdf()` and stored in `metadata['named_graphs']` as a keyed array:

```php
$metadata['named_graphs'] = [
    'http://example.org/graph1' => [
        ['subject' => '...', 'predicate' => '...', 'object' => '...'],
    ],
];
```

---

## JSON-LD Keywords -- 1.1 Additions

Reference: [JSON-LD 1.1 -- Section 4 (Advanced Concepts)](https://www.w3.org/TR/json-ld11/#advanced-concepts)

These keywords were introduced in JSON-LD 1.1 and are **not supported** because sweetrdf/json-ld ^1.3
implements the JSON-LD 1.0 Processing Algorithms:

| Feature | Status | Behavior in 1.0 Processor | Tests |
|---|---|---|---|
| `@version` (processing mode) | not supported | Causes `JsonLdException` (unknown keyword) | `JsonLd11KeywordsTest` |
| `@nest` (property nesting) | not supported | Silently ignored, nested values lost | `JsonLd11KeywordsTest` |
| `@included` (included blocks) | not supported | Silently ignored, included nodes not emitted | `JsonLd11KeywordsTest` |
| `@json` (JSON literal type) | not supported | Silent degradation (no rdf:JSON datatype) | `JsonLd11KeywordsTest` |
| `@direction` (text direction) | not supported | Silently ignored, no direction info in output | `JsonLd11KeywordsTest` |
| `@propagate` (context propagation) | not supported | Silently ignored | `JsonLd11KeywordsTest` |
| `@protected` (protected term defs) | not supported | Silently ignored, redefinition allowed | `JsonLd11KeywordsTest` |
| `@import` (context import) | not supported | Silently ignored or may fail | `JsonLd11KeywordsTest` |
| `@prefix` (prefix flag) | not supported | Silently ignored | W3C tests skipped |
| `@container: @id` / `@type` / `@graph` (1.1 container types) | not supported | Silent degradation | `JsonLd11KeywordsTest` |

265 of the 467 W3C toRdf manifest tests specify `specVersion: json-ld-1.1`.

---

## Context Handling

Reference: [JSON-LD 1.1 -- Section 3.1 (The Context)](https://www.w3.org/TR/json-ld11/#the-context)

| Feature | Status | Location | Tests |
|---|---|---|---|
| Inline object context (`@context: {...}`) | supported | via sweetrdf/json-ld | `Characterization`, `Unit` |
| Array of inline contexts (`@context: [{...}, {...}]`) | supported | via sweetrdf/json-ld | `Characterization` |
| String URL context (`@context: "http://..."`) | supported | via sweetrdf/json-ld `FileGetContentsLoader` | `RemoteContextTest` |
| Context metadata preserved in ParsedRdf | implemented | `JsonLdHandler:145` | `Characterization` |
| Remote context resolution (`http://` URLs) | supported | via sweetrdf/json-ld `FileGetContentsLoader` (HTTP fetching) | `RemoteContextTest` (8 tests) |
| `disableRemoteContexts` security option | implemented | `JsonLdHandler:127-131` + `assertNoRemoteContexts()` | `RemoteContextTest` (4 security tests) |
| Scoped contexts (property-scoped) | not supported | sweetrdf/json-ld 1.0 limitation | W3C tests skipped |

---

## Parsing to ParsedRdf

Reference: `parser-core` `ParsedRdf` value object

| Feature | Status | Location | Tests |
|---|---|---|---|
| Returns `ParsedRdf` instance | implemented | `JsonLdHandler:149-154` | `Characterization`, `Unit` |
| `format` set to `'json-ld'` | implemented | `JsonLdHandler:151` | `Characterization`, `Unit` |
| `rawContent` preserves original input | implemented | `JsonLdHandler:152` | `Characterization` |
| `graph` contains EasyRdf Graph | implemented | `JsonLdHandler:135-136` | `Characterization` |
| `metadata` with 5 keys | implemented | `JsonLdHandler:141-147` | `Characterization` |
| `metadata['named_graphs']` for named graph quads | implemented | `JsonLdHandler:139,146` | `NamedGraphSupportTest` |

### Metadata Schema

```php
$metadata = [
    'parser'         => 'jsonld_handler',              // constant string identifier
    'format'         => 'json-ld',                     // matches ParsedRdf::format
    'resource_count' => count($graph->resources()),    // integer
    'context'        => $decoded['@context'],          // preserved from input
    'named_graphs'   => [...],                         // named graph quads (keyed by graph URI)
];
```

---

## Error Handling

Reference: `parser-core` `ParseException`

| Feature | Status | Location | Tests |
|---|---|---|---|
| Invalid JSON detection | implemented | `JsonLdHandler:117-120` | `Characterization` |
| Missing `@context` detection | implemented | `JsonLdHandler:122-124` | `Characterization`, `Unit` |
| Remote context disabled | implemented | `JsonLdHandler:127-131` + `assertNoRemoteContexts()` | `RemoteContextTest` |
| EasyRdf/sweetrdf-json-ld failure wrapping | implemented | `JsonLdHandler:156-160` | `Characterization`, `RemoteContextTest` |

### Error Flow

The `parseWithOptions()` method uses a multi-layer exception strategy:

1. **JSON validation**: Invalid JSON throws `ParseException` with `"Invalid JSON: "` prefix.
2. **`@context` validation**: Missing `@context` throws `ParseException`.
3. **Remote context check**: If `disableRemoteContexts` is `true`, remote URLs cause `ParseException`.
4. **`ParseException` re-throw**: Any `ParseException` from above is re-thrown as-is.
5. **`Throwable` wrapping**: Any exception from EasyRdf/sweetrdf-json-ld is wrapped in `ParseException`.

---

## W3C Conformance Test Suite

Reference: [W3C JSON-LD API toRdf Tests](https://w3c.github.io/json-ld-api/tests/toRdf-manifest.jsonld)

The W3C toRdf manifest contains **467 tests** across three categories. All 467 tests are exercised
in the conformance test suite.

### Positive Evaluation Tests (345 in manifest)

| Category | Count | Notes |
|---|---|---|
| Passing (triple comparison verified) | 79 | Full N-Triples output match (up from 22 baseline) |
| Failed (library limitation) | 5 | See failure breakdown below (down from 12) |
| Skipped (handler/library limitation) | 260 | See skip reasons below |
| Deprecated (EasyRdf Collection/Resource) | 1 | EasyRdf `Collection::count()` return type |
| **Total** | **345** | |

**Failure breakdown (5 tests):**

| Failure Type | Count | Tests | Cause |
|---|---|---|---|
| Generalized RDF / bnode handling | 1 | `t0118` | `produceGeneralizedRdf` flag behavior |
| Type-scoped context | 1 | `tc021` | 1.1 type-scoped context partially processed |
| `@vocab` as blank node | 1 | `te075` | EasyRdf blank node handling for @vocab |
| `@base` empty/relative resolution | 2 | `te089`, `te090` | Empty/relative `@base` overrides |

**Skip reason breakdown:**

| Skip Reason | Estimated Count |
|---|---|
| `@context` required at top level (handler limitation) | ~70 |
| JSON-LD 1.1 feature not supported by sweetrdf/json-ld | ~130 |
| Expected output contains only named graph quads | ~25 |
| Test requires remote context resolution (fixture loader needed) | ~30 |
| Mixed named graph / default graph filtering | ~5 |

### Positive Syntax Tests (16 in manifest)

| Category | Count | Notes |
|---|---|---|
| Skipped | 16 | All lack `@context` at top level -- parse() limitation |

### Negative Evaluation Tests (106 in manifest)

| Category | Count | Notes |
|---|---|---|
| Passing (exception correctly thrown) | 90 | ParseException or Throwable raised (up from 43) |
| Skipped (1.1 validation not enforced) | 16 | sweetrdf/json-ld does not validate all 1.1 error conditions |
| Deprecated | 0 | (down from 48, sweetrdf fixes PHP 8.x warnings) |
| **Total** | **106** | |

### Improvement Summary (Epic 11)

| Metric | Before (baseline) | After (Epic 11) | Change |
|---|---|---|---|
| Passing (positive + negative) | 65 | 169 | +104 (+160%) |
| Failed | 12 | 5 | -7 (-58%) |
| Deprecated | 100 | 1 | -99 (-99%) |
| Skipped | 290 | 276 | -14 (-5%) |
| Warnings | 0 | 6 | +6 (new test coverage) |

---

## Backward Compatibility (Alias Bridge)

| Feature | Status | Location | Tests |
|---|---|---|---|
| `App\...\JsonLdHandler` alias to new namespace | implemented | `aliases.php:13-30` | `AliasesTest:10-11` |
| `instanceof` compatibility across namespaces | implemented | via `class_alias` | `AliasesTest:16-18,21-24` |
| `E_USER_DEPRECATED` on old namespace usage | implemented | `aliases.php:20-26` | `AliasesTest:62-63,66-71,73-76` |
| No deprecation at autoload time | implemented | `aliases.php:13` (lazy via `spl_autoload_register`) | `AliasesTest:78-102` |
| No eager aliasing of parser-core classes | implemented | only JsonLdHandler aliased | `AliasesTest:106-119` |

---

## Test Coverage Summary

### Static Test Counts

| Test Suite | File | Test Count |
|---|---|---|
| Unit tests | `tests/Unit/JsonLdHandlerTest.php` | 4 |
| Unit tests (aliases) | `tests/Unit/AliasesTest.php` | 10 |
| Unit tests (canHandle) | `tests/Unit/CanHandleDetectionTest.php` | 23 |
| Unit tests (base URI + xsd:string) | `tests/Unit/BaseUriAndXsdStringTest.php` | 8 |
| Unit tests (named graphs) | `tests/Unit/NamedGraphSupportTest.php` | 6 |
| Unit tests (remote contexts) | `tests/Unit/RemoteContextTest.php` | 8 |
| Unit tests (JSON-LD 1.1 keywords) | `tests/Unit/JsonLd11KeywordsTest.php` | 16 |
| Characterization tests | `tests/Characterization/JsonLdHandlerTest.php` | 50 |
| W3C positive evaluation | `tests/Conformance/W3cToRdfPositiveTest.php` | 345 (dynamic) |
| W3C positive syntax | `tests/Conformance/W3cToRdfPositiveTest.php` | 16 (dynamic) |
| W3C negative evaluation | `tests/Conformance/W3cToRdfNegativeTest.php` | 106 (dynamic) |
| **Total** | | **592** |

---

## Architecture Notes

The handler is a **single ~270-line class** with the following structure:

1. **`canHandle`** (lines 44-71): JSON decode + keyword/IRI detection. Supports objects and arrays.
2. **`parse`** (lines 100-103): Delegates to `parseWithOptions` with empty options.
3. **`parseWithOptions`** (lines 114-161): Full parsing with options (`base`, `disableRemoteContexts`).
   Validates JSON, checks `@context`, handles remote context security, delegates to EasyRdf,
   extracts named graphs, builds `ParsedRdf` with metadata.
4. **`assertNoRemoteContexts`** (lines 173-201): Security check for remote context URLs.
5. **`extractNamedGraphs`** (lines 208-264): Direct `ML\JsonLD\JsonLD::toRdf()` for named graph quads.
6. **`getFormatName`** (lines 266-269): Returns `'json-ld'`.

Key design decisions:

1. **Full delegation to EasyRdf + sweetrdf/json-ld** for actual JSON-LD processing.
2. **`parseWithOptions()`** extends the interface with `base` URI and `disableRemoteContexts` support.
3. **Named graphs in metadata** -- avoids modifying parser-core's `ParsedRdf` value object.
4. **`@context` is still required for `parse()`** -- documents using only full IRIs are rejected.
5. **sweetrdf/json-ld ^1.3** replaces ml/json-ld ^1.2, fixing all PHP 8.x deprecation warnings.

---

## Remaining Gaps

### Handler-Level Gaps

1. **`@context` required for `parse()`** -- documents using only full IRIs (no `@context`) are rejected
   by `parse()`. The `canHandle()` method detects them correctly, but `parse()` throws `ParseException`.
2. **W3C remote context fixtures** -- ~30 W3C tests use relative context URLs that need a
   `FixtureDocumentLoader` to serve fixture files. Would require `JsonLD::setDefaultDocumentLoader()`
   (global state, test isolation risk) or modifying EasyRdf.

### Library-Level Gaps (sweetrdf/json-ld ^1.3)

1. **JSON-LD 1.0 only** -- sweetrdf/json-ld ^1.3 implements JSON-LD 1.0 Processing Algorithms.
   All JSON-LD 1.1 features (`@nest`, `@included`, `@json`, `@direction`, `@propagate`,
   `@protected`, `@import`, `@prefix`, `@version`, scoped contexts) are unsupported.
   This blocks ~265 W3C tests.
2. **Named graph quads dropped by EasyRdf** -- EasyRdf's JSON-LD parser (`Parser\JsonLd:86-88`) skips
   all quads belonging to named graphs. Named graphs are preserved via `extractNamedGraphs()` in
   metadata, but not in the EasyRdf Graph object.
3. **EasyRdf PHP 8.x deprecation** -- EasyRdf itself emits 1-2 `E_DEPRECATED` warnings
   (`Collection::count()`, `Resource::offsetExists()`) unrelated to JSON-LD processing.

### Path to Higher W3C Conformance

1. **Fixture document loader** (~30 tests): Implement a `FixtureDocumentLoader` serving W3C test
   fixture files to unblock remote context tests.
2. **JSON-LD 1.1 processor** (~265 tests): When a PHP JSON-LD 1.1 library becomes available
   (or if sweetrdf/json-ld adds 1.1 support), adopt it.
3. **Positive syntax tests** (16 tests): Relax the `@context` requirement in `parse()` for
   documents that `canHandle()` already detects.

---

## JSON-LD 1.1 Dependency Strategy Decision (Story 11.1)

> Decision date: 2026-02-20

### Decision: Phased approach

**Phase 1 (Stories 11.2 through 11.6) -- COMPLETED:**
- Replaced `ml/json-ld ^1.2` with `sweetrdf/json-ld ^1.3` (PHP 8.x deprecation fix)
- Fixed handler-level gaps: `canHandle()` detection, base URI parameter, `xsd:string` normalization
- Implemented named graph support, remote context security option
- Documented JSON-LD 1.1 keyword status
- Impact: deprecated tests 100 -> 1, failed tests 12 -> 5, passing tests 65 -> 169

**Phase 2 (future): JSON-LD 1.1 features**
- Defer full JSON-LD 1.1 implementation until a PHP 1.1 processor becomes available
- If `sweetrdf/json-ld` gains 1.1 support, adopt it (lowest effort path)
- If no PHP 1.1 processor emerges, evaluate Node.js subprocess approach
- Direct PHP implementation is last resort due to extreme scope (~100 pages of processing algorithms)

### Options Evaluated

See earlier in this document for the full evaluation of Options A through E.
