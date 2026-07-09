<?php

/*
 * Smoke test for the assembled app's login page — the first browser test in core's
 * cross-package regression suite. It boots the whole app (all package submodules,
 * built web assets) and asserts the real /login renders: no JS/console/page errors
 * (assertNoSmoke) and the expected content (assertSee), capturing a screenshot.
 *
 * This caught the inertia-laravel v3 (server) vs @inertiajs/vue3 v1 (client)
 * mismatch that blanked every page; resolved by the web v2 client upgrade.
 */
it('renders the login page', function () {
    $page = visit('/login');
    $page->assertNoSmoke();
    $page->assertSee('Sign in');
    $page->screenshot(true, 'login');
});
