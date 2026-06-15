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
*/

uses(Tests\TestCase::class)->in('Browser');
