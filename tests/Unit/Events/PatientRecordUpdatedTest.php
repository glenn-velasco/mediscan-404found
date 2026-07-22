<?php

use App\Events\PatientRecordUpdated;
use Illuminate\Broadcasting\PrivateChannel;

it('broadcasts on the owning user private channel as PatientRecordUpdated with no PHI', function () {
    $event = new PatientRecordUpdated('allergy', 'ac9d9b1a-0000-4000-8000-000000000000', 42);

    expect($event->broadcastOn())->toEqual([new PrivateChannel('App.Models.User.42')])
        ->and($event->broadcastAs())->toBe('PatientRecordUpdated')
        ->and($event->broadcastWith())->toBe([
            'record_type' => 'allergy',
            'record_id' => 'ac9d9b1a-0000-4000-8000-000000000000',
        ]);
});
