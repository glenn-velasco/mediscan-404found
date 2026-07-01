<?php

namespace App\Models;

use App\Enums\Gender;
use App\Models\Builders\MedicalInformationBuilder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $last_name
 * @property string|null $suffix
 * @property string $full_name
 * @property Carbon $date_of_birth
 * @property Gender $gender
 * @property string|null $phone_country_code
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $address
 * @property string|null $blood_type
 * @property string|null $religion
 * @property bool $no_blood_transfusion
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable('user_id', 'first_name', 'middle_name', 'last_name', 'suffix', 'date_of_birth', 'gender', 'phone_country_code', 'phone', 'email', 'address', 'blood_type', 'religion', 'no_blood_transfusion')]
#[Table('medical_information')]
#[UseEloquentBuilder(MedicalInformationBuilder::class)]
class MedicalInformation extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'gender' => Gender::class,
            'no_blood_transfusion' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Allergy, $this>
     */
    public function allergies(): HasMany
    {
        return $this->hasMany(Allergy::class);
    }

    /**
     * @return HasMany<MedicalRecord, $this>
     */
    public function medicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class);
    }

    /**
     * @return HasMany<EmergencyContact, $this>
     */
    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(EmergencyContact::class);
    }
}
