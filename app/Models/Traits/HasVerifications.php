<?php

namespace App\Models\Traits;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Adds verification tracking to a model via a `verified_by` JSON column.
 *
 * The column stores an array of verification objects:
 * [
 *   {"user_id": 1, "name": "Dr. Reyes", "verified_at": "2026-07-24T12:00:00Z"},
 *   {"user_id": 2, "name": "Dr. Santos", "verified_at": "2026-07-24T11:00:00Z"}
 * ]
 */
trait HasVerifications
{
    /**
     * Get the verified_by array or empty array.
     *
     * Reads through the model accessor which applies the 'array' cast,
     * so the value is always a decoded PHP array (or null).
     *
     * @return array<int, array{user_id: int, name: string, verified_at: string}>
     */
    public function getVerifiedByArray(): array
    {
        $value = $this->verified_by;

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * Check if a user has verified this record.
     */
    public function isVerifiedBy(int $userId): bool
    {
        return collect($this->getVerifiedByArray())
            ->contains('user_id', $userId);
    }

    /**
     * Get the verification entry for a specific user.
     */
    public function getVerificationFor(int $userId): ?object
    {
        $entry = collect($this->getVerifiedByArray())
            ->firstWhere('user_id', $userId);

        return $entry ? (object) $entry : null;
    }

    /**
     * Get the count of professionals who verified this record.
     */
    public function getVerifiedCount(): int
    {
        return count($this->getVerifiedByArray());
    }

    /**
     * Add or update a verification for a user.
     *
     * @return array<int, array{user_id: int, name: string, verified_at: string}>
     */
    public function addVerification(User $professional): array
    {
        $verifications = $this->getVerifiedByArray();

        // Remove existing entry for this user (if any)
        $verifications = array_values(
            array_filter($verifications, fn ($v) => $v['user_id'] !== $professional->id)
        );

        // Add new entry
        $verifications[] = [
            'user_id' => $professional->id,
            'name' => $professional->fullname,
            'verified_at' => Carbon::now()->toIso8601String(),
        ];

        return $verifications;
    }

    /**
     * Remove a verification for a user.
     *
     * @return array<int, array{user_id: int, name: string, verified_at: string}>
     */
    public function removeVerification(int $userId): array
    {
        return array_values(
            array_filter($this->getVerifiedByArray(), fn ($v) => $v['user_id'] !== $userId)
        );
    }

    /**
     * Toggle verification for a user.
     *
     * @return array<int, array{user_id: int, name: string, verified_at: string}>
     */
    public function toggleVerification(User $professional, bool $verified): array
    {
        if ($verified) {
            return $this->addVerification($professional);
        }

        return $this->removeVerification($professional->id);
    }
}
