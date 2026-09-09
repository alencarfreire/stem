<?php

declare(strict_types=1);

use Stem\App;
use Stem\Request;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$app = new App();

$app->route(function (Request $r): void {
    $r->root(fn () => $r->json(['message' => 'StemPHP API']));

    $r->on('users', function () use ($r): void {
        $r->get(fn () => $r->json([['id' => 1, 'name' => 'John']]));
        $r->post(fn () => $r->json(['id' => 2], 201));

        $r->onInt(function (int $id) use ($r): void {
            $r->get(fn () => $r->json(['id' => $id, 'name' => 'John']));
            $r->delete(fn () => $r->halt(204));
        });
    });
});

$app->run();
