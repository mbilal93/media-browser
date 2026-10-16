<?php
declare(strict_types=1);
// Runnable core PHP integration: no Composer or framework required.
require dirname(__DIR__) . '/adapters/core-php/bootstrap.php';
if (!$authorized) {
    header('Location: playground.php');
    exit;
}
require dirname(__DIR__) . '/demo/core-php.php';
