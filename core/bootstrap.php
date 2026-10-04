<?php
declare(strict_types=1);
define('ROOT', dirname(__DIR__));
define('DATA_DIR', ROOT . '/data');
foreach (['lib', 'security', 'content', 'extend', 'xml', 'router'] as $f) {
    require __DIR__ . "/$f.php";
}
