<?php

namespace Devium\Toml;

/**
 * @internal
 */
final class TomlToken
{
    public function __construct(public string $type, public mixed $value = null, public bool $isMultiline = false) {}

    public static function fromArray(array $from): self
    {
        $type = $from['type'];
        $value = $from['value'] ?? null;
        $isMultiline = $from['isMultiline'] ?? false;

        return new self($type, $value, $isMultiline);
    }
}
