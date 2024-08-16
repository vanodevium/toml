<?php

namespace Devium\Toml;

/**
 * @internal
 */
final class TomlToken
{
    public function __construct(
        public readonly string $type,
        public readonly mixed $value = null,
        public readonly bool $isMultiline = false
    ) {}

    public static function fromArray(array $from): self
    {
        $type = $from['type'];
        $value = $from['value'] ?? null;
        $isMultiline = $from['isMultiline'] ?? false;

        return new self($type, $value, $isMultiline);
    }
}
