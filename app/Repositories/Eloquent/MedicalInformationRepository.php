<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\MedicalInformationRepositoryContract;
use App\Models\MedicalInformation;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends BaseRepository<MedicalInformation>
 */
class MedicalInformationRepository extends BaseRepository implements MedicalInformationRepositoryContract
{
    public function __construct(MedicalInformation $medicalInformation)
    {
        parent::__construct($medicalInformation);
    }

    /** @return MedicalInformation */
    public function findOrFail(int $id): Model
    {
        return $this->model->newQuery()
            ->with(['contacts', 'transfusionConsents'])
            ->findOrFail($id);
    }

    /**
     * Confident match: normalized name + exact dob. Returns null when
     * there's no match or the result is genuinely ambiguous (multiple
     * primary-owned records for different people with the same name+dob,
     * e.g. twins).
     *
     * When multiple records match but only one has a primary_user (the
     * "canonical" record that was first claimed), that record is returned
     * — duplicate/orphan records left by prior bugs don't prevent matching.
     *
     * `$excludeId` matters because callers query this while the caller's
     * own record already exists with the exact name+dob they're matching
     * against (e.g. a fresh registrant's own brand-new interim record) —
     * without excluding it, that record would count as a second "match"
     * alongside the real candidate, making every genuine match look
     * ambiguous and silently drop it.
     *
     * `first_name`/`middle_name`/`last_name`/`suffix`/`dob` are `encrypted`
     * casts, so the DB can't filter on them - every row is fetched and
     * compared after Eloquent decrypts it. Fine at this table's per-user
     * scale; would need a blind-index (HMAC) column instead if this table
     * ever grows large enough for a full scan per registration to matter.
     *
     * @param  array{first_name: string, middle_name: ?string, last_name: string, suffix: ?string}  $nameFields
     */
    public function findMatchingByName(array $nameFields, string $dob, ?int $excludeId = null): ?MedicalInformation
    {
        $normalized = $this->normalizeName($nameFields);

        $matches = $this->model->newQuery()
            ->when($excludeId !== null, fn ($query) => $query->whereKeyNot($excludeId))
            ->get()
            ->filter(function (MedicalInformation $record) use ($normalized, $dob) {
                $recordNormalized = $this->normalizeName([
                    'first_name' => $record->first_name,
                    'middle_name' => $record->middle_name,
                    'last_name' => $record->last_name,
                    'suffix' => $record->suffix,
                ]);

                return $recordNormalized === $normalized && $record->dob->toDateString() === $dob;
            });

        if ($matches->count() === 0) {
            return null;
        }

        if ($matches->count() === 1) {
            return $matches->first();
        }

        // Multiple matches: return the canonical (primary-owned) record.
        // Duplicate/orphan records without a primary_user are artifacts of
        // the registration bug we're fixing — they don't represent different
        // people, so they shouldn't cause ambiguity.
        $primaryOwned = $matches->filter(fn (MedicalInformation $r) => $r->primary_user_id !== null);

        if ($primaryOwned->count() === 1) {
            return $primaryOwned->first();
        }

        // 0 or 2+ primary-owned records genuinely ambiguous — different
        // people with the same name+dob (twins, etc.). Never match.
        return null;
    }

    /**
     * @param  array{first_name: ?string, middle_name: ?string, last_name: ?string, suffix: ?string}  $nameFields
     */
    private function normalizeName(array $nameFields): string
    {
        return mb_strtolower(trim(preg_replace(
            '/\s+/',
            ' ',
            implode(' ', array_filter([
                $nameFields['first_name'] ?? null,
                $nameFields['middle_name'] ?? null,
                $nameFields['last_name'] ?? null,
                $nameFields['suffix'] ?? null,
            ]))
        )));
    }
}
