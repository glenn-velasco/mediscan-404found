<?php

namespace Database\Factories;

use App\Enums\WorkflowStatus;
use App\Models\AccountRetrievalRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AccountRetrievalRequest> */
class AccountRetrievalRequestFactory extends Factory
{
    protected $model = AccountRetrievalRequest::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'requester_user_id' => null,
            'old_email' => $this->faker->safeEmail(),
            'first_name' => $this->faker->firstName(),
            'middle_name' => null,
            'last_name' => $this->faker->lastName(),
            'dob' => $this->faker->date(),
            'id_photo_path' => 'account-retrieval-requests/1/id-photo.jpg',
            'selfie_path' => 'account-retrieval-requests/1/selfie.jpg',
            'status' => WorkflowStatus::Pending,
        ];
    }

    public function fromExistingAccount(): static
    {
        return $this->state(fn () => ['requester_user_id' => User::factory()]);
    }
}
