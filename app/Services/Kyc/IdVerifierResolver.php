<?php

namespace App\Services\Kyc;

use App\Contracts\Kyc\IdVerifierContract;
use App\Enums\IdType;
use App\Exceptions\UnsupportedIdTypeException;
use App\Services\Kyc\IdVerifiers\PrcIdVerifier;
use UnhandledMatchError;

class IdVerifierResolver
{
    public function resolve(IdType $idType): IdVerifierContract
    {
        // No `default` arm here on purpose - PHPStan flags one as dead code
        // while IdType has a single case, so a future case that's added to
        // the enum but not to this match is instead caught below and turned
        // into the intended UnsupportedIdTypeException, rather than
        // silently becoming dead code the next time this file is touched.
        try {
            return match ($idType) {
                IdType::PhPrc => app(PrcIdVerifier::class),
            };
        } catch (UnhandledMatchError) {
            throw new UnsupportedIdTypeException($idType->value);
        }
    }
}
