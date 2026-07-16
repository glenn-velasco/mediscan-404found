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

];
