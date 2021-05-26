<?php

namespace skrtdev\JSON2;

use Throwable;

class InexistentClassException extends Exception
{
    public function __construct(string $class, int $code = 0, Throwable $previous = null)
    {
        parent::__construct("Not found class $class", $code, $previous);
    }


}