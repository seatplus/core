<?php

/*
 * Pest 4 browser smoke tests against the real, assembled core app.
 *
 * Phase 1: the unauthenticated login page — proves the harness renders the real
 * Vue/Inertia app end-to-end (Vite build → mount → screenshot) in core, where the
 * Testbench integration pain doesn't exist. Authenticated pages + the full
 * per-page suite follow once a browser-login mechanism is in place.
 */

it('renders the login page', function () {
    $page = visit('/login');

    $page->assertNoSmoke();
    $page->assertSee('Sign in');
    $page->screenshot(true, 'login');
});
