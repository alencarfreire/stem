<?php

declare(strict_types=1);

/**
 * Hello-world target for local measurement. Do not publish numbers you have not measured.
 *
 *   php -S 127.0.0.1:8080 examples/bench/hello.php
 *   wrk -t4 -c64 -d10s http://127.0.0.1:8080/
 */

use Stem\App;
use Stem\Request;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$app = new App();
$app->route(function (Request $r): void {
    $r->root(fn () => $r->json(['ok' => true]));
});

$app->run();
