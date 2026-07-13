<?php

use Illuminate\Support\Facades\Schema;

it('has the new profile-field columns on users and no more name column', function () {
    expect(Schema::hasColumns('users', [
        'first_name', 'middle_name', 'last_name', 'suffix',
        'dob', 'gender', 'address', 'phone_number',
    ]))->toBeTrue()
        ->and(Schema::hasColumn('users', 'name'))->toBeFalse();
});

it('has an index on users.deactivated_at', function () {
    expect(Schema::hasIndex('users', ['deactivated_at']))->toBeTrue();
});

it('has the expected indexes on user_invitations', function () {
    expect(Schema::hasIndex('user_invitations', ['email']))->toBeTrue()
        ->and(Schema::hasIndex('user_invitations', ['invited_by']))->toBeTrue()
        ->and(Schema::hasIndex('user_invitations', ['role_id']))->toBeTrue()
        ->and(Schema::hasIndex('user_invitations', ['accepted_at', 'expires_at']))->toBeTrue();
});

it('has the expected indexes on professional_applications', function () {
    expect(Schema::hasIndex('professional_applications', ['user_id', 'status']))->toBeTrue()
        ->and(Schema::hasIndex('professional_applications', ['status']))->toBeTrue()
        ->and(Schema::hasIndex('professional_applications', ['deleted_at']))->toBeTrue()
        ->and(Schema::hasIndex('professional_applications', ['reviewed_by']))->toBeTrue();
});

it('has an index on pending_sync_envelopes.sender_id', function () {
    expect(Schema::hasIndex('pending_sync_envelopes', ['sender_id']))->toBeTrue();
});
