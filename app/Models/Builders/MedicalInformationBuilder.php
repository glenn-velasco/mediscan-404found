<?php

namespace App\Models\Builders;

use App\Models\MedicalInformation;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<MedicalInformation>
 */
class MedicalInformationBuilder extends Builder
{
    public function search(string $term): static
    {
        return $this->where(function ($q) use ($term) {
            $q->where('full_name', 'ilike', "%{$term}%")
                ->orWhere('email', 'ilike', "%{$term}%");
        });
    }
}
