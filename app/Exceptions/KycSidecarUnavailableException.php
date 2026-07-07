<?php

namespace App\Exceptions;

use Exception;

class KycSidecarUnavailableException extends Exception
{
    public function __construct(string $message = 'The KYC sidecar (OCR/face-match/liveness) is currently unavailable.', ?\Throwable $previous = null)
    {
        parent::__construct($message, previous: $previous);
    }
}
