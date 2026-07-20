<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Browser smoke tests run against the fully-assembled core app (web + all
| packages), so Vite/manifest, @routes/@translations, providers, assets and
| auth all work natively — unlike the web package's Testbench harness.
|
| Seatplus package factory-name guessing is registered in Tests\TestCase::setUp().
|
*/

uses(Tests\TestCase::class)->in('Browser');

/*
|--------------------------------------------------------------------------
| Browser suite helpers
|--------------------------------------------------------------------------
|
| The suite's provisioning + seeding helpers (actingAsCharacter, snap, the
| make/seed factories, …) live in tests/Browser/web/helpers.php, authored and
| shipped alongside the tests in the seatplus/web package and synced into core
| with them. Each browser test file `require_once`s that shared file directly,
| so nothing needs to be declared here — Pest only auto-loads this ROOT Pest.php
| (a Pest.php synced into tests/Browser/web is never loaded), which is why the
| suite binding above stays here while the helpers travel with the tests.
|
*/
