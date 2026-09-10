<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

test('enables Eloquent strict mode outside production', function () {
    expect(Model::preventsLazyLoading())->toBeTrue()
        ->and(Model::preventsSilentlyDiscardingAttributes())->toBeTrue()
        ->and(Model::preventsAccessingMissingAttributes())->toBeTrue()
        ->and(Model::isAutomaticallyEagerLoadingRelationships())->toBeFalse();
});

test('requires robust default passwords', function () {
    $shortPassword = Validator::make([
        'password' => 'Secure-Pass1!',
    ], [
        'password' => ['required', Password::default()],
    ]);
    $longPassword = Validator::make([
        'password' => str_repeat('A', 126).'a1!',
    ], [
        'password' => ['required', Password::default()],
    ]);
    $strongPassword = Validator::make([
        'password' => 'Secure-Default-Password-2026!',
    ], [
        'password' => ['required', Password::default()],
    ]);

    expect($shortPassword->fails())->toBeTrue()
        ->and($longPassword->fails())->toBeTrue()
        ->and($strongPassword->passes())->toBeTrue();
});

test('encrypts session data by default', function () {
    expect(config('session.encrypt'))->toBeTrue();
});
