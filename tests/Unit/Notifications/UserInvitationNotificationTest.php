<?php

use App\Enums\Role;
use App\Notifications\UserInvitationNotification;

it('renders mail for each role backing value without throwing', function (Role $role) {
    $notification = new UserInvitationNotification(
        'https://example.test/invitations/accept?token=abc',
        now()->addDays(3),
        $role->value
    );

    $mail = $notification->toMail(new stdClass);

    expect($mail->actionText)->toBe('Accept Invitation as '.$role->label());
})->with(Role::cases());

it('throws when given a role label instead of a backing value', function () {
    $notification = new UserInvitationNotification(
        'https://example.test/invitations/accept?token=abc',
        now()->addDays(3),
        Role::Admin->label()
    );

    $notification->toMail(new stdClass);
})->throws(ValueError::class);
