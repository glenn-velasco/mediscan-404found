<?php

use App\Events\MedicalInformationRegistrationMatchStatusChanged;
use Illuminate\Broadcasting\PrivateChannel;

it('broadcasts on the requester private channel as MedicalInformationRegistrationMatchStatusChanged', function () {
    $event = new MedicalInformationRegistrationMatchStatusChanged(7, 42, 'approved');

    expect($event->broadcastOn())->toEqual([new PrivateChannel('App.Models.User.42')])
        ->and($event->broadcastAs())->toBe('MedicalInformationRegistrationMatchStatusChanged')
        ->and($event->broadcastWith())->toBe([
            'medical_information_registration_match_id' => 7,
            'status' => 'approved',
        ]);
});
