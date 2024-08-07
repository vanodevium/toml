<?php

namespace Devium\Toml;

use Exception;

final class TomlError extends Exception
{
    public mixed $tomlLine;

    public mixed $tomlColumn;

    public string $tomlCodeBlock;

    public function __construct(
        string $message = '',
        array $options = [
            'toml' => '',
            'ptr' => 0,
        ],
    ) {
        [$line, $column] = $this->getLineColFromPtr($options['toml'], $options['ptr']);
        $codeBlock = $this->makeCodeBlock($options['toml'], $line, $column);

        parent::__construct("Invalid TOML document: $message\n\n$codeBlock");

        $this->tomlLine = $line;
        $this->tomlColumn = $column;
        $this->tomlCodeBlock = $codeBlock;
    }

    protected function getLineColFromPtr($string, $pointer): array
    {
        $lines = preg_split('/\r\n|\n|\r/', TomlUtils::stringSlice($string, 0, $pointer));

        return [count($lines), strlen((string) array_pop($lines))];
    }

    protected function makeCodeBlock($string, $line, $column): string
    {
        $lines = preg_split('/\r\n|\n|\r/', (string) $string);
        $codeBlock = '';

        $numberLen = ((int) log10($line + 1) | 0) + 1;

        for ($i = $line - 1; $i <= $line + 1; $i++) {
            $l = $lines[$i - 1] ?? null;
            if (! $l) {
                continue;
            }

            $codeBlock .= str_pad($i, $numberLen);
            $codeBlock .= ':  ';
            $codeBlock .= $l;
            $codeBlock .= "\n";

            if ($i === $line) {
                $codeBlock .= ' '.str_repeat(' ', $numberLen + $column + 2);
                $codeBlock .= "^\n";
            }
        }

        return $codeBlock;
    }
}
