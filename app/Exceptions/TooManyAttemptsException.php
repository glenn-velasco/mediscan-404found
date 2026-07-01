<?php

namespace App\Exceptions;

use Exception;

class TooManyAttemptsException extends Exception
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct("Too many attempts. Try again in {$retryAfter} seconds.");
    }
}
