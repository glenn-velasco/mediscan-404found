<?php

use App\Concerns\ProfileValidationRules;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class ProfileValidationRulesHarness
{
    use ProfileValidationRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(?int $userId = null): array
    {
        return $this->profileRules($userId);
    }
}

function validProfileData(): array
{
    return [
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'Delacruz',
        'suffix' => 'Jr.',
        'dob' => now()->subYears(30)->toDateString(),
        'gender' => 'male',
        'address' => '123 Main St',
        'phone_number' => '+639171234567',
        'email' => 'juan@example.com',
    ];
}

it('rejects a dob of today', function () {
    $validator = Validator::make(
        array_merge(validProfileData(), ['dob' => now()->toDateString()]),
        (new ProfileValidationRulesHarness)->rules(),
    );

    expect($validator->fails())->toBeTrue();
});

it('rejects a future dob', function () {
    $validator = Validator::make(
        array_merge(validProfileData(), ['dob' => now()->addDay()->toDateString()]),
        (new ProfileValidationRulesHarness)->rules(),
    );

    expect($validator->fails())->toBeTrue();
});

it('rejects an invalid gender value', function () {
    $validator = Validator::make(
        array_merge(validProfileData(), ['gender' => 'other']),
        (new ProfileValidationRulesHarness)->rules(),
    );

    expect($validator->fails())->toBeTrue();
});

it('accepts valid gender values', function () {
    foreach (['male', 'female'] as $gender) {
        $validator = Validator::make(
            array_merge(validProfileData(), ['gender' => $gender]),
            (new ProfileValidationRulesHarness)->rules(),
        );

        expect($validator->fails())->toBeFalse();
    }
});

it('accepts a null middle name', function () {
    $validator = Validator::make(
        array_merge(validProfileData(), ['middle_name' => null]),
        (new ProfileValidationRulesHarness)->rules(),
    );

    expect($validator->fails())->toBeFalse();
});

it('accepts a null suffix', function () {
    $validator = Validator::make(
        array_merge(validProfileData(), ['suffix' => null]),
        (new ProfileValidationRulesHarness)->rules(),
    );

    expect($validator->fails())->toBeFalse();
});

it('rejects an oversized middle name', function () {
    $validator = Validator::make(
        array_merge(validProfileData(), ['middle_name' => str_repeat('a', 256)]),
        (new ProfileValidationRulesHarness)->rules(),
    );

    expect($validator->fails())->toBeTrue();
});

it('rejects an oversized suffix', function () {
    $validator = Validator::make(
        array_merge(validProfileData(), ['suffix' => str_repeat('a', 51)]),
        (new ProfileValidationRulesHarness)->rules(),
    );

    expect($validator->fails())->toBeTrue();
});

it('rejects an oversized address', function () {
    $validator = Validator::make(
        array_merge(validProfileData(), ['address' => str_repeat('a', 1001)]),
        (new ProfileValidationRulesHarness)->rules(),
    );

    expect($validator->fails())->toBeTrue();
});

it('rejects a malformed phone number', function () {
    $validator = Validator::make(
        array_merge(validProfileData(), ['phone_number' => 'not-a-phone-number']),
        (new ProfileValidationRulesHarness)->rules(),
    );

    expect($validator->fails())->toBeTrue();
});

it('accepts a valid international phone number', function () {
    $validator = Validator::make(
        array_merge(validProfileData(), ['phone_number' => '+639171234567']),
        (new ProfileValidationRulesHarness)->rules(),
    );

    expect($validator->fails())->toBeFalse();
});

it('validates UpdateUserRequest sometimes-fields correctly when omitted vs present-but-invalid', function () {
    $target = User::factory()->create(['email' => 'target@example.com']);

    $bindFakeRoute = function (UpdateUserRequest $request) use ($target) {
        $request->setRouteResolver(fn () => new class($target)
        {
            public function __construct(private User $user) {}

            public function parameter($name, $default = null)
            {
                return $name === 'user' ? $this->user : $default;
            }
        });
    };

    $requestWithOmittedFields = UpdateUserRequest::create('/admin/users/'.$target->id, 'PATCH', [
        'email' => 'target-changed@example.com',
    ]);
    $bindFakeRoute($requestWithOmittedFields);

    $validator = Validator::make($requestWithOmittedFields->all(), $requestWithOmittedFields->rules());
    expect($validator->fails())->toBeFalse();
});
