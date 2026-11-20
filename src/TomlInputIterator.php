<?php

declare(strict_types=1);

namespace Devium\Toml;

/**
 * Character cursor over the input; a CRLF pair is read as a single "\n".
 *
 * @internal
 */
final class TomlInputIterator
{
    public const EOF = '-1';

    public int $pos = -1;

    private readonly int $inputLength;

    public function __construct(public readonly string $input)
    {
        $this->inputLength = strlen($input);
    }

    public function take(string ...$chars): bool
    {
        $char = $this->peek();
        if ($char !== self::EOF && in_array($char, $chars, true)) {
            $this->next();

            return true;
        }

        return false;
    }

    /**
     * Moves past the run of $chars that starts at the next character. When $chars has "\n",
     * a CRLF pair counts as one too; a lone "\r" stops the run.
     */
    public function skip(string $chars): void
    {
        $pos = $this->pos + 1;
        $pos += strspn($this->input, $chars, $pos);
        if (str_contains($chars, "\n")) {
            while (substr_compare($this->input, "\r\n", $pos, 2) === 0) {
                $pos += 2;
                $pos += strspn($this->input, $chars, $pos);
            }
        }

        $this->pos = $pos - 1;
    }

    public function peek(): string
    {
        $pos = $this->pos + 1;
        if ($pos >= $this->inputLength) {
            return self::EOF;
        }

        $char = $this->input[$pos];
        if ($char === "\r" && $pos + 1 < $this->inputLength && $this->input[$pos + 1] === "\n") {
            return "\n";
        }

        return $char;
    }

    public function next(): string
    {
        $pos = $this->pos + 1;
        if ($pos >= $this->inputLength) {
            return self::EOF;
        }

        $char = $this->input[$pos];
        if ($char === "\r" && $pos + 1 < $this->inputLength && $this->input[$pos + 1] === "\n") {
            $this->pos = $pos + 1;

            return "\n";
        }

        $this->pos = $pos;

        return $char;
    }

    public function isEOF(): bool
    {
        return $this->pos + 1 >= $this->inputLength;
    }
}
