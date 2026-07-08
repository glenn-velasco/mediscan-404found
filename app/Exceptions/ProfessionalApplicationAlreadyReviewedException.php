<?php

namespace App\Exceptions;

use Exception;

class ProfessionalApplicationAlreadyReviewedException extends Exception
{
    public function __construct()
    {
        parent::__construct('This application has already been reviewed.');
    }
}
