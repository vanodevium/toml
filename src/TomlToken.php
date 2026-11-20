<?php

declare(strict_types=1);

namespace Devium\Toml;

/**
 * @internal
 */
final readonly class TomlToken
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
        public string $type,
        public mixed $value = null,
        public bool $isMultiline = false
    ) {}
}
