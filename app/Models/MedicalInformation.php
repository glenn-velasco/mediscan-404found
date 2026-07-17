<?php

namespace App\Models;

use App\Enums\BloodType;
use App\Enums\Gender;
use App\Enums\Religion;
use App\Models\Builders\MedicalInformationBuilder;
use Database\Factories\MedicalInformationFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int|null $primary_user_id
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $last_name
 * @property string|null $suffix
 * @property Carbon $dob
 * @property Gender $gender
 * @property BloodType|null $blood_type
 * @property Religion|null $religion
 * @property string|null $national_id
 * @property array{province: ?string, street: ?string, unit: ?string, country: ?string, postal_code: ?string, city: ?string}|null $address
 * @property bool $no_blood_transfusion
 * @property string|null $avatar_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Guarded('id')]
#[UseEloquentBuilder(MedicalInformationBuilder::class)]
class MedicalInformation extends Model
{
    /** @use HasFactory<MedicalInformationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            // Not cast to the NameSuffix enum: this column mirrors
            // User::suffix, which is never validated against that enum
            // either (ProfileValidationRules::suffixRules() just allows any
            // string) - registration can forward arbitrary free text like
            // "Jr." here, so an enum cast would throw on real data.
            'dob' => 'date',
            'gender' => Gender::class,
            'blood_type' => BloodType::class,
            'religion' => Religion::class,
            'address' => 'array',
            'no_blood_transfusion' => 'boolean',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function primaryUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'primary_user_id');
    }

    /**
     * @return HasMany<MedicalInformationRegistrationMatch, $this>
     */
    public function registrationMatches(): HasMany
    {
        return $this->hasMany(MedicalInformationRegistrationMatch::class, 'candidate_medical_information_id');
    }

    /**
     * @return HasMany<MedicalInformationContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(MedicalInformationContact::class);
    }

    /**
     * @return HasMany<MedicalInformationTransfusionConsent, $this>
     */
    public function transfusionConsents(): HasMany
    {
        return $this->hasMany(MedicalInformationTransfusionConsent::class);
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function avatar(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->avatar_path
                ? Storage::disk('public')->url($this->avatar_path)
                : null,
        );
    }

    /**
     * @return Attribute<string, never>
     */
    protected function fullname(): Attribute
    {
        return Attribute::make(
            get: fn () => collect([$this->first_name, $this->middle_name, $this->last_name, $this->suffix])
                ->filter()
                ->implode(' '),
        );
    }
}
