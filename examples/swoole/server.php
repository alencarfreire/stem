<?php

declare(strict_types=1);

/**
 * Optional Swoole HTTP server. StemPHP has no Swoole dependency.
 *
 *   pecl install swoole
 *   php examples/swoole/server.php
 */

use Stem\App;
use Stem\Request;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if (!class_exists(Swoole\Http\Server::class)) {
    fwrite(STDERR, "ext-swoole is required to run this example.\n");
    exit(1);
}

$app = new App();
$app->route(function (Request $r): void {
    $r->root(fn () => $r->json(['runtime' => 'swoole']));
    $r->on('users', function () use ($r): void {
        $r->get(fn () => $r->json([['id' => 1]]));
        $r->onInt(function (int $id) use ($r): void {
            $r->get(fn () => $r->json(['id' => $id]));
        });
    });
});

$server = new Swoole\Http\Server('0.0.0.0', 8080);

$server->on('request', static function (Swoole\Http\Request $swooleRequest, Swoole\Http\Response $swooleResponse) use ($app): void {
    $headers = [];
    foreach ($swooleRequest->header ?? [] as $name => $value) {
        $headers[(string) $name] = (string) $value;
    }

    $query = [];
    foreach ($swooleRequest->get ?? [] as $name => $value) {
        $query[(string) $name] = $value;
    }

    $response = $app->handle(Request::create(
        (string) ($swooleRequest->server['request_method'] ?? 'GET'),
        (string) ($swooleRequest->server['request_uri'] ?? '/'),
        $headers,
        $query,
        (string) ($swooleRequest->rawContent() ?: ''),
    ));

    $swooleResponse->status($response->status());
    foreach ($response->headers() as $name => $value) {
        $swooleResponse->header($name, $value);
    }
    $swooleResponse->end($response->body());
});

$server->start();
