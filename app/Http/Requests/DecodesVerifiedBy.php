<?php

namespace App\Http\Requests;

/**
 * Decodes `verified_by` from a JSON string to a PHP array before validation.
 *
 * The mobile app may send `verified_by` as a JSON-encoded string (e.g.,
 * "[{\"user_id\":1}]") rather than as a native array. Without decoding,
 * the model's 'array' cast would double-encode it on save.
 */
trait DecodesVerifiedBy
{
    protected function prepareForVerifiedByDecoding(): void
    {
        if ($this->has('verified_by') && is_string($this->input('verified_by'))) {
            $decoded = json_decode($this->input('verified_by'), true);
            if (is_array($decoded)) {
                $this->merge(['verified_by' => $decoded]);
            }
        }
    }
}
