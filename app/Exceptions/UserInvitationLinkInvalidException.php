<?php

namespace App\Exceptions;

use Exception;

class UserInvitationLinkInvalidException extends Exception
{
    protected $message = 'The invitation link is invalid or has expired.';
}
