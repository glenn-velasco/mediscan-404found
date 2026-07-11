<?php

use App\Models\User;

it('shows the correct status for an approved application', function () {
    $user = User::factory()->create();

    $user->professionalApplications()->create([
        'id_type' => 'ph_prc',
        'issuing_country' => 'PH',
        'profession' => 'Physician',
        'specialty' => 'Orthopedic',
        'id_photo_path' => 'demo/id.jpg',
        'selfie_path' => 'demo/selfie.jpg',
        'coe_path' => 'demo/coe.pdf',
        'status' => 'approved',
        'role_granted' => 'orthopedic',
    ]);

    $this->actingAs($user);

    visit(route('professional-application.show'))
        ->assertSee('Approved')
        ->assertSee("You're verified")
        ->assertSee('orthopedic')
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
        'coe_path' => 'demo/coe.pdf',
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
