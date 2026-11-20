<?php

declare(strict_types=1);

use Devium\Toml\TomlError;
use Devium\Toml\TomlTag;

it('untags tagged values', function () {
    expect(TomlTag::untagObject((object) ['type' => 'string', 'value' => 'x']))->toBe('x')
        ->and(TomlTag::untagObject((object) ['type' => 'bool', 'value' => 'true']))->toBeTrue()
        ->and(TomlTag::untagObject((object) ['type' => 'integer', 'value' => '42']))->toBe(42)
        ->and(TomlTag::untagObject([(object) ['type' => 'float', 'value' => '-inf']]))->toBe([-INF]);
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
