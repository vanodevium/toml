<?php

namespace Devium\Toml;

use Exception;

final class TomlError extends Exception
{
    public const RE = '/\r\n|\n|\r/';

    public mixed $tomlLine;

    public mixed $tomlColumn;

    public string $tomlCodeBlock;

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

    protected function getLineColFromPosition($string, $position): array
    {
        $lines = preg_split(self::RE, TomlUtils::stringSlice($string, 0, $position));

        return [count($lines), strlen((string) array_pop($lines))];
    }

    protected function makeCodeBlock($string, $line, $column): string
    {
        $lines = preg_split(self::RE, (string) $string);
        $codeBlock = '';

        $numberLen = ((int) log10($line + 1) | 0) + 1;

        for ($i = $line - 1; $i <= $line + 1; $i++) {
            $l = $lines[$i - 1] ?? null;
            if (! $l) {
                continue;
            }

            $codeBlock .= str_pad($i, $numberLen).":  $l\n";

            if ($i === $line) {
                $codeBlock .= ' '.str_repeat(' ', $numberLen + $column + 2)."^\n";
            }
        }

        return $codeBlock;
    }
}
