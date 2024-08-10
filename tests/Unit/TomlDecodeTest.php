<?php

use Devium\Toml\TomlError;

it('can decode toml',
    /**
     * @throws TomlError
     */
    function () {
        $json = <<<'JSON'
{
  "title": "TOML Example",
  "owner": {
    "name": "Tom Preston-Werner",
    "dob": "1979-05-27T15:32:00.000Z"
  },
  "database": {
    "enabled": true,
    "ports": [
      8000,
      8001,
      8002
    ],
    "data": [
      [
        "delta",
        "phi"
      ],
      [
        3.14
      ]
    ],
    "temp_targets": {
      "cpu": 79.5,
      "case": 72
    }
  },
  "servers": {
    "alpha": {
      "ip": "10.0.0.1",
      "role": "frontend"
    },
    "beta": {
      "ip": "10.0.0.2",
      "role": "backend"
    }
  },
  "fruits": [
    {
      "name": "apple",
      "physical": {
        "color": "red",
        "shape": "round"
      },
      "varieties": [
        { "name": "red delicious" },
        { "name": "granny smith" }
      ]
    },
    {
      "name": "banana",
      "varieties": [
        { "name": "plantain" }
      ]
    }
  ]
}
JSON;
        $toml = <<<'TOML_WRAP'
# This is a TOML document

title = "TOML Example"

[owner]
name = "Tom Preston-Werner"
dob = 1979-05-27T07:32:00-08:00

[database]
enabled = true
ports = [ 8000, 8001, 8002 ]
data = [ ["delta", "phi"], [3.14] ]
temp_targets = { cpu = 79.5, case = 72.0 }

[servers]

[servers.alpha]
ip = "10.0.0.1"
role = "frontend"

[servers.beta]
ip = "10.0.0.2"
role = "backend"

[[fruits]]
name = "apple"

[fruits.physical]  # sub table
color = "red"
shape = "round"

[[fruits.varieties]]  # nested array of tables
name = "red delicious"

[[fruits.varieties]]
name = "granny smith"


[[fruits]]
name = "banana"

[[fruits.varieties]]
name = "plantain"
TOML_WRAP;
        expect(toml_decode($toml))->toEqual(json_decode($json, false))
            ->and(toml_decode($toml, true))->toEqual(json_decode($json, true));
    });

it('can decode TOML datetime formats',
    /**
     * @throws TomlError
     */
    function () {
        $toml = <<<'TOML'
DateTimeInterface = 1979-05-27T07:32:00.999Z
DateTimeImmutable = 1979-05-27T07:32:00.999Z
TOML;

        $data = [
            DateTimeInterface::class => new DateTime('1979-05-27T07:32:00.999'),
            DateTimeImmutable::class => new DateTimeImmutable('1979-05-27T07:32:00.999'),
        ];

        $expected = (object) $data;

        expect(toml_decode($toml))->toEqual($expected)
            ->and(toml_decode($toml, true))->toEqual($data);
    });
