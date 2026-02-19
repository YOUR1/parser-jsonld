# Spec Completeness

> Assessment of parser-jsonld implementation coverage against W3C JSON-LD 1.1.
> Last updated: 2026-02-19

## Scope

This library provides a single **JSON-LD format handler** (`JsonLdHandler`) that detects and parses
JSON-LD content into a `ParsedRdf` value object. It implements the `RdfFormatHandlerInterface` contract
from `parser-core`.

Actual JSON-LD-to-RDF conversion is delegated to **EasyRdf** + **ml/json-ld ^1.2**, which implements
the JSON-LD 1.0 Processing Algorithms. JSON-LD 1.1 features are therefore only supported to the extent
that ml/json-ld handles them.

Reference: [W3C JSON-LD 1.1](https://www.w3.org/TR/json-ld11/)

## Summary

| Spec Area | Implemented | Total | Coverage |
|---|---|---|---|
| Handler Interface Contract | 3 | 3 | 100% |
| Format Detection | 3 | 5 | 60% |
| JSON-LD Keywords (1.0 core) | 11 | 11 | 100% |
| JSON-LD Keywords (1.1 additions) | 0 | 10 | 0% |
| Context Handling | 4 | 7 | 57% |
| Parsing to ParsedRdf | 5 | 5 | 100% |
| Error Handling | 3 | 3 | 100% |
| W3C Conformance -- toRdf Positive | 22 | 345 | 6% |
| W3C Conformance -- toRdf Negative | 43 | 106 | 41% |
| W3C Conformance -- Positive Syntax | 0 | 16 | 0% |
| **Overall (handler code)** | | | **~85%** |
| **Overall (W3C toRdf suite)** | | | **~14%** |

---

## Handler Interface Contract

Reference: `parser-core` `RdfFormatHandlerInterface`

| Feature | Status | Location | Tests |
|---|---|---|---|
| `canHandle(string $content): bool` | implemented | `JsonLdHandler:21-30` | `Characterization:18,23,28,33,38,43,50,54,58,63,68,72,77,82,87,91,96` (17 cases) |
| `parse(string $content): ParsedRdf` | implemented | `JsonLdHandler:32-67` | `Characterization:103,114,124,134,145,155,165,176,187,208,224,239,251,266,280,292,305` (17 cases) |
| `getFormatName(): string` | implemented | `JsonLdHandler:69-72` | `Unit:17-19`, `Characterization:314-315,318-319` (3 cases) |

---

## Format Detection (`canHandle`)

Reference: [JSON-LD 1.1 -- Section 9 (JSON-LD Grammar)](https://www.w3.org/TR/json-ld11/#json-ld-grammar)

| Feature | Status | Location | Tests |
|---|---|---|---|
| Detects JSON object starting with `{` | implemented | `JsonLdHandler:25-27` | `Characterization:50,54,58,63,68,72,77,82,87,91,96` |
| Detects `@context` keyword (substring match) | implemented | `JsonLdHandler:29` | `Characterization:18,23,28,33,38,43` |
| Case-sensitive `@context` matching | implemented | `JsonLdHandler:29` | `Characterization:91-93` (`@Context` with uppercase C returns false) |
| JSON-LD arrays (top-level `[`) | not handled | `JsonLdHandler:25` returns false | `Characterization:77-79` (returns false for `[{...}]`) |
| Content without `@context` (bare node objects) | not detected | `JsonLdHandler:29` requires `@context` | `Characterization:82-85` (e.g., `{"name":"foo"}` returns false) |

**Notes on detection limitations:**

- `canHandle` uses `str_contains($trimmed, '@context')` which matches `@context` anywhere in the string,
  including inside property values and nested objects. This produces false positives for non-JSON-LD
  JSON that happens to contain the substring `@context` (documented in `Characterization:38-41`).
- Valid JSON-LD that omits `@context` (using full IRIs as keys) is not detected.
  This affects 16 of the 16 W3C Positive Syntax tests, all of which lack a top-level `@context`.
- JSON-LD arrays (`[{...}]`) are valid per the spec but rejected by `canHandle`.

---

## JSON-LD Keywords -- Core (1.0)

Reference: [JSON-LD 1.1 -- Section 9.16 (Keywords)](https://www.w3.org/TR/json-ld11/#keywords)

These keywords are supported via delegation to EasyRdf + ml/json-ld:

| Feature | Status | Location | Tests |
|---|---|---|---|
| `@context` | supported | `JsonLdHandler:41-43` (validated), EasyRdf `Parser\JsonLd:79` | `Characterization:103-112`, `Unit:13-15,21-26` |
| `@id` (node identifiers) | supported | via EasyRdf/ml-json-ld | `Characterization:239-249` (resource URI in graph) |
| `@type` (type coercion + `rdf:type`) | supported | via EasyRdf/ml-json-ld | `Characterization:239-249`, W3C `t0007` |
| `@value` (value objects) | supported | via EasyRdf/ml-json-ld | `Characterization:251-264` (`@value` + `@type`), `Characterization:280-290` (`@value` + `@language`) |
| `@language` (language tags) | supported | via EasyRdf/ml-json-ld | `Characterization:280-290` (language-tagged literal) |
| `@graph` (named graphs) | partial | EasyRdf `Parser\JsonLd:86-88` ignores named graphs | `Characterization:266-278` (default graph portion only) |
| `@list` (ordered collections) | supported | via EasyRdf/ml-json-ld | W3C `t0013`, `t0014`, `t0015` |
| `@set` (unordered collections) | supported | via EasyRdf/ml-json-ld | W3C tests in manifest |
| `@reverse` (reverse properties) | supported | via EasyRdf/ml-json-ld | W3C `t0029` (Reverse property) |
| `@base` (base IRI) | partial | via ml-json-ld (some resolution differences) | W3C `t0017-t0018` (relative IRIs) |
| `@vocab` (default vocabulary) | supported | via EasyRdf/ml-json-ld | W3C tests with `@vocab` in context |

### Named Graph Limitation

EasyRdf's JSON-LD parser (`Parser\JsonLd:86-88`) explicitly skips quads belonging to named graphs:

```php
if (null !== $quad->getGraph()) {
    continue;
}
```

This means only the **default graph** is populated. All W3C tests expecting named graph output
are skipped in the conformance suite.

---

## JSON-LD Keywords -- 1.1 Additions

Reference: [JSON-LD 1.1 -- Section 4 (Advanced Concepts)](https://www.w3.org/TR/json-ld11/#advanced-concepts)

These keywords were introduced in JSON-LD 1.1 and are **not supported** because ml/json-ld ^1.2
implements the JSON-LD 1.0 Processing Algorithms:

| Feature | Status | Location | Tests |
|---|---|---|---|
| `@nest` (property nesting) | not supported | ml/json-ld 1.0 limitation | W3C tests skipped |
| `@included` (included blocks) | not supported | ml/json-ld 1.0 limitation | W3C tests skipped |
| `@json` (JSON literal type) | not supported | ml/json-ld 1.0 limitation | W3C tests skipped |
| `@direction` (text direction) | not supported | ml/json-ld 1.0 limitation | W3C tests skipped |
| `@propagate` (context propagation) | not supported | ml/json-ld 1.0 limitation | W3C tests skipped |
| `@protected` (protected term defs) | not supported | ml/json-ld 1.0 limitation | W3C tests skipped |
| `@import` (context import) | not supported | ml/json-ld 1.0 limitation | W3C tests skipped |
| `@prefix` (prefix flag) | not supported | ml/json-ld 1.0 limitation | W3C tests skipped |
| `@version` (processing mode) | not supported | ml/json-ld 1.0 limitation | W3C tests skipped |
| `@container: @id` / `@type` / `@graph` (1.1 container types) | not supported | ml/json-ld 1.0 limitation | W3C tests skipped |

265 of the 467 W3C toRdf manifest tests specify `specVersion: json-ld-1.1`.

---

## Context Handling

Reference: [JSON-LD 1.1 -- Section 3.1 (The Context)](https://www.w3.org/TR/json-ld11/#the-context)

| Feature | Status | Location | Tests |
|---|---|---|---|
| Inline object context (`@context: {...}`) | supported | `JsonLdHandler:41-43`, via ml/json-ld | `Characterization:208-222`, `Unit:13-15` |
| Array of inline contexts (`@context: [{...}, {...}]`) | supported | via ml/json-ld | `Characterization:224-237` |
| String URL context (`@context: "http://..."`) | partial | via ml/json-ld (requires resolution) | `Characterization:187-206` (non-resolvable URL triggers ParseException) |
| Context metadata preserved in ParsedRdf | implemented | `JsonLdHandler:52` | `Characterization:176-185,208-222,224-237` |
| Remote context resolution (`http://` URLs) | not supported | no HTTP client configured | `W3cToRdfPositiveTest:77-79` (tests skipped) |
| Scoped contexts (property-scoped) | not supported | ml/json-ld 1.0 limitation | W3C tests skipped |
| `@context: null` validation | implemented | `JsonLdHandler:41-43` (`isset` returns false for null) | `Characterization:375-383` |

---

## Parsing to ParsedRdf

Reference: `parser-core` `ParsedRdf` value object

| Feature | Status | Location | Tests |
|---|---|---|---|
| Returns `ParsedRdf` instance | implemented | `JsonLdHandler:55-60` | `Characterization:103-112`, `Unit:21-26` |
| `format` set to `'json-ld'` | implemented | `JsonLdHandler:57` | `Characterization:114-122`, `Unit:25` |
| `rawContent` preserves original input | implemented | `JsonLdHandler:58` | `Characterization:124-132` |
| `graph` contains EasyRdf Graph | implemented | `JsonLdHandler:45-46,55` | `Characterization:165-174` (resource_count matches graph) |
| `metadata` with `parser`, `format`, `resource_count`, `context` | implemented | `JsonLdHandler:48-53` | `Characterization:134-143,145-153,155-163,165-174,176-185` |

### Metadata Schema

```php
$metadata = [
    'parser'         => 'jsonld_handler',    // constant string identifier
    'format'         => 'json-ld',           // matches ParsedRdf::format
    'resource_count' => count($graph->resources()), // integer
    'context'        => $decoded['@context'],       // preserved from input
];
```

---

## Error Handling

Reference: `parser-core` `ParseException`

| Feature | Status | Location | Tests |
|---|---|---|---|
| Invalid JSON detection | implemented | `JsonLdHandler:36-38` (`json_decode` + `json_last_error`) | `Characterization:324-332,335-341,401-408,419-427` |
| Missing `@context` detection | implemented | `JsonLdHandler:41-43` (`isset` check) | `Characterization:344-352,354-361,375-383,410-417`, `Unit:28-30` |
| EasyRdf/ml-json-ld failure wrapping | implemented | `JsonLdHandler:64-66` (Throwable catch) | `Characterization:363-373,385-399` |

### Error Flow

The `parse()` method uses a two-layer exception strategy (lines 62-66):

1. **`ParseException` re-throw** (line 62-63): Invalid JSON and missing `@context` throw `ParseException`
   directly without a `$previous` exception and without the `"JSON-LD parsing failed:"` prefix.
2. **`Throwable` wrapping** (line 64-66): Any exception thrown by EasyRdf or ml/json-ld is caught and
   wrapped in a `ParseException` with the `"JSON-LD parsing failed:"` prefix and the original exception
   as `$previous`.

| Error Scenario | Message Prefix | `$previous` | Tests |
|---|---|---|---|
| Invalid JSON | `"Invalid JSON: "` | `null` | `Characterization:324-332,335-341` |
| Missing `@context` | `"Missing @context in JSON-LD"` | `null` | `Characterization:344-352,354-361` |
| `@context: null` | `"Missing @context in JSON-LD"` | `null` | `Characterization:375-383` |
| EasyRdf/ml-json-ld failure | `"JSON-LD parsing failed: "` | original Throwable | `Characterization:363-373,385-399` |
| Empty string input | `"Invalid JSON: "` | `null` | `Characterization:419-427` |
| canHandle/parse gap (broken JSON with @context) | `"Invalid JSON: "` | `null` | `Characterization:429-433` |

---

## W3C Conformance Test Suite

Reference: [W3C JSON-LD API toRdf Tests](https://w3c.github.io/json-ld-api/tests/toRdf-manifest.jsonld)

The W3C toRdf manifest contains **467 tests** across three categories. All 467 tests are exercised
in the conformance test suite.

### Positive Evaluation Tests (345 in manifest)

| Category | Count | Notes |
|---|---|---|
| Passing (triple comparison verified) | 22 | Full N-Triples output match |
| Failed (library limitation) | 12 | See failure breakdown below |
| Skipped (handler/library limitation) | 259 | See skip reasons below |
| Deprecated (PHP deprecation from ml/json-ld) | 52 | Tests pass/skip but emit `E_DEPRECATED` |
| **Total** | **345** | |

**Failure breakdown (12 tests):**

| Failure Type | Count | Cause |
|---|---|---|
| `xsd:string` datatype mismatch | 8 | EasyRdf/ml-json-ld serializes plain literals with `^^<xsd:string>` but W3C expected output uses bare string literals |
| Blank node count mismatch | 2 | Generalized RDF / library-specific bnode handling |
| `@base` IRI resolution + `xsd:string` | 2 | Relative `@base` handling differences |

**Skip reason breakdown:**

| Skip Reason | Estimated Count |
|---|---|
| Handler requires `@context` at top level | ~70 |
| JSON-LD 1.1 feature not supported by ml/json-ld ^1.2 | ~130 |
| Expected output contains only named graph quads | ~25 |
| Test requires remote context resolution | ~30 |
| Mixed named graph / default graph filtering | ~4 |

### Positive Syntax Tests (16 in manifest)

| Category | Count | Notes |
|---|---|---|
| Skipped | 16 | All lack `@context` at top level -- `canHandle` limitation |

### Negative Evaluation Tests (106 in manifest)

| Category | Count | Notes |
|---|---|---|
| Passing (exception correctly thrown) | 43 | ParseException or Throwable raised |
| Skipped (1.1 validation not enforced) | 15 | ml/json-ld ^1.2 does not validate 1.1 error conditions |
| Deprecated (PHP deprecation from ml/json-ld) | 48 | Tests pass/skip but emit `E_DEPRECATED` |
| Unexpected failures | 0 | |
| **Total** | **106** | |

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
| Characterization tests | `tests/Characterization/JsonLdHandlerTest.php` | 50 |
| W3C positive evaluation | `tests/Conformance/W3cToRdfPositiveTest.php` | 345 (dynamic) |
| W3C positive syntax | `tests/Conformance/W3cToRdfPositiveTest.php` | 16 (dynamic) |
| W3C negative evaluation | `tests/Conformance/W3cToRdfNegativeTest.php` | 106 (dynamic) |
| **Total** | | **531** |

### Coverage by Area

| Area | Test Count | Coverage Notes |
|---|---|---|
| `canHandle()` true cases | 6 | Standard JSON-LD, whitespace, false positives |
| `canHandle()` false cases | 11 | Empty, Turtle, RDF/XML, N-Triples, HTML, arrays, no context, uppercase |
| `parse()` success paths | 13 | Class declarations, properties, @graph, language tags, typed literals, minimal |
| `parse()` metadata verification | 7 | All 4 metadata keys individually verified |
| `parse()` context preservation | 4 | Object context, array context, string URL context, empty context |
| Error handling | 11 | Invalid JSON, missing context, null context, EasyRdf wrapping, empty string, canHandle/parse gap |
| Prefix side effects | 3 | Namespace count unchanged, standard prefixes kept, no registerPrefixesFromContent |
| Backward compatibility | 10 | Alias resolution, instanceof, deprecation warnings, no eager aliasing |
| W3C conformance | 467 | Full manifest exercised (22 + 43 passing, rest skipped/deprecated/failed) |

---

## Architecture Notes

The implementation is minimal by design -- a **single 73-line class** with three methods:

1. **`canHandle`** (lines 21-30): Two-step heuristic -- starts with `{` and contains `@context`.
2. **`parse`** (lines 32-67): Validates JSON + `@context`, delegates to `EasyRdf\Graph::parse('jsonld')`,
   wraps result in `ParsedRdf`.
3. **`getFormatName`** (lines 69-72): Returns `'json-ld'`.

Key design decisions:

1. **Full delegation to EasyRdf + ml/json-ld** for actual JSON-LD processing. The handler itself
   performs no JSON-LD-specific transformation -- it only validates structure and wraps results.
2. **No prefix registration** -- unlike the Turtle and RDF/XML handlers in `parser-owl`, the JSON-LD
   handler does not call `RdfNamespace::set()`. Prefix handling is left entirely to EasyRdf.
3. **No base URI support** -- the handler does not accept or pass a base URI to EasyRdf.
   This affects relative IRI resolution in some W3C tests.
4. **`@context` is required** -- the handler mandates a top-level `@context` key. While the JSON-LD
   spec allows documents without `@context` (using full IRIs), this handler rejects them.

---

## Remaining Gaps

### Handler-Level Gaps

1. **No JSON-LD array support** -- `canHandle` rejects documents starting with `[`, though
   `[{...}]` is valid JSON-LD.
2. **Substring-based `@context` detection** -- false positives when `@context` appears inside
   string values or nested objects rather than as a top-level key.
3. **No base URI parameter** -- `parse()` does not accept a base URI, preventing correct
   relative IRI resolution.
4. **`@context` required** -- documents using only full IRIs (no `@context`) are rejected
   by both `canHandle` and `parse`.

### Library-Level Gaps (ml/json-ld ^1.2)

1. **JSON-LD 1.0 only** -- ml/json-ld ^1.2 implements JSON-LD 1.0 Processing Algorithms.
   All JSON-LD 1.1 features (`@nest`, `@included`, `@json`, `@direction`, `@propagate`,
   `@protected`, `@import`, `@prefix`, scoped contexts) are unsupported.
2. **`xsd:string` datatype normalization** -- ml/json-ld serializes plain string literals with
   explicit `^^<http://www.w3.org/2001/XMLSchema#string>`, while the W3C test suite expects
   bare string literals without a datatype. This causes 8 test failures.
3. **Named graph quads dropped** -- EasyRdf's JSON-LD parser (`Parser\JsonLd:86-88`) skips
   all quads belonging to named graphs, returning only the default graph.
4. **PHP deprecation notices** -- ml/json-ld emits `E_DEPRECATED` warnings on 52+ tests
   due to use of deprecated PHP features.

### Path to Higher W3C Conformance

Upgrading to a JSON-LD 1.1 processor (e.g., replacing ml/json-ld with a 1.1-compliant library)
would address the largest gap (~265 tests). Adding base URI support and relaxing the `@context`
requirement would address an additional ~86 tests.
