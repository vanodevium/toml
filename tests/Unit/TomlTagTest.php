<?php

/** @noinspection StaticClosureCanBeUsedInspection */

declare(strict_types=1);

use Devium\Toml\TomlDateTime;
use Devium\Toml\TomlError;
use Devium\Toml\TomlLocalDate;
use Devium\Toml\TomlLocalDateTime;
use Devium\Toml\TomlLocalTime;
use Devium\Toml\TomlTag;

it('untags tagged values', function (object|array $tagged, mixed $expected) {
    expect(TomlTag::untagObject($tagged))->toBe($expected);
})->with([
    'string' => [(object) ['type' => 'string', 'value' => 'x'], 'x'],
    'bool' => [(object) ['type' => 'bool', 'value' => 'true'], true],
    'integer' => [(object) ['type' => 'integer', 'value' => '42'], 42],
    'list of floats' => [[(object) ['type' => 'float', 'value' => '-inf']], [-INF]],
]);

it('untags nan', function () {
    expect(TomlTag::untagObject((object) ['type' => 'float', 'value' => 'nan']))->toBeNan();
});

it('rejects a type that is not one of the tag strings', function (mixed $type) {
    expect(static fn () => TomlTag::untagObject((object) ['type' => $type, 'value' => 'x']))
        ->toThrow(TomlError::class, 'cannot untag object');
})->with([
    'true' => true,
    'false' => false,
    'int' => 1,
    'null' => null,
    'unknown string' => 'array',
]);

it('tags values and untags them back', function () {
    $values = [
        'int' => 1,
        'float' => 1.5,
        'float string' => '1.5',
        'string' => 'x',
        'bool' => false,
        'inf' => INF,
        '-inf' => -INF,
        'datetime' => new TomlDateTime('1979-05-27T07:32:00Z'),
        'date' => new TomlLocalDate(1979, 5, 27),
        'time' => new TomlLocalTime(7, 32, 0, 0),
        'date-time' => new TomlLocalDateTime(1979, 5, 27, 7, 32, 0, 999),
        'list' => [1],
        'table' => (object) ['k' => 'v'],
    ];
    $tagged = TomlTag::tagObject($values);

    expect($tagged)->toEqual([
        'int' => ['type' => 'integer', 'value' => '1'],
        'float' => ['type' => 'float', 'value' => '1.5'],
        'float string' => ['type' => 'float', 'value' => '1.5'],
        'string' => ['type' => 'string', 'value' => 'x'],
        'bool' => ['type' => 'bool', 'value' => 'false'],
        'inf' => ['type' => 'float', 'value' => 'inf'],
        '-inf' => ['type' => 'float', 'value' => '-inf'],
        'datetime' => ['type' => 'datetime', 'value' => '1979-05-27T07:32:00.000Z'],
        'date' => ['type' => 'date-local', 'value' => '1979-05-27'],
        'time' => ['type' => 'time-local', 'value' => '07:32:00'],
        'date-time' => ['type' => 'datetime-local', 'value' => '1979-05-27T07:32:00.999'],
        'list' => [['type' => 'integer', 'value' => '1']],
        'table' => (object) ['k' => ['type' => 'string', 'value' => 'v']],
    ])
        ->and(TomlTag::tagObject(NAN))->toBe(['type' => 'float', 'value' => 'nan'])
        ->and(TomlTag::untagObject(json_decode(json_encode($tagged))))->toEqual((object) [...$values, 'float string' => 1.5]);
});
