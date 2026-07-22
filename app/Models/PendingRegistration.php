<?php

namespace App\Models;

use Database\Factories\PendingRegistrationFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Holds a registration's submitted data while it awaits confirmation from an
 * existing record's primary user (see MedicalInformationRegistrationMatch) -
 * no User or MedicalInformation row exists for this registrant until
 * MedicalInformationRegistrationMatchService::accept() materializes one.
 * Not Notifiable: the "you're confirmed" email is sent via an on-demand
 * Notification::route('mail', ...), same pattern as UserInvitation.
 *
 * @property int $id
 * @property string $email
 * @property string $password
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $last_name
 * @property string|null $suffix
 * @property Carbon $dob
 * @property string $gender
 * @property string|null $address
 * @property string|null $phone_number
 * @property string|null $phone_country_code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Guarded('id')]
#[Hidden(['password'])]
class PendingRegistration extends Model
{
    /** @use HasFactory<PendingRegistrationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'dob' => 'date',
        ];
    }
}
