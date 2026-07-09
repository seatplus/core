<?php

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Browser smoke tests run against the fully-assembled core app (web + all
| packages), so Vite/manifest, @routes/@translations, providers, assets and
| auth all work natively — unlike the web package's Testbench harness.
|
*/

uses(Tests\TestCase::class)->in('Browser');

/*
| The assembled app has none of the web package's Testbench factory-name
| guessing, so model factories from the seatplus packages can't be resolved by
| Laravel's default guessing. Map each seatplus model namespace to its package
| factory namespace, for any browser test that uses factories (e.g. authed tests).
*/
beforeEach(function () {
    Factory::guessFactoryNamesUsing(fn (string $model) => match (true) {
        Str::startsWith($model, 'Seatplus\\Auth') => 'Seatplus\\Auth\\Database\\Factories\\'.class_basename($model).'Factory',
        Str::startsWith($model, 'Seatplus\\Eveapi') => 'Seatplus\\Eveapi\\Database\\Factories\\'.class_basename($model).'Factory',
        Str::startsWith($model, 'Seatplus\\Web') => 'Seatplus\\Web\\Database\\Factories\\'.class_basename($model).'Factory',
        default => 'Database\\Factories\\'.class_basename($model).'Factory',
    });
})->in('Browser');
