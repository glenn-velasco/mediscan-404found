<?php

namespace App\Exceptions;

use Exception;

class MedicalInformationAvatarUploadFailedException extends Exception
{
    public function __construct()
    {
        parent::__construct("We couldn't upload your photo. Please try again.");
    }
}
