# Stand With Ukraine 🇺🇦

[![Stand With Ukraine](https://raw.githubusercontent.com/vshymanskyy/StandWithUkraine/main/banner-direct-single.svg)](https://vshymanskyy.github.io/StandWithUkraine/)

---

[![Build status](https://img.shields.io/github/actions/workflow/status/vanodevium/toml/ci.yaml?v1.0.0)](https://github.com/vanodevium/toml)
[![Latest Version](https://img.shields.io/packagist/v/devium/toml?v1.0.0)](https://packagist.org/packages/devium/toml)
[![License](https://img.shields.io/packagist/l/devium/toml?v1.0.0)](https://packagist.org/packages/devium/toml)
[![Downloads](https://img.shields.io/packagist/dt/devium/toml?v1.0.0)](https://packagist.org/packages/devium/toml)

# Devium\Toml

A robust and efficient PHP library for encoding and decoding [TOML](https://github.com/toml-lang/toml)
compatible with [TOML v1.0.0](https://toml.io/en/v1.0.0)

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

This library tries to parse TOML datetime formats into next variants (according to the specification 1.0.0):

-   `Devium\Toml\TomlDateTime` (for [offset date time](https://toml.io/en/v1.0.0#offset-date-time))
-   `Devium\Toml\TomlLocalDatetime` (for [local date time](https://toml.io/en/v1.0.0#local-date-time))
-   `Devium\Toml\TomlLocalDate` (for [local date](https://toml.io/en/v1.0.0#local-date))
-   `Devium\Toml\TomlLocalTime` (for [local time](https://toml.io/en/v1.0.0#local-time))

Each class implements `Stringable` interface.
`TomlLocal*` classes implement `TomlDateTimeInterface` for usability. Each class has public properties.
There is `TomlDateTime` class to support TOML offset date time format also.

Of course there is ability to encode any `DateTimeInterface` value into TOML datetime string.

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

Else it shows problem about whole input.

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
