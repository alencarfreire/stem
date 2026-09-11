<?php

declare(strict_types=1);

namespace Stem\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stem\App;
use Stem\Integrations\Cors;
use Stem\Request;

final class RequestHelpersTest extends TestCase
{
    public function testQueryParamAndJsonBodyAndBearer(): void
    {
        $app = (new App())->route(function (Request $r): void {
            $r->post(function () use ($r): void {
                $r->json([
                    'page' => $r->queryParam('page', '1'),
                    'body' => $r->jsonBody(),
                    'token' => $r->bearerToken(),
                    'wants' => $r->wantsJson(),
                    'form' => $r->formParam('name'),
                ]);
            });
        });

        $response = $app->handle(Request::create(
            'POST',
            '/',
            [
                'Authorization' => 'Bearer secret',
                'Accept' => 'application/json',
            ],
            ['page' => '2'],
            '{"ok":true}',
            ['name' => 'Ada'],
        ));

        self::assertSame(
            '{"page":"2","body":{"ok":true},"token":"secret","wants":true,"form":"Ada"}',
            $response->body(),
        );
    }

    public function testInvalidJsonHaltsWith400(): void
    {
        $app = (new App())->route(function (Request $r): void {
            $r->post(function () use ($r): void {
                $r->json(['body' => $r->jsonBody()]);
            });
        });

        $response = $app->handle(Request::create('POST', '/', [], [], '{'));
        self::assertSame(400, $response->status());
        self::assertSame('Invalid JSON', $response->body());
    }

    public function testEmptyJsonBodyIsNull(): void
    {
        $app = (new App())->route(function (Request $r): void {
            $r->post(fn () => $r->json(['body' => $r->jsonBody()]));
        });

        $response = $app->handle(Request::create('POST', '/'));
        self::assertSame('{"body":null}', $response->body());
    }

    public function testFilesAreRequestScoped(): void
    {
        $app = (new App())->route(function (Request $r): void {
            $r->post(fn () => $r->json(['files' => array_keys($r->files())]));
        });

        $with = $app->handle(Request::create('POST', '/', [], [], '', [], [
            'avatar' => ['name' => 'a.png', 'error' => 0],
        ]));
        self::assertSame('{"files":["avatar"]}', $with->body());

        $without = $app->handle(Request::create('POST', '/'));
        self::assertSame('{"files":[]}', $without->body());
    }

    public function testDownloadAttachesFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'stem');
        self::assertNotFalse($path);
        file_put_contents($path, 'hello-file');

        try {
            $app = (new App())->route(function (Request $r) use ($path): void {
                $r->get(fn () => $r->download($path, 'note.txt'));
            });
            $response = $app->handle(Request::create('GET', '/'));
            self::assertSame(200, $response->status());
            self::assertSame('hello-file', $response->body());
            self::assertSame('attachment; filename="note.txt"', $response->headers()['Content-Disposition']);
        } finally {
            unlink($path);
        }
    }

    public function testCorsAllowSetsHeadersAndShortCircuitsOptions(): void
    {
        $app = (new App())->route(function (Request $r): void {
            $r->on('api', function () use ($r): void {
                Cors::allow($r, origins: ['https://app.example']);
                $r->get(fn () => $r->json(['ok' => true]));
            });
        });

        $preflight = $app->handle(Request::create('OPTIONS', '/api', ['Origin' => 'https://app.example']));
        self::assertSame(204, $preflight->status());
        self::assertSame('https://app.example', $preflight->headers()['Access-Control-Allow-Origin']);

        $get = $app->handle(Request::create('GET', '/api', ['Origin' => 'https://app.example']));
        self::assertSame(200, $get->status());
        self::assertSame('https://app.example', $get->headers()['Access-Control-Allow-Origin']);
    }

    public function testFromGlobalsHonorsMethodOverride(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/go';
        $_POST = ['_method' => 'DELETE'];
        $_GET = [];
        $_FILES = [];

        try {
            $request = Request::fromGlobals();
            self::assertSame('DELETE', $request->method());
        } finally {
            unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
            $_POST = [];
            $_FILES = [];
        }
    }
}
