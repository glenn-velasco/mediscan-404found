<?php

use App\Events\ProfessionalApplicationStatusChanged;
use App\Models\ProfessionalApplication;
use Illuminate\Broadcasting\PrivateChannel;

it('broadcasts on the applicant and admin dashboard channels', function () {
    $application = new ProfessionalApplication([
        'user_id' => 42,
        'status' => 'pending_review',
    ]);
    $application->id = 7;

    $event = new ProfessionalApplicationStatusChanged($application);

    expect($event->broadcastOn())->toEqual([
        new PrivateChannel('App.Models.User.42'),
        new PrivateChannel('admin-dashboard'),
    ])
        ->and($event->broadcastAs())->toBe('ProfessionalApplicationStatusChanged')
        ->and($event->broadcastWith())->toBe(['application_id' => 7, 'status' => 'pending_review']);
});
