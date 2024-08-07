<?php

namespace Devium\Toml;

use ArrayObject;

class TomlObject extends ArrayObject
{
    public function __construct(object|array $array = [])
    {
        parent::__construct($array, ArrayObject::ARRAY_AS_PROPS);
    }
}
