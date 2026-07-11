<?php

it('shows the welcome page without a create-profile registration cta', function () {
    visit(route('home'))
        ->assertSee('MediScan')
        ->assertDontSee('Create your profile')
        ->assertSee('Log in')
        ->assertNoJavascriptErrors();
});
