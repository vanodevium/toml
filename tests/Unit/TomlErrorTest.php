<?php

use Devium\Toml\TomlError;

it('shows code block and caret',
    /**
     * @throws TomlError
     */
    function () {

        $message = <<<'MESSAGE'
Invalid TOML document: unexpected non-numeric value

5:  [owner]
6:  name = Tom Preston-Werner
             ^
7:  dob = 1979-05-31T07:32:00-08:00
MESSAGE;

        $toml = <<<'TOML_STRING'
# This is a TOML document

title = "TOML Example"

[owner]
name = Tom Preston-Werner
dob = 1979-05-31T07:32:00-08:00

[database]
enabled = true
ports = [ 8000, 8001, 8002 ]
data = [ ["delta", "phi"], [3.14] ]
temp_targets = { cpu = 79.5, case = 72.0 }
TOML_STRING;

        expect(static fn () => toml_decode($toml, true))->toThrow(TomlError::class, $message);
    });
