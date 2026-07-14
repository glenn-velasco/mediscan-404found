<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('admin-dashboard', function (User $user) {
    return $user->hasRole(Role::Admin->value);
});

Broadcast::channel('family', fn (User $user) => [
    'id' => $user->id,
    'name' => $user->fullname,
    'email' => $user->email,
]);
