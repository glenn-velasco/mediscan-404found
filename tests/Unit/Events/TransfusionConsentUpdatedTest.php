<?php

use App\Events\TransfusionConsentUpdated;
use Illuminate\Broadcasting\PrivateChannel;

it('broadcasts on the patient private channel as TransfusionConsentUpdated', function () {
    $event = new TransfusionConsentUpdated(42, true, 2);

    expect($event->broadcastOn())->toEqual([new PrivateChannel('App.Models.User.42')])
        ->and($event->broadcastAs())->toBe('TransfusionConsentUpdated')
        ->and($event->broadcastWith())->toBe([
            'no_blood_transfusion' => true,
            'witness_count' => 2,
        ]);
});
