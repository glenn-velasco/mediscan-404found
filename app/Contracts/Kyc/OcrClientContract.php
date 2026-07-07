<?php

namespace App\Contracts\Kyc;

use App\Exceptions\KycSidecarUnavailableException;

interface OcrClientContract
{
    /**
     * Run OCR against an image stored on the given disk and return the
     * extracted text.
     *
     * @throws KycSidecarUnavailableException when the sidecar cannot be reached after retries
     */
    public function detectText(string $disk, string $path): string;
}
