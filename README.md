# Stand With Ukraine 🇺🇦

[![Stand With Ukraine](https://raw.githubusercontent.com/vshymanskyy/StandWithUkraine/main/banner-direct-single.svg)](https://vshymanskyy.github.io/StandWithUkraine/)


# Devium/Toml

[![Build status](https://img.shields.io/github/actions/workflow/status/vanodevium/toml/ci.yaml?v1.0.5&style=flat-square&label=tests)](https://github.com/vanodevium/toml)
[![Latest Version](https://img.shields.io/packagist/v/devium/toml?v1.0.5&style=flat-square&label=stable)](https://packagist.org/packages/devium/toml)
[![Downloads](https://img.shields.io/packagist/dt/devium/toml?v1.0.5&style=flat-square)](https://packagist.org/packages/devium/toml)
[![License](https://img.shields.io/packagist/l/devium/toml?v1.0.5&style=flat-square)](https://packagist.org/packages/devium/toml)

A robust and efficient PHP library for encoding and decoding [TOML](https://github.com/toml-lang/toml)
compatible with [v1.0.0](https://toml.io/en/v1.0.0)

> This library tries to support the TOML specification as much as possible.

## Overview

This library provides a comprehensive solution for working with TOML in PHP applications

## Features

-   Encoding PHP arrays and objects to TOML format
-   Decoding TOML strings into PHP data structures
-   Preserves data types as specified in TOML
-   Handles complex nested structures
-   Supports TOML datetime formats
-   Error handling with informative messages

### About TOML datetime formats

This library tries to parse TOML datetime formats into next variants (according to the specification):

-   `Devium\Toml\TomlDateTime` (for the [offset date time](https://toml.io/en/v1.0.0#offset-date-time))
-   `Devium\Toml\TomlLocalDatetime` (for the [local date time](https://toml.io/en/v1.0.0#local-date-time))
-   `Devium\Toml\TomlLocalDate` (for the [local date](https://toml.io/en/v1.0.0#local-date))
-   `Devium\Toml\TomlLocalTime` (for the [local time](https://toml.io/en/v1.0.0#local-time))

Example:

```toml

offset-date-time-1 = 1979-05-27T07:32:00Z
offset-date-time-2 = 1979-05-27T00:32:00-07:00
offset-date-time-3 = 1979-05-27T00:32:00.999999-07:00
offset-date-time-4 = 1979-05-27 07:32:00Z

local-date-time-1 = 1979-05-27T07:32:00
local-date-time-2 = 1979-05-27T00:32:00.999999

local-date-1 = 1979-05-27

local-time-1 = 07:32:00
local-time-2 = 00:32:00.999999

```

If you use

```php
dump(toml_decode($toml, true));
```

TOML will be parsed into array

```php
array:9 [
  "offset-date-time-1" => Devium\Toml\TomlDateTime @296638320 {#29
    date: 1979-05-27 07:32:00.0 +00:00
  }
  "offset-date-time-2" => Devium\Toml\TomlDateTime @296638320 {#35
    date: 1979-05-27 00:32:00.0 -07:00
  }
  "offset-date-time-3" => Devium\Toml\TomlDateTime @296638320 {#41
    date: 1979-05-27 00:32:00.999999 -07:00
  }
  "offset-date-time-4" => Devium\Toml\TomlDateTime @296638320 {#47
    date: 1979-05-27 07:32:00.0 +00:00
  }
  "local-date-time-1" => Devium\Toml\TomlLocalDateTime {#55
    +year: 1979
    +month: 5
    +day: 27
    +hour: 7
    +minute: 32
    +second: 0
    +millisecond: 0
  }
  "local-date-time-2" => Devium\Toml\TomlLocalDateTime {#61
    +year: 1979
    +month: 5
    +day: 27
    +hour: 0
    +minute: 32
    +second: 0
    +millisecond: 999
  }
  "local-date-1" => Devium\Toml\TomlLocalDate {#59
    +year: 1979
    +month: 5
    +day: 27
  }
  "local-time-1" => Devium\Toml\TomlLocalTime {#70
    +millisecond: 0
    +hour: 7
    +minute: 32
    +second: 0
  }
  "local-time-2" => Devium\Toml\TomlLocalTime {#77
    +millisecond: 999
    +hour: 0
    +minute: 32
    +second: 0
  }
]
```

Each class implements `Stringable` interface.

`TomlLocal*` classes are marked with `TomlDateTimeInterface` for usability. Each class has public properties.

There is `TomlDateTime` class to support TOML offset date time format also.

Of course any `DateTimeInterface` or `TomlDateTimeInterface` are encoded into TOML datetime string.
So

```php
$data = [
    'DateTimeInterface' => new DateTimeImmutable('1979-05-27T07:32:00Z'),
    'TomlDateTimeInterface' => new TomlDateTime('1979-05-27T07:32:00Z'),
];
```

will be encoded into

```toml
DateTimeInterface = 1979-05-27T07:32:00.000Z
TomlDateTimeInterface = 1979-05-27T07:32:00.000Z
```

### About informative errors

If there is parsing error, `TomlError` has the approximate location of the problem in the message.
Something like:

```sh
Invalid TOML document: unexpected non-numeric value

5:  [owner]
6:  name = Tom Preston-Werner
             ^
7:  dob = 1979-05-31T07:32:00-08:00
```

Else it has message about whole input.

### About floating-point values

The decoder returns each floating-point value as a string by default.

You can force it to return a float type by setting the **$asFloat** argument:

```php
toml_decode($toml, asFloat: true);
// or
\Devium\Toml\Toml::decode($toml, asFloat: true);
```

### About NULL

**TOML does not support null values.**

If the array contains a null value, an exception will be thrown.

The only thing possible is a null value for the keys in the tables. Such keys are simply skipped during encoding.

## Installation

You can install this library via composer:

```shell
composer require devium/toml
```

## Usage

Decoding:

```php
$toml = <<<TOML

# This is a TOML document

title = "TOML Example"

[owner]
name = "Tom Preston-Werner"
dob = 1979-05-27T07:32:00-08:00

TOML;

dump(\Devium\Toml\Toml::decode($toml, asArray: true));
dump(\Devium\Toml\Toml::decode($toml, asArray: false));

// or use global helper
dump(toml_decode($toml, asArray: false));
dump(toml_decode($toml, asArray: true));
```

Encoding:

```php
$data = [
  "title" => "TOML Example",
  "owner" => [
    "name" => "Tom Preston-Werner",
    "dob" => "1979-05-27T15:32:00.000Z",
  ],
];

dump(\Devium\Toml\Toml::encode($data));
// or use global helper
dump(toml_encode($data));
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request

## License

**devium/toml** is open-sourced software licensed under the [MIT license](./LICENSE.md).

[Vano Devium](https://github.com/vanodevium/)

---

Made with ❤️ in Ukraine
