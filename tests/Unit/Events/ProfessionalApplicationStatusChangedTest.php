<?php

use App\Events\ProfessionalApplicationStatusChanged;
use Illuminate\Broadcasting\PrivateChannel;

it('broadcasts on the applicant and admin dashboard channels', function () {
    $event = new ProfessionalApplicationStatusChanged(applicationId: 7, userId: 42, status: 'pending_review');

    expect($event->broadcastOn())->toEqual([
        new PrivateChannel('App.Models.User.42'),
        new PrivateChannel('admin-dashboard'),
    ])
        ->and($event->broadcastAs())->toBe('ProfessionalApplicationStatusChanged')
        ->and($event->broadcastWith())->toBe(['application_id' => 7, 'status' => 'pending_review']);
});
