<?php

namespace App\Contracts\Kyc;

use App\Exceptions\KycSidecarUnavailableException;

interface OcrClientContract
{
    /**
     * Run OCR against raw image bytes and return the extracted text.
     *
     * @throws KycSidecarUnavailableException when the sidecar cannot be reached after retries
     */
    public function detectText(string $imageContents): string;
}
