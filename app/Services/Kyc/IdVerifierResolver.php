<?php

namespace App\Services\Kyc;

use App\Contracts\Kyc\IdVerifierContract;
use App\Enums\IdType;
use App\Services\Kyc\IdVerifiers\PrcIdVerifier;

class IdVerifierResolver
{
    public function resolve(IdType $idType): IdVerifierContract
    {
        return match ($idType) {
            IdType::PhPrc => app(PrcIdVerifier::class),
        };
    }
}
