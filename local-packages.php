<?php

declare(strict_types=1);

/*
 * Toggle local cross-package development.
 *
 *   composer run local:on    (php local-packages.php on)
 *   composer run local:off   (php local-packages.php off)
 *
 * `on` writes composer.local.json with a `type: path` repository for each
 * packages/* that is actually checked out (so a missing package never triggers
 * composer's "path does not exist" error). merge-plugin then merges those repos
 * so composer resolves seatplus/* from the local checkouts. `off` removes the
 * file. Either way, run `composer update` afterwards to apply.
 *
 * composer.local.json is gitignored, so CI/prod resolve from Packagist.
 */

$root = __DIR__;
$file = "$root/composer.local.json";
$mode = $argv[1] ?? 'on';

if ($mode === 'off') {
    is_file($file) && unlink($file);
    echo "Local packages OFF — composer.local.json removed. Run `composer update` to resolve seatplus/* from Packagist.\n";
    exit(0);
}

$repositories = [];

foreach (glob("$root/packages/*", GLOB_ONLYDIR) ?: [] as $dir) {
    if (is_file("$dir/composer.json")) {
        $repositories[] = ['type' => 'path', 'url' => 'packages/'.basename($dir)];
    }
}

if ($repositories === []) {
    is_file($file) && unlink($file);
    echo "No packages/* checked out — nothing to link (staying on Packagist).\n";
    exit(0);
}

file_put_contents(
    $file,
    json_encode(['repositories' => $repositories], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
);

echo count($repositories)." local package repo(s) written to composer.local.json. Run `composer update` to use them.\n";
