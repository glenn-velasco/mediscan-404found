<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->applicant = User::factory()->create();
    $this->applicant->assignRole(Role::User->value);
});

it('only shows the face verification step until a capture succeeds', function () {
    $this->actingAs($this->applicant);

    visit(route('professional-application.create'))
        ->assertSee('Step 1 of 2')
        ->assertSee('Start selfie capture')
        ->assertDontSee('Step 2 of 2')
        // "Certificate of Employment" can't be asserted absent: the intro
        // paragraph visible on both steps legitimately mentions it.
        ->assertDontSee('Professional ID photo')
        ->screenshot(filename: 'professional-application-create-step-1')
        ->assertNoJavascriptErrors();
});
