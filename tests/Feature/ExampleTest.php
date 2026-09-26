<?php

use Inertia\Testing\AssertableInertia as Assert;

test('welcome page renders successfully with inertia component and props', function () {
    $response = $this->get(route('home'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->has('auth')
            ->has('locale'),
        );
});
