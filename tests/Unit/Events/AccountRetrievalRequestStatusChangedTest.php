<?php

use App\Events\AccountRetrievalRequestStatusChanged;
use Illuminate\Broadcasting\PrivateChannel;

it('broadcasts on admin-dashboard and the requester channel when a requester is present', function () {
    $event = new AccountRetrievalRequestStatusChanged(5, 42, 'approved');

    expect($event->broadcastOn())->toEqual([
        new PrivateChannel('admin-dashboard'),
        new PrivateChannel('App.Models.User.42'),
    ])
        ->and($event->broadcastAs())->toBe('AccountRetrievalRequestStatusChanged')
        ->and($event->broadcastWith())->toBe([
            'account_retrieval_request_id' => 5,
            'status' => 'approved',
        ]);
});

it('only broadcasts on admin-dashboard for a pre-registration request with no requester', function () {
    $event = new AccountRetrievalRequestStatusChanged(5, null, 'denied');

    expect($event->broadcastOn())->toEqual([new PrivateChannel('admin-dashboard')]);
});
