<?php

namespace App\Exceptions;

use Exception;

class ProfessionalApplicationUploadFailedException extends Exception
{
    public function __construct()
    {
        parent::__construct('We couldn\'t upload your documents. Please try again.');
    }
}
