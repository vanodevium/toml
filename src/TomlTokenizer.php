<?php

namespace Devium\Toml;

/**
 * @internal
 */
final class TomlTokenizer
{
    protected const PUNCTUATOR_OR_NEWLINE_TOKENS = [
        "\n" => TomlToken::NEWLINE,
        '=' => TomlToken::EQUALS,
        '.' => TomlToken::PERIOD,
        ',' => TomlToken::COMMA,
        ':' => TomlToken::COLON,
        '+' => TomlToken::PLUS,
        '{' => TomlToken::LEFT_CURLY_BRACKET,
        '}' => TomlToken::RIGHT_CURLY_BRACKET,
        '[' => TomlToken::LEFT_SQUARE_BRACKET,
        ']' => TomlToken::RIGHT_SQUARE_BRACKET,
    ];

    protected const ESCAPES = [
        'b' => "\x08",
        't' => "\t",
        'n' => "\n",
        'f' => "\f",
        'r' => "\r",
        '"' => '"',
        '\\' => '\\',
    ];

    private const BARE_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';

    private const HEX_DIGITS = [
        '0' => true, '1' => true, '2' => true, '3' => true, '4' => true,
        '5' => true, '6' => true, '7' => true, '8' => true, '9' => true,
        'A' => true, 'B' => true, 'C' => true, 'D' => true, 'E' => true, 'F' => true,
        'a' => true, 'b' => true, 'c' => true, 'd' => true, 'e' => true, 'f' => true,
    ];

    private const CONTROL_NO_TAB = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x0a\x0b\x0c\x0d\x0e\x0f\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c\x1d\x1e\x1f\x7f";

    /** @var array<string, TomlToken> immutable single-character tokens, shared between instances */
    private static array $punctuatorTokens = [];

    private static ?TomlToken $eofToken = null;

    protected TomlInputIterator $iterator;

    private readonly string $input;

    private readonly int $length;

    /** Iterator position the cached peek token was read from; -2 when nothing is cached */
    private int $peekPos = -2;

    private int $peekEnd = -2;

    private ?TomlToken $peekToken = null;

    /**
     * @throws TomlError
     */
    public function __construct(string $input)
    {
        $this->input = $this->validateInput($input);
        $this->length = strlen($input);
        $this->iterator = new TomlInputIterator($this->input);

        if (! self::$eofToken instanceof TomlToken) {
            foreach (self::PUNCTUATOR_OR_NEWLINE_TOKENS as $char => $type) {
                self::$punctuatorTokens[$char] = new TomlToken($type, (string) $char);
            }
            self::$eofToken = new TomlToken(TomlToken::EOF);
        }
    }

    /**
     * @throws TomlError
     */
    protected function validateInput(string $input): string
    {
        if (str_contains($input, '\\ ') && preg_match('/("""\n?.*\\\\ )|(\\\\ .*\n?""")/', $input)) {
            throw new TomlError('unexpected \\<space> escaping');
        }

        return $input;
    }

    /**
     * @throws TomlError
     */
    public function assert(string ...$types): void
    {
        if (! $this->take(...$types)) {
            throw new TomlError('unexpected assertion');
        }
    }

    /**
     * @throws TomlError
     */
    public function take(string ...$types): bool
    {
        $type = $this->peek()->type;
        if ($type !== $types[0] && ! in_array($type, $types, true)) {
            return false;
        }

        $this->skip();

        return true;
    }

    /**
     * Consumes the token returned by the immediately preceding peek().
     */
    public function skip(): void
    {
        $this->iterator->pos = $this->peekEnd;
        $this->peekPos = -2;
    }

    /**
     * @throws TomlError
     */
    public function peek(): TomlToken
    {
        $iterator = $this->iterator;
        $pos = $iterator->pos;
        if ($pos === $this->peekPos) {
            return $this->peekToken;
        }

        try {
            $token = $this->next();
        } finally {
            $this->peekEnd = $iterator->pos;
            $iterator->pos = $pos;
        }

        $this->peekPos = $pos;
        $this->peekToken = $token;

        return $token;
    }

    /**
     * @throws TomlError
     */
    public function next(): TomlToken
    {
        $iterator = $this->iterator;
        if ($iterator->pos === $this->peekPos) {
            $iterator->pos = $this->peekEnd;
            $this->peekPos = -2;

            return $this->peekToken;
        }

        // same as $iterator->next(), inlined for the hot path; CRLF is left to the iterator
        $start = $iterator->pos + 1;
        if ($start >= $this->length) {
            return self::$eofToken;
        }

        $char = $this->input[$start];
        if ($char === "\r") {
            $char = $iterator->next();
            $start = $iterator->pos;
        } else {
            $iterator->pos = $start;
        }

        return self::$punctuatorTokens[$char] ?? match ($char) {
            ' ', "\t" => $this->scanSpan(TomlToken::WHITESPACE, $start, strspn($this->input, " \t", $start + 1)),
            '#' => $this->scanSpan(TomlToken::COMMENT, $start, strcspn($this->input, self::CONTROL_NO_TAB, $start + 1)),
            "'", '"' => $this->scanString($char),
            default => strspn($char, self::BARE_CHARS) === 1
                ? $this->scanSpan(TomlToken::BARE, $start, strspn($this->input, self::BARE_CHARS, $start + 1))
                : throw new TomlError('unexpected character: '.$char),
        };
    }

