<?php

namespace App\Models;

use App\Enums\Gender;
use App\Models\Builders\UserBuilder;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property int|null $medical_information_id
 * @property string|null $first_name
 * @property string|null $middle_name
 * @property string|null $last_name
 * @property string|null $suffix
 * @property Carbon|null $dob
 * @property Gender|null $gender
 * @property string|null $address
 * @property string|null $phone_number
 * @property string|null $phone_country_code
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $deactivated_at
 * @property string|null $profile_photo_path
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['first_name', 'middle_name', 'last_name', 'suffix', 'dob', 'gender', 'address', 'phone_number', 'phone_country_code', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
#[Appends(['avatar', 'fullname', 'age'])]
#[UseEloquentBuilder(UserBuilder::class)]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'gender' => Gender::class,
            'first_name' => 'encrypted',
            'middle_name' => 'encrypted',
            'last_name' => 'encrypted',
            'suffix' => 'encrypted',
            'address' => 'encrypted',
            'phone_number' => 'encrypted',
        ];
    }

    /**
     * DOB is a named HIPAA identifier, so it's encrypted at rest like the
     * other PII fields - but `encrypted` doesn't compose with `date`, so the
     * column stores an encrypted date string and this accessor parses it.
     * Mirrors MedicalInformation::dob().
     *
     * @return Attribute<Carbon|null, string|null>
     */
    protected function dob(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value !== null ? Carbon::parse(Crypt::decryptString($value)) : null,
            set: fn (Carbon|string|null $value) => $value !== null
                ? Crypt::encryptString($value instanceof Carbon ? $value->toDateString() : $value)
                : null,
        );
    }

    /**
     * @return HasMany<ProfessionalApplication, $this>
     */
    public function professionalApplications(): HasMany
    {
        return $this->hasMany(ProfessionalApplication::class);
    }

    /**
     * @return BelongsTo<MedicalInformation, $this>
     */
    public function medicalInformation(): BelongsTo
    {
        return $this->belongsTo(MedicalInformation::class);
    }

    public function isActive(): bool
    {
        return $this->deactivated_at === null;
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function avatar(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->profile_photo_path
                ? Storage::disk('public')->url($this->profile_photo_path)
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

    /**
     * @return Attribute<int|null, never>
     */
    protected function age(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->dob?->age,
        );
    }

    /**
     * @return array<int, string>
     */
    public function tokenAbilities(): array
    {
        return $this->getRoleNames()
            ->merge($this->getAllPermissions()->pluck('name'))
            ->unique()
            ->values()
            ->all();
    }
}
