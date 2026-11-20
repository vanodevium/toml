<?php

/** @noinspection StaticClosureCanBeUsedInspection */

declare(strict_types=1);

use Devium\Toml\TomlDateTime;
use Devium\Toml\TomlError;
use Devium\Toml\TomlLocalDate;
use Devium\Toml\TomlLocalDateTime;
use Devium\Toml\TomlLocalTime;

it('decodes a TOML document', function () {
    $toml = <<<'TOML_STRING'
# This is a TOML document

title = "TOML Example"

[owner]
name = "Tom Preston-Werner"
dob = 1979-05-27T07:32:00-08:00

[database]
enabled = true
ports = [ 8000, 8001, 8002 ]
data = [ ["delta", "phi"], [3.14] ]
temp_targets = { cpu = 79.5, case = 72.0 }

[servers]

[servers.alpha]
ip = "10.0.0.1"
role = "frontend"

[servers.beta]
ip = "10.0.0.2"
role = "backend"

[[fruits]]
name = "apple"

[fruits.physical]  # sub table
color = "red"
shape = "round"

[[fruits.varieties]]  # nested array of tables
name = "red delicious"

[[fruits.varieties]]
name = "granny smith"


[[fruits]]
name = "banana"

[[fruits.varieties]]
name = "plantain"

[[fruit]] # prefix of existing array
name = "strawberry"
TOML_STRING;

    $json = <<<'JSON_STRING'
{
  "title": "TOML Example",
  "owner": { "name": "Tom Preston-Werner", "dob": "1979-05-27T15:32:00.000Z" },
  "database": {
    "enabled": true,
    "ports": [8000, 8001, 8002],
    "data": [["delta", "phi"], [3.14]],
    "temp_targets": { "cpu": 79.5, "case": 72 }
  },
  "servers": {
    "alpha": { "ip": "10.0.0.1", "role": "frontend" },
    "beta": { "ip": "10.0.0.2", "role": "backend" }
  },
  "fruits": [
    {
      "name": "apple",
      "physical": { "color": "red", "shape": "round" },
      "varieties": [{ "name": "red delicious" }, { "name": "granny smith" }]
    },
    { "name": "banana", "varieties": [{ "name": "plantain" }] }
  ],
  "fruit": [{ "name": "strawberry" }]
}
JSON_STRING;

    expect(toml_decode($toml))->toEqual(json_decode($json))
        ->and(toml_decode($toml, true))->toEqual(json_decode($json, true));
});

it('decodes into objects and arrays', function (string $toml, array $expected) {
    expect(toml_decode($toml))->toEqual((object) $expected)
        ->and(toml_decode($toml, true))->toEqual($expected);
})->with([
    'date-times' => [
        "DateTimeInterface = 1979-05-27T07:32:00.999Z\nDateTimeImmutable = 1979-05-27T07:32:00.999Z",
        [
            DateTimeInterface::class => new DateTime('1979-05-27T07:32:00.999'),
            DateTimeImmutable::class => new DateTimeImmutable('1979-05-27T07:32:00.999'),
        ],
    ],
    'floats' => [
        "float1 = 3.14\nfloat2 = \"3.14\"\nfloat3 = 1.0\nfloat4 = \"1.0\"",
        ['float1' => 3.14, 'float2' => 3.14, 'float3' => 1.0, 'float4' => 1.0],
    ],
]);

it('decodes floats as PHP floats', function (bool $asArray) {
    expect((array) toml_decode("pi = 3.141592653589793\n-pi = -3.141592653589793", $asArray, asFloat: true))
        ->toBe(['pi' => 3.141592653589793, '-pi' => -3.141592653589793]);
})->with([
    'object' => false,
    'array' => true,
]);

it('decodes array tables whose names share a prefix', function () {
    $decoded = toml_decode(<<<'TOML_STRING'
[[items]]
name = "first"

[[itemsExtended]]
name = "second"

[[itemsExtendedMore]]
name = "third"

[[itemsExtendedMore.entries]]
name = "first entry"

[itemsExtendedMore.entries.metadata]
type = "primary"

[[itemsExtendedMore.entries]]
name = "second entry"

[itemsExtendedMore.entries.metadata]
type = "secondary"
TOML_STRING, true);

    expect($decoded)->toHaveCount(3)
        ->and($decoded['itemsExtendedMore'][0]['entries'][1]['metadata']['type'])->toBe('secondary');
});

