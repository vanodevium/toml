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
        'b' => "\b",
        't' => "\t",
        'n' => "\n",
        'f' => "\f",
        'r' => "\r",
        '"' => '"',
        '\\' => '\\',
    ];

    protected TomlInputIterator $iterator;

    private const BARE_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';

    private const CONTROL_NO_TAB = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x0a\x0b\x0c\x0d\x0e\x0f\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c\x1d\x1e\x1f\x7f";

    private int $peekPos = -2;

    private int $peekEnd = -2;

    private ?TomlToken $peekToken = null;

    /**
     * @throws TomlError
     */
    public function __construct(string $input)
    {
        $this->iterator = new TomlInputIterator($this->validateInput($input));
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
    public function assert(...$types): void
    {
        if (! $this->take(...$types)) {
            throw new TomlError('unexpected assertion');
        }
    }

    /**
     * @throws TomlError
     */
    public function take(...$types): bool
    {
        $token = $this->peek();
        if (in_array($token->type, $types, true)) {
            $this->next();

            return true;
        }

        return false;
    }

    /**
     * @throws TomlError
     */
    public function peek(): TomlToken
    {
        $pos = $this->iterator->pos;
        if ($pos === $this->peekPos) {
            return $this->peekToken;
        }
        try {
            $token = $this->next();
            $this->peekPos = $pos;
            $this->peekEnd = $this->iterator->pos;
            $this->peekToken = $token;
            $this->iterator->pos = $pos;

            return $token;
        } catch (TomlError $e) {
            $this->iterator->pos = $pos;
            throw $e;
        }
    }

    /**
     * @throws TomlError
     */
    public function next(): TomlToken
    {
        if ($this->iterator->pos === $this->peekPos) {
            $this->iterator->pos = $this->peekEnd;
            $this->peekPos = -2;

            return $this->peekToken;
        }
        $char = $this->iterator->next();
        $start = $this->iterator->pos;
        if ($this->isPunctuatorOrNewline($char)) {
            return TomlToken::fromArray([
                'type' => self::PUNCTUATOR_OR_NEWLINE_TOKENS[$char],
                'value' => $char,
            ]);
        }
        if ($this->isBare($char)) {
            return $this->scanBare($start);
        }

        return match ($char) {
            ' ', "\t" => $this->scanWhitespace($start),
            '#' => $this->scanComment($start),
            "'" => $this->scanLiteralString(),
            '"' => $this->scanBasicString(),
            TomlInputIterator::EOF => TomlToken::fromArray(['type' => TomlToken::EOF]),
            default => throw new TomlError('unexpected character: '.$char),
        };
    }

    protected function isPunctuatorOrNewline($char): bool
    {
        return array_key_exists($char, self::PUNCTUATOR_OR_NEWLINE_TOKENS);
    }

    protected function isBare($char): bool
    {
        return ($char >= 'A' && $char <= 'Z') ||
            ($char >= 'a' && $char <= 'z') ||
            ($char >= '0' && $char <= '9') ||
            $char === '-' ||
            $char === '_';
    }

    protected function scanBare($start): TomlToken
    {
        $this->iterator->pos += strspn($this->iterator->input, self::BARE_CHARS, $this->iterator->pos + 1);

        return $this->returnScan(TomlToken::BARE, $start);
    }

    protected function returnScan(string $type, $start): TomlToken
    {
        return TomlToken::fromArray([
            'type' => $type,
            'value' => TomlUtils::stringSlice($this->getInput(), $start, $this->getPosition() + 1),
        ]);
    }

    public function getInput(): string
    {
        return $this->iterator->input;
    }

    public function getPosition(): int
    {
        return $this->iterator->pos;
    }

    protected function scanWhitespace($start): TomlToken
    {
        $this->iterator->pos += strspn($this->iterator->input, " \t", $this->iterator->pos + 1);

        return $this->returnScan(TomlToken::WHITESPACE, $start);
    }

    protected function isWhitespace($char): bool
    {
        return $char === ' ' || $char === "\t";
    }

    protected function scanComment($start): TomlToken
    {
        $this->iterator->pos += strcspn($this->iterator->input, self::CONTROL_NO_TAB, $this->iterator->pos + 1);

        return $this->returnScan(TomlToken::COMMENT, $start);
    }

    public function isEOF(): bool
    {
        return $this->iterator->isEOF();
    }

    protected function isControlCharacterOtherThanTab($char): bool
    {
        return $this->isControlCharacter($char) && $char !== "\t";
    }

    protected function isControlCharacter($char): bool
    {
        return ($char >= "\u{0}" && $char < "\u{20}") || $char === "\u{7f}";
    }

    /**
     * @throws TomlError
     */
    protected function scanLiteralString(): TomlToken
    {
        return $this->scanString("'");
    }

    /**
     * @throws TomlError
     */
    protected function scanString($delimiter): TomlToken
    {
        $isMultiline = false;
        if ($this->iterator->take($delimiter)) {
            if (! $this->iterator->take($delimiter)) {
                return TomlToken::fromArray([
                    'type' => TomlToken::STRING,
                    'value' => '',
                    'isMultiline' => false,
                ]);
            }
            $isMultiline = true;
        }
        if ($isMultiline) {
            $this->iterator->take("\n");
        }
        $value = '';
        $input = $this->iterator->input;
        $stop = $delimiter.'\\'.self::CONTROL_NO_TAB;
        for (; ;) {
            $p = $this->iterator->pos + 1;
            $n = strcspn($input, $stop, $p);
            if ($n > 0) {
                $value .= substr($input, $p, $n);
                $this->iterator->pos += $n;
                if ($this->iterator->isEOF()) {
                    throw new TomlError('unexpected EOF or control character: '.$input[$p + $n - 1]);
                }
            }
            $char = $this->iterator->next();
            switch ($char) {
                case "\n":
                    if (! $isMultiline) {
                        throw new TomlError('unexpected multiline value');
                    }
                    $value .= $char;

                    continue 2;
                case $delimiter:
                    if ($isMultiline) {
                        if (! $this->iterator->take($delimiter)) {
                            $value .= $delimiter;

                            continue 2;
                        }
                        if (! $this->iterator->take($delimiter)) {
                            $value .= $delimiter;
                            $value .= $delimiter;

                            continue 2;
                        }
                        if ($this->iterator->take($delimiter)) {
                            $value .= $delimiter;
                        }
                        if ($this->iterator->take($delimiter)) {
                            $value .= $delimiter;
                        }
                    }
                    break;
                default:
                    if ($this->iterator->isEOF() || $this->isControlCharacterOtherThanTab($char)) {
                        throw new TomlError('unexpected EOF or control character: '.$char);
                    }
                    switch ($delimiter) {
                        case "'":
                            $value .= $char;

                            continue 3;
                        case '"':
                            if ($char === '\\') {
                                $char = $this->iterator->next();
                                if ($this->isEscaped($char)) {
                                    $value .= $char === 'b' ? chr(8) : self::ESCAPES[$char];

                                    continue 3;
                                }
                                if ($char === 'u' || $char === 'U') {
                                    $size = $char === 'u' ? 4 : 8;
                                    $codePoint = '';
                                    for ($i = 0; $i < $size; $i++) {
                                        $char = $this->iterator->next();
                                        if ($char === TomlInputIterator::EOF || ! TomlUtils::isUnicode($char)) {
                                            throw new TomlError('unexpected EOF or invalid unicode: '.$char);
                                        }
                                        $codePoint .= $char;
                                    }
                                    $result = mb_chr(intval($codePoint, 16));
                                    if (! $this->isUnicodeCharacter($result)) {
                                        throw new TomlError('unexpected invalid unicode character: '.$char);
                                    }
                                    $value .= $result;

                                    continue 3;
                                }
                                if ($isMultiline && ($this->isWhitespace($char) || $char === "\n")) {
                                    for (; ;) {
                                        if (! $this->iterator->take(' ', "\t", "\n")) {
                                            break;
                                        }
                                    }

                                    continue 3;
                                }
                                throw new TomlError('unexpected character: '.$char);
                            }
                            $value .= $char;

                            continue 3;
                    }
            }
            break;
        }

        return TomlToken::fromArray([
            'type' => TomlToken::STRING,
            'value' => $value,
            'isMultiline' => $isMultiline,
        ]);
    }

    protected function isEscaped($char): bool
    {
        return array_key_exists($char, self::ESCAPES);
    }

    protected function isUnicodeCharacter($char): bool
    {
        if ($char === false) {
            return false;
        }

        return $char <= "\u{10ffff}";
    }

    /**
     * @throws TomlError
     */
    protected function scanBasicString(): TomlToken
    {
        return $this->scanString('"');
    }

    /**
     * @throws TomlError
     */
    public function sequence(...$types): array
    {
        return array_map($this->expect(...), $types);
    }

    /**
     * @throws TomlError
     */
    public function expect($type): TomlToken
    {
        $token = $this->next();
        if ($token->type !== $type) {
            throw new TomlError('unexpected token type: '.$token->type);
        }

        return $token;
    }
}
