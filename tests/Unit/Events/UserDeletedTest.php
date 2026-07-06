<?php

use App\Events\UserDeleted;
use Illuminate\Broadcasting\PrivateChannel;

it('broadcasts on the user private channel as UserDeleted', function () {
    $event = new UserDeleted(42);

    expect($event->broadcastOn())->toEqual([new PrivateChannel('App.Models.User.42')])
        ->and($event->broadcastAs())->toBe('UserDeleted')
        ->and($event->broadcastWith())->toBe(['user_id' => 42]);
});
