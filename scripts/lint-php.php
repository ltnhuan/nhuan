<?php

$roots = ['app', 'database', 'routes', 'tests', 'config', 'bootstrap'];
$failed = false;

foreach ($roots as $root) {
    if (! is_dir($root)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        passthru('php -l '.escapeshellarg($file->getPathname()), $code);
        $failed = $failed || $code !== 0;
    }
}

exit($failed ? 1 : 0);
