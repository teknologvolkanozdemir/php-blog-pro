<?php
// Front controller. Dev server: php -S localhost:8000 index.php
if (PHP_SAPI === 'cli-server') {
    $p = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
    if (preg_match('#^/(themes|plugins)/[A-Za-z0-9_-]+/[A-Za-z0-9_./-]+\.(css|js|png|jpe?g|svg|webp|gif)$#i', $p)
        && strpos($p, '..') === false && is_file(__DIR__ . $p)) {
        return false;
    }
}
require __DIR__ . '/core/bootstrap.php';
route();
