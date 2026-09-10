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

test('requires default passwords with an eight-character minimum', function () {
    $tooShortPassword = Validator::make([
        'password' => 'Aa1!abc',
    ], [
        'password' => ['required', Password::default()],
    ]);
    $minimumLengthPassword = Validator::make([
        'password' => 'Aa1!abcd',
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

    expect($tooShortPassword->fails())->toBeTrue()
        ->and($minimumLengthPassword->passes())->toBeTrue()
        ->and($longPassword->fails())->toBeTrue()
        ->and($strongPassword->passes())->toBeTrue();
});

test('encrypts session data by default', function () {
    expect(config('session.encrypt'))->toBeTrue();
});
