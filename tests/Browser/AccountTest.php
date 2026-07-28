<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

it('sends a verification email after changing account email', function () {
    Notification::fake();

    $user = User::factory()->create();
    $this->actingAs($user);

    visit(route('account.edit'))
        ->type('email', 'changed@example.com')
        ->press('Save')
        ->assertSee('Change email address?')
        ->press('Continue')
        ->assertNoJavascriptErrors();

    $this->artisan('queue:work --once');

    Notification::assertSentTo($user->fresh(), VerifyEmail::class);
});
