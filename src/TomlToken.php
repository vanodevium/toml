<?php

namespace Devium\Toml;

/**
 * @internal
 */
final class TomlToken
{
    public const EOF = 'EOF';

    public const BARE = 'BARE';

    public const WHITESPACE = 'WHITESPACE';

    public const NEWLINE = 'NEWLINE';

    public const STRING = 'STRING';

    public const COMMENT = 'COMMENT';

    public const EQUALS = 'EQUALS';

    public const PERIOD = 'PERIOD';

    public const COMMA = 'COMMA';

    public const COLON = 'COLON';

    public const PLUS = 'PLUS';

    public const LEFT_CURLY_BRACKET = 'LEFT_CURLY_BRACKET';

    public const RIGHT_CURLY_BRACKET = 'RIGHT_CURLY_BRACKET';

    public const LEFT_SQUARE_BRACKET = 'LEFT_SQUARE_BRACKET';

    public const RIGHT_SQUARE_BRACKET = 'RIGHT_SQUARE_BRACKET';

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
