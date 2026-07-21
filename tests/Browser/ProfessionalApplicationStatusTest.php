<?php

use App\Models\User;

it('shows the correct status for an approved application', function () {
    $user = User::factory()->create();

    $user->professionalApplications()->create([
        'id_type' => 'ph_prc',
        'issuing_country' => 'PH',
        'profession' => 'Physician',
        'id_photo_path' => 'demo/id.jpg',
        'selfie_path' => 'demo/selfie.jpg',
        'status' => 'approved',
        'role_granted' => 'physician',
    ]);

    $this->actingAs($user);

    visit(route('professional-application.show'))
        ->assertSee('Approved')
        ->assertSee("You're verified")
        ->assertSee('physician')
        ->assertNoJavascriptErrors();
});

it('shows the correct status and rejection reason for a denied application', function () {
    $user = User::factory()->create();

    $user->professionalApplications()->create([
        'id_type' => 'ph_prc',
        'issuing_country' => 'PH',
        'profession' => 'Physician',
        'id_photo_path' => 'demo/id.jpg',
        'selfie_path' => 'demo/selfie.jpg',
        'status' => 'denied',
        'rejection_reason' => 'Blurry ID photo.',
        'deleted_at' => now(),
    ]);

    $this->actingAs($user);

    visit(route('professional-application.show'))
        ->assertSee('Denied')
        ->assertSee('Application rejected')
        ->assertSee('Blurry ID photo.')
        ->assertSee('Resubmit application')
        ->assertNoJavascriptErrors();
});
