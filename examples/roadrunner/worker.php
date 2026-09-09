<?php

declare(strict_types=1);

/**
 * Optional RoadRunner worker. StemPHP has no RoadRunner dependency.
 *
 *   composer require spiral/roadrunner-http nyholm/psr7
 *   ./vendor/bin/rr serve -c examples/roadrunner/.rr.yaml
 */

use Nyholm\Psr7\Response as PsrResponse;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Worker;
use Stem\App;
use Stem\Request;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if (!class_exists(PSR7Worker::class)) {
    fwrite(STDERR, "Install spiral/roadrunner-http and nyholm/psr7 to run this example.\n");
    exit(1);
}

$app = new App();
$app->route(function (Request $r): void {
    $r->root(fn () => $r->json(['runtime' => 'roadrunner']));
    $r->on('users', function () use ($r): void {
        $r->get(fn () => $r->json([['id' => 1]]));
        $r->onInt(function (int $id) use ($r): void {
            $r->get(fn () => $r->json(['id' => $id]));
        });
    });
});

$psr7 = new PSR7Worker(Worker::create(), new Nyholm\Psr7\Factory\Psr17Factory(), new Nyholm\Psr7\Factory\Psr17Factory(), new Nyholm\Psr7\Factory\Psr17Factory());

while ($psrRequest = $psr7->waitRequest()) {
    try {
        $headers = [];
        foreach ($psrRequest->getHeaders() as $name => $values) {
            $headers[$name] = $values[0] ?? '';
        }

        $response = $app->handle(Request::create(
            $psrRequest->getMethod(),
            $psrRequest->getUri()->getPath(),
            $headers,
            $psrRequest->getQueryParams(),
            (string) $psrRequest->getBody(),
        ));

        $psr7->respond(new PsrResponse(
            $response->status(),
            $response->headers(),
            $response->body(),
        ));
    } catch (\Throwable $e) {
        $psr7->respond(new PsrResponse(500, ['Content-Type' => 'text/plain'], $e->getMessage()));
    }
}
