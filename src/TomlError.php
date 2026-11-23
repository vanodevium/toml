<?php

namespace Devium\Toml;

use Exception;

final class TomlError extends Exception
{
    public const RE = '/\r\n|\n|\r/';

    public mixed $tomlLine = 0;

    public mixed $tomlColumn = 0;

    public string $tomlCodeBlock = '';

    public function __construct(
        string $message = '',
        string $toml = '',
        int $position = -1,
    ) {
        if ($position < 0) {
            parent::__construct($message);

            return;
        }

        [$line, $column] = $this->getLineColFromPosition($toml, $position);
        $codeBlock = $this->makeCodeBlock($toml, $line, $column);

        $this->tomlLine = $line;
        $this->tomlColumn = $column;
        $this->tomlCodeBlock = $codeBlock;

        parent::__construct("Invalid TOML document: $message\n\n$codeBlock");
    }

    /**
     * @return array{int, int} 1-based line and 0-based column
     */
    protected function getLineColFromPosition(string $string, int $position): array
    {
        $lines = preg_split(self::RE, substr($string, 0, $position));

        return [count($lines), strlen(array_pop($lines))];
    }

    /**
     * Renders the lines around $line with a caret under $column.
     */
    protected function makeCodeBlock(string $string, int $line, int $column): string
    {
        $lines = preg_split(self::RE, $string);
        $codeBlock = '';

        // width of the largest printed line number
        $numberLen = strlen((string) ($line + 1));

        for ($i = $line - 1; $i <= $line + 1; $i++) {
            $l = $lines[$i - 1] ?? null;
            if ($l === null) {
                continue;
            }

            $codeBlock .= str_pad((string) $i, $numberLen).":  $l\n";

            if ($i === $line) {
                $codeBlock .= ' '.str_repeat(' ', $numberLen + $column + 2)."^\n";
            }
        }

        return $codeBlock;
    }
}
