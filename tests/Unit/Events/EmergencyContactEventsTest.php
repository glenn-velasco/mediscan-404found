<?php

use App\Events\EmergencyContactCreated;
use App\Events\EmergencyContactDeleted;
use App\Events\EmergencyContactUpdated;
use Illuminate\Broadcasting\PrivateChannel;

it('broadcasts EmergencyContactCreated on the patient private channel', function () {
    $event = new EmergencyContactCreated(userId: 42, emergencyContactId: 7, name: 'Jane');

    expect($event->broadcastOn())->toEqual([new PrivateChannel('App.Models.User.42')])
        ->and($event->broadcastAs())->toBe('EmergencyContactCreated')
        ->and($event->broadcastWith())->toBe(['emergency_contact_id' => 7, 'name' => 'Jane']);
});

it('broadcasts EmergencyContactUpdated on the patient private channel', function () {
    $event = new EmergencyContactUpdated(userId: 42, emergencyContactId: 7, name: 'Jane');

    expect($event->broadcastOn())->toEqual([new PrivateChannel('App.Models.User.42')])
        ->and($event->broadcastAs())->toBe('EmergencyContactUpdated')
        ->and($event->broadcastWith())->toBe(['emergency_contact_id' => 7, 'name' => 'Jane']);
});

it('broadcasts EmergencyContactDeleted on the patient private channel', function () {
    $event = new EmergencyContactDeleted(userId: 42, emergencyContactId: 7);

    expect($event->broadcastOn())->toEqual([new PrivateChannel('App.Models.User.42')])
        ->and($event->broadcastAs())->toBe('EmergencyContactDeleted')
        ->and($event->broadcastWith())->toBe(['emergency_contact_id' => 7]);
});