it('decodes nested tables and arrays into objects', function () {
    expect(toml_decode("a = 1\nlist = [1, [2], { x = 3 }]\ninline = { y = { z = 4 } }\n[t.sub]\nk = 5\n[[items]]\nv = 6\n"))
        ->toEqual((object) [
            'a' => 1,
            'list' => [1, [2], (object) ['x' => 3]],
            'inline' => (object) ['y' => (object) ['z' => 4]],
            't' => (object) ['sub' => (object) ['k' => 5]],
            'items' => [(object) ['v' => 6]],
        ])
        ->t->toBeInstanceOf(stdClass::class)
        ->list->toBeArray();
});

it('decodes values', function (string $toml, array $expected) {
    expect(toml_decode($toml, true))->toEqual($expected);
})->with([
    'empty strings' => ["a = ''\nb = \"\"", ['a' => '', 'b' => '']],
    'escape' => ['a = "x\ty"', ['a' => "x\ty"]],
    'newline in multiline string' => ["a = \"\"\"x\ny\"\"\"", ['a' => "x\ny"]],
    'quotes in multiline string' => ['a = """x"y""z"""', ['a' => 'x"y""z']],
    'quotes before the closing delimiter' => ['a = """"x""""'."\n".'b = """x"""""', ['a' => '"x"', 'b' => 'x""']],
    'quoted and dotted keys' => ["\"a b\" = 1\nc.d = 2", ['a b' => 1, 'c' => ['d' => 2]]],
    'comment at EOF' => ["a = 1\n# c", ['a' => 1]],
    'plus sign' => ['a = +1', ['a' => 1]],
    'octal and binary' => ["a = 0o17\nb = 0b101", ['a' => 15, 'b' => 5]],
    'exponents' => ["a = 1e5\nb = 1e+5\nc = 1.5e-3", ['a' => 1e5, 'b' => 1e5, 'c' => 1.5e-3]],
    'empty array and inline table' => ["a = []\nb = {}", ['a' => [], 'b' => []]],
    'local date' => ["a = 1979-05-27\nb = 2000-02-29\nc = 1979-05-27 # c", [
        'a' => new TomlLocalDate(1979, 5, 27),
        'b' => new TomlLocalDate(2000, 2, 29),
        'c' => new TomlLocalDate(1979, 5, 27),
    ]],
    'local time' => ["a = 07:32:00\nb = 07:32:00.999", [
        'a' => new TomlLocalTime(7, 32, 0, 0),
        'b' => new TomlLocalTime(7, 32, 0, 999),
    ]],
    'local date-time' => ["a = 1979-05-27 07:32:00\nb = 1979-05-27T07:32:00.999", [
        'a' => new TomlLocalDateTime(1979, 5, 27, 7, 32, 0, 0),
        'b' => new TomlLocalDateTime(1979, 5, 27, 7, 32, 0, 999),
    ]],
    'offset date-time' => ["a = 1979-05-27T07:32:00+05:00\nb = 1979-05-27T07:32:00.999-07:00\nc = 1979-05-27T07:32:00.5+01:00", [
        'a' => new TomlDateTime('1979-05-27T07:32:00+05:00'),
        'b' => new TomlDateTime('1979-05-27T07:32:00.999-07:00'),
        'c' => new TomlDateTime('1979-05-27T07:32:00.5+01:00'),
    ]],
    'fractional seconds as milliseconds' => ["a = 07:32:00.05\nb = 07:32:00.123456", [
        'a' => new TomlLocalTime(7, 32, 0, 50),
        'b' => new TomlLocalTime(7, 32, 0, 123),
    ]],
]);

