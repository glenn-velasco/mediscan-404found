<?php

use App\Events\MedicalInformationUpdated;
use Illuminate\Broadcasting\PrivateChannel;

it('broadcasts on every linked user private channel as MedicalInformationUpdated', function () {
    $event = new MedicalInformationUpdated(1, [42, 43]);

    expect($event->broadcastOn())->toEqual([
        new PrivateChannel('App.Models.User.42'),
        new PrivateChannel('App.Models.User.43'),
    ])
        ->and($event->broadcastAs())->toBe('MedicalInformationUpdated')
        ->and($event->broadcastWith())->toBe(['medical_information_id' => 1]);
});
