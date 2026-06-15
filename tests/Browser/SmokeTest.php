<?php

/*
 * KNOWN-FAILING (intentional, true positive) — do not "fix" by relaxing the assertion.
 *
 * This test currently fails: /login renders blank because the assembled app pairs
 * inertia-laravel v3 (server) with @inertiajs/vue3 ^1 (client, pinned in
 * packages/web/package.json). The v3 server emits the page payload in a separate
 *   <script data-page="app" type="application/json">…</script><div id="app"></div>
 * (CSP-friendly format), but the v1 client reads it from `el.dataset.page` on the
 * now-empty #app div — so it mounts nothing. No JS error is thrown, which is why
 * assertNoSmoke() passes while the page is empty.
 *
 * This is the cross-package regression the core browser suite exists to catch.
 * Fix = upgrade the web client to @inertiajs/vue3 ^2 (roadmap "PR3"), tracked as a
 * dedicated web PR. Verified locally: with the v2 client the login page renders and
 * this test passes (3 assertions).
 */
it('renders the login page', function () {
    $page = visit('/login');
    $page->assertNoSmoke();
    $page->assertSee('Sign in');
    $page->screenshot(true, 'login');
});
