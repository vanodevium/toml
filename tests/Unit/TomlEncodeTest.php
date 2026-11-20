<?php

/** @noinspection StaticClosureCanBeUsedInspection */

declare(strict_types=1);

use Devium\Toml\TomlError;
use Devium\Toml\TomlLocalDate;
use Devium\Toml\TomlLocalDateTime;
use Devium\Toml\TomlLocalTime;

it('encodes a TOML document', function (bool $asArray) {
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
  ]
}
JSON_STRING;

    expect(toml_encode(json_decode($json, $asArray)))->toBe(<<<'TOML_STRING'
title = "TOML Example"

[owner]
name = "Tom Preston-Werner"
dob = "1979-05-27T15:32:00.000Z"

[database]
enabled = true
ports = [ 8000, 8001, 8002 ]
data = [ [ "delta", "phi" ], [ 3.14 ] ]

[database.temp_targets]
cpu = 79.5
case = 72

[servers]
[servers.alpha]
ip = "10.0.0.1"
role = "frontend"

[servers.beta]
ip = "10.0.0.2"
role = "backend"

[[fruits]]
name = "apple"

[fruits.physical]
color = "red"
shape = "round"

[[fruits.varieties]]
name = "red delicious"

[[fruits.varieties]]
name = "granny smith"

[[fruits]]
name = "banana"

[[fruits.varieties]]
name = "plantain"
TOML_STRING);
})->with([
    'object' => false,
    'array' => true,
]);

it('skips null values in tables', function () {
    $data = ['nullValue' => null, 'list' => [1, 2, 3, 4], 'keyValue' => ['a' => 1, 'b' => 2, 'c' => null, 'd' => 4, 'e' => 5]];
    $expected = "list = [ 1, 2, 3, 4 ]\n\n[keyValue]\na = 1\nb = 2\nd = 4\ne = 5";

    expect(toml_encode($data))->toBe($expected)
        ->and(toml_encode(json_decode(json_encode($data))))->toBe($expected);
});

it('encodes DateTimeInterface', function (bool $asObject) {
    $data = [
        DateTimeInterface::class => new DateTime('1979-05-27T00:32:00.999999-07:00'),
        DateTimeImmutable::class => new DateTimeImmutable('1979-05-27T00:32:00.999999-07:00'),
    ];

    expect(toml_encode($asObject ? (object) $data : $data))
        ->toBe("DateTimeInterface = 1979-05-27T07:32:00.999Z\nDateTimeImmutable = 1979-05-27T07:32:00.999Z");
})->with([
    'object' => true,
    'array' => false,
]);

it('encodes nested arrays and objects inside stdClass', function (array|object $value, string $expected) {
    expect(toml_encode((object) ['a' => $value]))->toBe($expected);
})->with([
    'array of mixed values' => [[['x' => 1], 5], 'a = [ { x = 1 }, 5 ]'],
    'table' => [['k' => 1], "[a]\nk = 1"],
    'ArrayObject table' => [fn () => new ArrayObject(['k' => 1]), "[a]\nk = 1"],
    'ArrayObject array table' => [fn () => [new ArrayObject(['k' => 1])], "[[a]]\nk = 1"],
    'public properties only' => [fn () => new class
    {
        private int $x = 1;

        protected int $z = 3;

        public int $y = 2;
    }, "[a]\ny = 2"],
]);

it('keeps the parent of tables nested under the key 0', function () {
    $data = ['0' => ['sub' => ['k' => 1], 'list' => [['k' => 2]]]];

    expect(toml_encode($data))->toBe("[0]\n[0.sub]\nk = 1\n\n[[0.list]]\nk = 2")
        ->and(toml_decode(toml_encode($data), true))->toEqual($data);
});

it('encodes special floats, empty arrays and local date-times', function () {
    expect(toml_encode([
        'nan' => NAN,
        'inf' => INF,
        '-inf' => -INF,
        'empty' => [],
        'date' => new TomlLocalDate(1979, 5, 27),
        'time' => new TomlLocalTime(7, 32, 0, 999),
        'date-time' => new TomlLocalDateTime(1979, 5, 27, 7, 32, 0, 0),
    ]))->toBe(<<<'TOML_STRING'
nan = nan
inf = inf
-inf = -inf
empty = []
date = 1979-05-27
time = 07:32:00.999
date-time = 1979-05-27T07:32:00
TOML_STRING);
});

it('rejects unsupported values', function (Closure $data, string $message) {
    expect(static fn () => toml_encode($data()))->toThrow(TomlError::class, $message);
})->with([
    'null in an array' => [fn () => ['list' => [1, 2, null, 3, 4]], 'arrays cannot contain null values'],
    'resource' => [fn () => ['r' => fopen('php://memory', 'r')], 'unrecognized type: resource'],
]);
