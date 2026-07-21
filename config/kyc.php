<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Face Match Hard Floor
    |--------------------------------------------------------------------------
    |
    | Minimum selfie-vs-ID-photo similarity score (0-1) required to clear the
    | automatic verification gate. Falling below this triggers an automatic
    | rejection. Clearing it does not auto-approve - every application that
    | passes this floor still requires a manual admin decision.
    |
    | Lowered from 0.4 to 0.2 for demonstration environments where lighting
    | and camera quality make strict face matching unreliable.
    |
    */

    'face_match_hard_floor' => (float) env('KYC_FACE_MATCH_HARD_FLOOR', 0.2),

    /*
    |--------------------------------------------------------------------------
    | Account Retrieval Face Match Floor
    |--------------------------------------------------------------------------
    |
    | Minimum selfie-vs-ID-photo similarity score for an account retrieval
    | request. Set higher than the KYC floor above because this flow has no
    | liveness burst backing the score up (single selfie, not a capture
    | flow) - clearing it still never auto-approves, an admin always reviews.
    |
    */

    'account_retrieval_face_match_floor' => (float) env('KYC_ACCOUNT_RETRIEVAL_FACE_MATCH_FLOOR', 0.4),

    /*
    |--------------------------------------------------------------------------
    | OCR / Face Match Drivers
    |--------------------------------------------------------------------------
    |
    | Which backend answers OcrClientContract / FaceMatchClientContract.
    | 'google' uses Google Cloud Vision (default). 'sidecar' uses the
    | self-hosted Tesseract/OpenCV machine-learning sidecar. The two are
    | toggled independently so, e.g., face matching can fall back to the
    | sidecar while OCR stays on Google Cloud Vision.
    |
    */

    'ocr_driver' => env('KYC_OCR_DRIVER', 'google'),

    'face_driver' => env('KYC_FACE_DRIVER', 'google'),

];
