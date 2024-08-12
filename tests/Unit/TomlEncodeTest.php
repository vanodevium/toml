<?php

use Devium\Toml\TomlError;

it('can encode TOML',
    /**
     * @throws TomlError
     */
    function () {
        $json = <<<'JSON_STRING'
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
JSON_STRING;

        $toml = <<<'TOML_STRING'
title = "TOML Example"

[owner]
name = "Tom Preston-Werner"
dob = "1979-05-27T15:32:00.000Z"

[database]
enabled = true
ports = [ 8000, 8001, 8002 ]
data = [ [ "delta", "phi" ], [ 3.14 ] ]

[database.temp_targets]
cpu = 79.5
case = 72

[servers]
[servers.alpha]
ip = "10.0.0.1"
role = "frontend"

[servers.beta]
ip = "10.0.0.2"
role = "backend"

[[fruits]]
name = "apple"

[fruits.physical]
color = "red"
shape = "round"

[[fruits.varieties]]
name = "red delicious"

[[fruits.varieties]]
name = "granny smith"

[[fruits]]
name = "banana"

[[fruits.varieties]]
name = "plantain"
TOML_STRING;
        expect(toml_encode(json_decode($json, false)))->toEqual($toml)
            ->and(toml_encode(json_decode($json, true)))->toEqual($toml);
    });

it('can encode DateTimeInterface',
    /**
     * @throws TomlError
     */
    function () {
        $expected = <<<'EXPECTED'
DateTimeInterface = 1979-05-27T07:32:00.999Z
DateTimeImmutable = 1979-05-27T07:32:00.999Z
EXPECTED;

        $data = [
            DateTimeInterface::class => new DateTime('1979-05-27T00:32:00.999999-07:00'),
            DateTimeImmutable::class => new DateTimeImmutable('1979-05-27T00:32:00.999999-07:00'),
        ];

        expect(toml_encode($data))->toEqual($expected)
            ->and(toml_encode((object) $data))->toEqual($expected);
    });
