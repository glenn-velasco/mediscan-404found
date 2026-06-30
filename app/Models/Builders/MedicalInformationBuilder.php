<?php

namespace App\Models\Builders;

use Illuminate\Database\Eloquent\Builder;

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
