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
     * Confident match: normalized name + exact dob. Returns null when there's
     * no match or more than one (ambiguous) - name+dob alone is not proof of
     * identity (twins, common names), so an ambiguous result must never be
     * treated as a match.
     *
     * `$excludeId` matters because callers query this while the caller's own
     * record already exists with the exact name+dob they're matching against
     * (e.g. a fresh registrant's own brand-new interim record) - without
     * excluding it, that record would count as a second "match" alongside
     * the real candidate, making every genuine match look ambiguous and
     * silently drop it.
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

        return $matches->count() === 1 ? $matches->first() : null;
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
