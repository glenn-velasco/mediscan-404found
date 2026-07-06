<?php

use App\Events\UserDeactivated;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;

it('broadcasts on the user private channel as UserDeactivated', function () {
    $user = User::factory()->make(['id' => 42]);

    $event = new UserDeactivated($user);

    expect($event->broadcastOn())->toEqual([new PrivateChannel('App.Models.User.42')])
        ->and($event->broadcastAs())->toBe('UserDeactivated')
        ->and($event->broadcastWith())->toBe(['user_id' => 42]);
});
