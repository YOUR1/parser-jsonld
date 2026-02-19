<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserCore\Exceptions\ParseException;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;
use Youri\vandenBogert\Software\ParserJsonLd\JsonLdHandler;

beforeEach(function () {
    $this->handler = new JsonLdHandler();
});

it('detects json-ld with @context', function () {
    expect($this->handler->canHandle('{"@context":{"ex":"http://example.org/"}}'))->toBeTrue();
});

it('returns json-ld format name', function () {
    expect($this->handler->getFormatName())->toBe('json-ld');
});

it('parses valid json-ld', function () {
    $jsonld = '{"@context":{"rdfs":"http://www.w3.org/2000/01/rdf-schema#"},"@id":"http://example.org/A","@type":"rdfs:Class"}';
    $parsed = $this->handler->parse($jsonld);
    expect($parsed)->toBeInstanceOf(ParsedRdf::class);
    expect($parsed->format)->toBe('json-ld');
});

it('throws on missing context', function () {
    expect(fn () => $this->handler->parse('{"name":"x"}'))->toThrow(ParseException::class);
});
