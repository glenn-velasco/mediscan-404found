<?php

namespace App\Http\Requests;

use App\Enums\IdType;
use App\Enums\LivenessFlashColor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitProfessionalApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id_type' => ['required', 'string', Rule::in(IdType::values())],
            'date_of_birth' => ['required', 'date', 'before:'.now()->subYears(18)->toDateString()],
            'id_photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'selfie_frames' => ['required', 'array', 'min:3', 'max:10'],
            'selfie_frames.*' => ['image', 'mimes:jpg,jpeg,png', 'max:3072'],
            'flash_frames' => ['required', 'array', 'min:3', 'max:10'],
            'flash_frames.*' => ['image', 'mimes:jpg,jpeg,png', 'max:3072'],
            'flash_colors' => [
                'required',
                'array',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (count($value) !== count($this->file('flash_frames', []))) {
                        $fail('The flash_colors field must have the same number of entries as flash_frames.');
                    }
                },
            ],
            'flash_colors.*' => ['string', Rule::in(LivenessFlashColor::values())],
        ];
    }
}
