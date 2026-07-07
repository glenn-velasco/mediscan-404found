<?php

namespace App\Exceptions;

use Exception;

class ProfessionalApplicationAlreadyPendingException extends Exception
{
    public function __construct()
    {
        parent::__construct('You already have a professional application being processed or under review.');
    }
}
