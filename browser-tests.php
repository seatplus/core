<?php

declare(strict_types=1);

/*
 * Sync the web package's browser tests into core, so they run against the fully
 * assembled core app (real routes, middleware, built assets).
 *
 *   composer run browser          (sync + run headless)
 *   composer run browser:debug    (sync + run headed / debug output)
 *
 * Source is vendor/seatplus/web/tests/Browser — a Packagist install in CI, or the
 * symlinked local checkout (the workspace's `web` clone, or packages/web) when
 * `composer run local:on` is active.
 * Destination tests/Browser/web is mirrored (wiped + recopied) each run so it never
 * drifts from the package. tests/Browser/Screenshots is left untouched.
 */

$root = __DIR__;
$src = "$root/vendor/seatplus/web/tests/Browser";
$dst = "$root/tests/Browser/web";

if (! is_dir($src)) {
    fwrite(STDERR, "No web browser tests at {$src} — is seatplus/web installed?\n");
    exit(1);
}

// Mirror: remove the previous copy, then recreate.
if (is_dir($dst)) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dst, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($it as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($dst);
}

mkdir($dst, 0755, true);

$count = 0;

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST,
);

foreach ($files as $file) {
    $target = $dst.DIRECTORY_SEPARATOR.$files->getSubPathName();

    if ($file->isDir()) {
        is_dir($target) || mkdir($target, 0755, true);

        continue;
    }

    copy($file->getPathname(), $target);
    $count++;
}

echo "Synced {$count} browser test file(s) → tests/Browser/web.\n";
