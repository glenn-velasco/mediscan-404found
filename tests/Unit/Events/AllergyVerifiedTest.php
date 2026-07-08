<?php

use App\Events\AllergyVerified;
use Illuminate\Broadcasting\PrivateChannel;

it('broadcasts on the patient private channel as AllergyVerified', function () {
    $event = new AllergyVerified(42, 7);

    expect($event->broadcastOn())->toEqual([new PrivateChannel('App.Models.User.42')])
        ->and($event->broadcastAs())->toBe('AllergyVerified')
        ->and($event->broadcastWith())->toBe(['allergy_id' => 7]);
});