    /**
     * Consumes $length more bytes after $start and returns them, with the byte at $start, as one token.
     */
    private function scanSpan(string $type, int $start, int $length): TomlToken
    {
        $this->iterator->pos += $length;

        return new TomlToken($type, substr($this->input, $start, $length + 1));
    }

    public function getInput(): string
    {
        return $this->input;
    }

    public function getPosition(): int
    {
        return $this->iterator->pos;
    }

    public function isEOF(): bool
    {
        return $this->iterator->isEOF();
    }

    /**
     * Scans a literal (') or basic (") string, single or multiline; the opening delimiter is already consumed.
     *
     * @throws TomlError
     */
    protected function scanString(string $delimiter): TomlToken
    {
        $iterator = $this->iterator;
        $input = $this->input;

        $isMultiline = false;
        if ($iterator->take($delimiter)) {
            if (! $iterator->take($delimiter)) {
                return new TomlToken(TomlToken::STRING, '');
            }
            $isMultiline = true;
            // a newline right after the opening delimiter is trimmed
            $iterator->take("\n");
        }

        $value = '';
        $stop = $delimiter.'\\'.self::CONTROL_NO_TAB;
        for (; ;) {
            // copy the run of ordinary characters in one go
            $p = $iterator->pos + 1;
            $n = strcspn($input, $stop, $p);
            if ($n > 0) {
                $value .= substr($input, $p, $n);
                $iterator->pos += $n;
                if ($iterator->isEOF()) {
                    throw new TomlError('unexpected EOF or control character: '.$input[$p + $n - 1]);
                }
            }

            $char = $iterator->next();

            if ($char === "\n") {
                if (! $isMultiline) {
                    throw new TomlError('unexpected multiline value');
                }
                $value .= $char;

                continue;
            }

            if ($char === $delimiter) {
                if (! $isMultiline) {
                    break;
                }
                if (! $iterator->take($delimiter)) {
                    $value .= $delimiter;

                    continue;
                }
                if (! $iterator->take($delimiter)) {
                    $value .= $delimiter.$delimiter;

                    continue;
                }
                // up to two delimiters may directly precede the closing triple
                if ($iterator->take($delimiter)) {
                    $value .= $delimiter;
                }
                if ($iterator->take($delimiter)) {
                    $value .= $delimiter;
                }

                break;
            }

            if ($iterator->isEOF() || $this->isControlCharacterOtherThanTab($char)) {
                throw new TomlError('unexpected EOF or control character: '.$char);
            }

            if ($char === '\\' && $delimiter === '"') {
                $value .= $this->scanEscape($isMultiline);

                continue;
            }

            $value .= $char;
        }

        return new TomlToken(TomlToken::STRING, $value, $isMultiline);
    }

    /**
     * Scans the escape sequence after a backslash in a basic string.
     *
     * @throws TomlError
     */
    private function scanEscape(bool $isMultiline): string
    {
        $iterator = $this->iterator;
        $char = $iterator->next();

        if (isset(self::ESCAPES[$char])) {
            return self::ESCAPES[$char];
        }

        if ($char === 'u' || $char === 'U') {
            $size = $char === 'u' ? 4 : 8;
            $codePoint = '';
            for ($i = 0; $i < $size; $i++) {
                $char = $iterator->next();
                if (! isset(self::HEX_DIGITS[$char])) {
                    throw new TomlError('unexpected EOF or invalid unicode: '.$char);
                }
                $codePoint .= $char;
            }

            // ext-mbstring returns false for invalid code points, the polyfill wraps them above U+10FFFF
            $result = mb_chr(intval($codePoint, 16));
            if ($result === false || $result > "\u{10ffff}") {
                throw new TomlError('unexpected invalid unicode character: '.$char);
            }

            return $result;
        }

        // line ending backslash: trim all whitespace and newlines up to the next character
        if ($isMultiline && (in_array($char, [' ', "\t", "\n"], true))) {
            while ($iterator->take(' ', "\t", "\n")) {
                // skip
            }

            return '';
        }

        throw new TomlError('unexpected character: '.$char);
    }

    protected function isControlCharacterOtherThanTab(string $char): bool
    {
        return ($char < "\x20" && $char !== "\t") || $char === "\x7f";
    }

    /**
     * Reads the given token types in order and returns their concatenated values.
     *
     * @throws TomlError
     */
    public function concat(string ...$types): string
    {
        $value = '';
        foreach ($types as $type) {
            $value .= $this->expect($type)->value;
        }

        return $value;
    }

    /**
     * @throws TomlError
     */
    public function expect(string $type): TomlToken
    {
        $token = $this->next();
        if ($token->type !== $type) {
            throw new TomlError('unexpected token type: '.$token->type);
        }

        return $token;
    }
}
