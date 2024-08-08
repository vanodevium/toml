<?php

namespace Devium\Toml;

/**
 * @internal
 */
final class TomlTokenizer
{
    public const PUNCTUATOR_OR_NEWLINE_TOKENS = [
        "\n" => 'NEWLINE',
        '=' => 'EQUALS',
        '.' => 'PERIOD',
        ',' => 'COMMA',
        ':' => 'COLON',
        '+' => 'PLUS',
        '{' => 'LEFT_CURLY_BRACKET',
        '}' => 'RIGHT_CURLY_BRACKET',
        '[' => 'LEFT_SQUARE_BRACKET',
        ']' => 'RIGHT_SQUARE_BRACKET',
    ];

    public const ESCAPES = [
        'b' => "\b",
        't' => "\t",
        'n' => "\n",
        'f' => "\f",
        'r' => "\r",
        '"' => '"',
        '\\' => '\\',
    ];

    protected TomlInputIterator $iterator;

    /**
     * @throws TomlError
     */
    public function __construct(string $input)
    {
        $this->iterator = new TomlInputIterator($this->validateInput($input));
    }

    public function getIteratorInput(): string
    {
        return $this->iterator->input;
    }

    public function getIteratorPosition(): int
    {
        return $this->iterator->pos;
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
        try {
            $token = $this->next();
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
            TomlInputIterator::EOF => TomlToken::fromArray(['type' => 'EOF']),
            default => throw new TomlError('unexpected character: '.$char),
        };
    }

    public function isPunctuatorOrNewline($char): bool
    {
        return array_key_exists($char, self::PUNCTUATOR_OR_NEWLINE_TOKENS);
    }

    public function isBare($char): bool
    {
        return ($char >= 'A' && $char <= 'Z') ||
            ($char >= 'a' && $char <= 'z') ||
            ($char >= '0' && $char <= '9') ||
            $char === '-' ||
            $char === '_';
    }

    public function scanBare($start): TomlToken
    {
        while ($this->isBare($this->iterator->peek())) {
            $this->iterator->next();
        }

        return $this->returnScan('BARE', $start);
    }

    public function returnScan(string $type, $start): TomlToken
    {
        return TomlToken::fromArray([
            'type' => $type,
            'value' => TomlUtils::stringSlice($this->iterator->input, $start, $this->iterator->pos + 1),
        ]);
    }

    public function scanWhitespace($start): TomlToken
    {
        while ($this->isWhitespace($this->iterator->peek())) {
            $this->iterator->next();
        }

        return $this->returnScan('WHITESPACE', $start);
    }

    public function isWhitespace($char): bool
    {
        return $char === ' ' || $char === "\t";
    }

    public function scanComment($start): TomlToken
    {
        while (! $this->iterator->isEOF()) {

            $char = $this->iterator->peek();
            if (! $this->isControlCharacterOtherThanTab($char)) {
                $this->iterator->next();

                continue;
            }

            return $this->returnScan('COMMENT', $start);
        }

        return $this->returnScan('COMMENT', $start);
    }

    public function isEOF(): bool
    {
        return $this->iterator->isEOF();
    }

    public function isControlCharacterOtherThanTab($char): bool
    {
        return $this->isControlCharacter($char) && $char !== "\t";
    }

    public function isControlCharacter($char): bool
    {
        return ($char >= "\u{0}" && $char < "\u{20}") || $char === "\u{7f}";
    }

    /**
     * @throws TomlError
     */
    public function scanLiteralString(): TomlToken
    {
        return $this->scanString("'");
    }

    /**
     * @throws TomlError
     */
    public function scanString($delimiter): TomlToken
    {
        $isMultiline = false;
        if ($this->iterator->take($delimiter)) {
            if (! $this->iterator->take($delimiter)) {
                return TomlToken::fromArray([
                    'type' => 'STRING',
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
        for (; ;) {
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
                                    /** @noinspection PhpStatementHasEmptyBodyInspection */
                                    while ($this->iterator->take(' ', "\t", "\n")) {
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
            'type' => 'STRING',
            'value' => $value,
            'isMultiline' => $isMultiline,
        ]);
    }

    public function isEscaped($char): bool
    {
        return array_key_exists($char, self::ESCAPES);
    }

    public function isUnicodeCharacter($char): bool
    {
        if ($char === false) {
            return false;
        }

        return $char <= "\u{10ffff}";
    }

    /**
     * @throws TomlError
     */
    public function scanBasicString(): TomlToken
    {
        return $this->scanString('"');
    }

    /**
     * @throws TomlError
     */
    public function sequence(...$types): array
    {
        return array_map(fn ($type) => $this->expect($type), $types);
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

    /**
     * @throws TomlError
     */
    protected function validateInput(string $input): string
    {
        if (preg_match('/("""\n?.*\\\\ )|(\\\\ .*\n?""")/', $input)) {
            throw new TomlError('unexpected \\<space> escaping');
        }

        return $input;
    }
}
