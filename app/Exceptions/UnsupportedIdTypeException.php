<?php

namespace App\Exceptions;

use Exception;

class UnsupportedIdTypeException extends Exception
{
    public function __construct(string $idType)
    {
        parent::__construct("No verifier is registered for id type \"{$idType}\".");
    }
}
