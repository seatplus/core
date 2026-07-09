<?php

namespace Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The assembled app has no Testbench factory-name guessing, so package
        // model factories (auth/eveapi/web) can't be resolved by Laravel's
        // default guessing. Map each seatplus model namespace to its package
        // factory namespace, for browser tests that use factories.
        Factory::guessFactoryNamesUsing(fn (string $model) => match (true) {
            Str::startsWith($model, 'Seatplus\\Auth') => 'Seatplus\\Auth\\Database\\Factories\\'.class_basename($model).'Factory',
            Str::startsWith($model, 'Seatplus\\Eveapi') => 'Seatplus\\Eveapi\\Database\\Factories\\'.class_basename($model).'Factory',
            Str::startsWith($model, 'Seatplus\\Web') => 'Seatplus\\Web\\Database\\Factories\\'.class_basename($model).'Factory',
            default => 'Database\\Factories\\'.class_basename($model).'Factory',
        });
    }
}
