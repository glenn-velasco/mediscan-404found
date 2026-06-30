<?php

namespace App\Events;

use App\Models\User;

class EmailChanged
{
    public function __construct(public readonly User $user) {}
}
