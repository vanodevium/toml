<?php

/** @noinspection StaticClosureCanBeUsedInspection */

declare(strict_types=1);

use Devium\Toml\TomlError;

it('shows the code block and caret', function (string $toml, string $message) {
    expect(/**
     * @throws TomlError
     */ static fn () => toml_decode($toml))->toThrow(TomlError::class, $message);
})->with([
    'non-numeric value' => [
        <<<'TOML_STRING'
# This is a TOML document

title = "TOML Example"

[owner]
name = Tom Preston-Werner
dob = 1979-05-31T07:32:00-08:00

[database]
enabled = true
TOML_STRING,
        <<<'MESSAGE'
Invalid TOML document: unexpected non-numeric value

5:  [owner]
6:  name = Tom Preston-Werner
             ^
7:  dob = 1979-05-31T07:32:00-08:00
MESSAGE,
    ],
    'invalid array order' => [
        <<<'TOML_STRING'
# This creates a as a normal (non-array) table
[[a.b]]
x = 1

# This requires a to be an array table
[[a]]
y = 2
TOML_STRING,
        <<<'MESSAGE'
Invalid TOML document: key duplication

5:  # This requires a to be an array table
6:  [[a]]
         ^
7:  y = 2
MESSAGE,
    ],
    'empty lines' => ["a = 1\n\n= 2\n\nb = 3", "Invalid TOML document: unexpected token type: EQUALS\n\n2:  \n3:  = 2\n    ^\n4:  \n"],
    'line that is just 0' => ["a = 1\n0\nb = 2", "Invalid TOML document: unexpected assertion\n\n1:  a = 1\n2:  0\n    ^\n3:  b = 2\n"],
]);

it('reports an error at position 0 on the first line', function () {
    try {
        toml_decode("=\nb = 1\nc = 2\n");
        $this->fail('TomlError was not thrown');
    } catch (TomlError $e) {
        expect($e)
            ->getMessage()->toBe("Invalid TOML document: unexpected token type: EQUALS\n\n1:  =\n    ^\n2:  b = 1\n")
            ->tomlLine->toBe(1)
            ->tomlColumn->toBe(0);
    }
});

it('rejects invalid documents', function (string $toml, string $message) {
    expect(/**
     * @throws TomlError
     */ static fn () => toml_decode($toml))->toThrow(TomlError::class, $message);
})->with([
    'invalid UTF-8' => ["a = \"\xff\"", 'invalid UTF-8 sequence'],
    'garbage after a value' => ['a = 1 b', 'unexpected assertion'],
    'multiline string key' => ['"""a""" = 1', 'unexpected string value'],
    'too deep nesting' => ['a = '.str_repeat('[', 513), 'maximum nesting depth exceeded'],
    'sign in a fraction' => ['a = 1.-5', 'unexpected sign (+/-)'],
    'leading zero' => ['a = 01', 'unexpected leading zero'],
    'radix prefix without digits' => ['a = 0x_1', 'unexpected number formatting'],
    'invalid hex digits' => ['a = 0xo1', 'unexpected binary number formatting'],
    'double underscore' => ['a = 1__2', 'unexpected underscore symbol'],
    'invalid binary digit' => ['a = 0b12', 'unexpected unparsed part of numeric value'],
    'float with leading zero' => ['a = 01.5', 'unexpected float formatting'],
    'fraction after exponent' => ['a = 1e5.5', 'unexpected unparsed part of float value'],
    'garbage after a fraction' => ['a = 1.5x', 'unexpected unparsed part of float value'],
    'invalid offset date-time' => ['a = 1979-02-30T00:00:00Z', 'error during datetime parsing'],
    'local date without leading zero' => ['a = 1979-5-27', 'invalid local date format "1979-5-27"'],
    'invalid local date' => ['a = 1979-13-01', 'invalid local date format "1979-13-01"'],
    'February 29 in a common year' => ['a = 1979-02-29', 'invalid local date: days of February'],
    'invalid local time' => ['a = 25:00:00', 'invalid local time format "25:00:00"'],
    'local time without leading zero' => ['a = 7:00:00', 'invalid local time format "7:00:00"'],
    'truncated date-time' => ['a = 1979-05-27T07:32', 'unexpected token type: EOF'],
    'backslash space in multiline string' => ['a = """\ x"""', 'unexpected \<space> escaping'],
    'unterminated string' => ['a = "abc', 'unexpected EOF or control character: c'],
    'opening quote at EOF' => ['a = "', 'unexpected EOF or control character'],
    'newline in basic string' => ["a = \"x\ny\"", 'unexpected multiline value'],
    'invalid unicode escape' => ['a = "\uZZZZ"', 'unexpected EOF or invalid unicode: Z'],
    'unknown escape' => ['a = "\q"', 'unexpected character: q'],
    'duplicate key' => ["a = 1\na = 2", 'key duplication'],
    'key over dotted key table' => ["a.b = 1\na = 2", 'key duplication'],
    'table over array table' => ["[[a]]\n[a]", 'broken key'],
    'table under a value' => ["a = 1\n[a.b]", 'key duplication'],
    'duplicate table' => ["[a]\n[a]", 'key duplication'],
    'array table over a value' => ["a = 1\n[[a]]", 'key duplication'],
    'array table over a value of an entry' => ["[[a]]\nb = 1\n[[a.b]]", 'key duplication'],
    'array table under a value' => ["a = 1\n[[a.b]]", 'key duplication'],
    'array table under an inline table' => ["a = {}\n[[a.b]]", 'key duplication'],
    'array table under an array' => ["a = [1]\n[[a.b]]", 'key duplication'],
    'array table under a value of an entry' => ["[[a]]\nb = 1\n[[a.b.c]]", 'key duplication'],
    'array table over a header table' => ["[[a]]\n[[a.b.c]]\n[[a.b]]", 'key duplication'],
    'array table over a dotted key table' => ["[[a]]\nb.c = 1\n[[a.b]]", 'key duplication'],
    'array table over a quoted dotted table' => ["[a.\"b.c\"]\n[[a.\"b.c\"]]", 'key duplication'],
    'value over a header table' => ["[a.b.c]\n[a]\nb = 1", 'key duplication'],
    'dotted key over a header table' => ["[a.b.c]\n[a]\nb.d = 1", 'key duplication'],
    'exponent sign without digits' => ['a = 1e+x', 'unexpected non-numeric value'],
    'fraction without digits' => ['a = 1.e5', 'unexpected non-numeric value'],
]);
