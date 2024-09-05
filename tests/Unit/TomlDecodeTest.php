<?php

use Devium\Toml\TomlError;

it('can decode TOML',
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
TOML_STRING;

        expect(toml_decode($toml))->toEqual(json_decode($json, false))
            ->and(toml_decode($toml, true))->toEqual(json_decode($json, true));
    });

it('can decode TOML datetime formats',
    /**
     * @throws TomlError
     */
    function () {
        $toml = <<<'TOML_STRING'
DateTimeInterface = 1979-05-27T07:32:00.999Z
DateTimeImmutable = 1979-05-27T07:32:00.999Z
TOML_STRING;

        $data = [
            DateTimeInterface::class => new DateTime('1979-05-27T07:32:00.999'),
            DateTimeImmutable::class => new DateTimeImmutable('1979-05-27T07:32:00.999'),
        ];

        expect(toml_decode($toml))->toEqual((object) $data)
            ->and(toml_decode($toml, true))->toEqual($data);
    });

it('can decode float',
    /**
     * @throws TomlError
     */
    function () {
        $toml = <<<'TOML_STRING'
float1 = 3.14
float2 = "3.14"
float3 = 1.0
float4 = "1.0"
TOML_STRING;

        $data = [
            'float1' => 3.14,
            'float2' => 3.14,
            'float3' => 1.0,
            'float4' => 1.0,
        ];

        expect(toml_decode($toml))->toEqual((object) $data)
            ->and(toml_decode($toml, true))->toEqual($data);
    });

it('can decode PI value as float',
    /**
     * @throws TomlError
     */
    function () {
        $toml = <<<'TOML_STRING'
pi = 3.141592653589793
-pi = -3.141592653589793
TOML_STRING;

        $parsed = toml_decode($toml, asFloat: true);
        expect($parsed->{'pi'})->toBeFloat()->toEqual(3.141592653589793)
            ->and($parsed->{'-pi'})->toBeFloat()->toEqual(-3.141592653589793);

        $parsed = toml_decode($toml, asArray: true, asFloat: true);
        expect($parsed['pi'])->toBeFloat()->toEqual(3.141592653589793)
            ->and($parsed['-pi'])->toBeFloat()->toEqual(-3.141592653589793);
    });
