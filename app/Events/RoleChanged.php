<?php

namespace App\Events;

use App\Models\User;

class RoleChanged
{
    public function __construct(public readonly User $user) {}
}
