<?php

declare(strict_types=1);

use Stem\App;
use Stem\Integrations\FrankenPhpWorker;
use Stem\Request;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$app = new App();
$app->route(function (Request $r): void {
    $r->root(fn () => $r->json(['worker' => true]));
    $r->on('users', function () use ($r): void {
        $r->get(fn () => $r->json([['id' => 1]]));
        $r->onInt(function (int $id) use ($r): void {
            $r->get(fn () => $r->json(['id' => $id]));
        });
    });
});

FrankenPhpWorker::run($app, maxRequests: (int) ($_SERVER['MAX_REQUESTS'] ?? 0));