it('decodes strings', function (string $toml, string $expected) {
    expect(toml_decode($toml, true))->toBe(['a' => $expected]);
})->with([
    'escape before the surrogates' => ["a = \"\\uD7FF\"\n", "\u{D7FF}"],
    'escape after the surrogates' => ["a = \"\\uE000\"\n", "\u{E000}"],
    'literal before the surrogates' => ["a = \"\u{D7FF}\"\n", "\u{D7FF}"],
    'literal after the surrogates' => ["a = \"\u{E000}\"\n", "\u{E000}"],
    'escape U+10FFFF' => ["a = \"\\U0010FFFF\"\n", "\u{10FFFF}"],
    'line ending backslash with LF' => ["a = \"\"\"x\\\n \t\n\t y\"\"\"\n", 'xy'],
    'line ending backslash with CRLF' => ["a = \"\"\"x\\\r\n \t\r\n\t y\"\"\"\r\n", 'xy'],
    'line ending backslash with mixed newlines' => ["a = \"\"\"x\\\r\n\n \r\ny\"\"\"\n", 'xy'],
    'line ending backslash up to the closing delimiter' => ["a = \"\"\"x\\\n \"\"\"\n", 'x'],
]);

it('tells apart tables and keys with similar paths', function (string $toml, array $expected) {
    expect(toml_decode($toml, true))->toBe($expected);
})->with([
    'array table name prefix' => ["[[ab]]\nc = 1\n[abc]\nx = 1", ['ab' => [['c' => 1]], 'abc' => ['x' => 1]]],
    'quoted key with a dot' => ["\"a.b\" = 1\na.b = 2", ['a.b' => 1, 'a' => ['b' => 2]]],
    'quoted table with a dot' => ["[\"a.b\"]\nx = 1\n[a.b]\nx = 2", ['a.b' => ['x' => 1], 'a' => ['b' => ['x' => 2]]]],
    'quoted key like an entry index' => ["[[a.\"[0]\"]]\n[a.\"[0]\".b]\nx = 1", ['a' => ['[0]' => [['b' => ['x' => 1]]]]]],
    'header table defined later' => ["[a.b.c]\nx = 1\n[a.b]\ny = 2\n[a]\nz = 3", ['a' => ['b' => ['c' => ['x' => 1], 'y' => 2], 'z' => 3]]],
    'sub-table of a dotted key table' => ["[a]\nb.c = 1\n[a.b.d]\nx = 2", ['a' => ['b' => ['c' => 1, 'd' => ['x' => 2]]]]],
]);

it('keeps leading zeros of fractional seconds', function (string $value) {
    expect(toml_encode(toml_decode("a = $value")))->toBe("a = $value");
})->with(['07:32:00.05', '07:32:00.005', '07:32:00.5', '1979-05-27T07:32:00.05']);

it('rejects invalid strings and numbers', function (string $toml, ?string $message) {
    expect(static fn () => toml_decode($toml))->toThrow(TomlError::class, $message);
})->with([
    'bare minus' => ["a = -\n", 'unexpected sign without digits'],
    'bare minus in an array' => ["a = [-]\n", 'unexpected sign without digits'],
    'exponent sign without digits' => ["a = 1e-\n", 'unexpected sign without digits'],
    'first surrogate' => ["a = \"\\uD800\"\n", 'unexpected invalid unicode character'],
    'last surrogate' => ["a = \"\\uDFFF\"\n", 'unexpected invalid unicode character'],
    'just above U+10FFFF' => ["a = \"\\U00110000\"\n", 'unexpected invalid unicode character'],
    'wraps to "A" in the polyfill' => ["a = \"\\U00200041\"\n", 'unexpected invalid unicode character'],
    'wraps to "\0" in the polyfill' => ["a = \"\\U00200000\"\n", 'unexpected invalid unicode character'],
    'lone carriage return after a line ending backslash' => ["a = \"\"\"x\\\n\ry\"\"\"\n", null],
    'line ending backslash with only whitespace up to EOF' => ["a = \"\"\"x\\\n \n", null],
]);

it('rejects invalid date-time strings', function (Closure $create, string $message) {
    expect($create)->toThrow(TomlError::class, $message);
})->with([
    'no leading zero' => [fn () => new TomlDateTime('1979-5-27'), 'datetime format must have leading zero'],
    'February 30' => [fn () => new TomlDateTime('1979-02-30'), 'invalid local date: days of February'],
    'local date-time without time' => [fn () => TomlLocalDateTime::fromString('1979-05-27'), 'invalid local date-time format'],
]);

it('does not redeclare the helper functions', function () {
    require __DIR__.'/../../src/helpers.php';

    expect(toml_decode('a = 1', true))->toBe(['a' => 1]);
});
