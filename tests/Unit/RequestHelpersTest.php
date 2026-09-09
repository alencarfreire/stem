<?php

declare(strict_types=1);

namespace Stem\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stem\App;
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
}
