<?php

declare(strict_types=1);

namespace Stem\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Stem\App;
use Stem\Integrations\FrankenPhpWorker;
use Stem\Request;
use Stem\Response;
use Stem\Router;

final class LifecycleTest extends TestCase
{
    public function testTheSameAppDoesNotLeakStateAcrossRequests(): void
    {
        $app = (new App())->route(function (Request $r): void {
            $r->onParam(function (string $name) use ($r): void {
                $r->get(fn () => $r->json(['name' => $name]));
                $r->post(fn () => $r->json(['created' => $name], 201));
            });
        });

        $alice = $app->handle(Request::create('GET', '/alice', ['X-User' => 'alice']));
        self::assertSame('{"name":"alice"}', $alice->body());

        $bob = $app->handle(Request::create('GET', '/bob', ['X-User' => 'bob']));
        self::assertSame('{"name":"bob"}', $bob->body());
        self::assertSame('{"name":"alice"}', $alice->body());

        $post = $app->handle(Request::create('POST', '/bob'));
        self::assertSame(201, $post->status());
        self::assertSame('{"created":"bob"}', $post->body());
        self::assertSame('{"name":"bob"}', $bob->body());

        $miss = $app->handle(Request::create('GET', '/'));
        self::assertSame(404, $miss->status());
        self::assertSame('Not Found', $miss->body());

        $afterMiss = $app->handle(Request::create('GET', '/carol'));
        self::assertSame(200, $afterMiss->status());
        self::assertSame('{"name":"carol"}', $afterMiss->body());
        self::assertArrayNotHasKey('X-User', $afterMiss->headers());
        self::assertArrayNotHasKey('X-User', $bob->headers());
    }

    public function testCtxDoesNotLeakAcrossHandleCalls(): void
    {
        $app = (new App())->route(function (Request $r): void {
            $r->onParam(function (string $name) use ($r): void {
                if ($r->ctx('seen') !== null) {
                    $r->json(['leaked' => $r->ctx('seen')], 500);

                    return;
                }
                $r->ctx('seen', $name);
                $r->get(fn () => $r->json(['name' => $r->ctx('seen')]));
            });
        });

        self::assertSame('{"name":"ada"}', $app->handle(Request::create('GET', '/ada'))->body());
        $second = $app->handle(Request::create('GET', '/linus'));
        self::assertSame(200, $second->status());
        self::assertSame('{"name":"linus"}', $second->body());
    }

    public function testCoreClassesHaveNoStaticRequestState(): void
    {
        foreach ([App::class, Request::class, Response::class, Router::class] as $class) {
            $reflection = new \ReflectionClass($class);
            foreach ($reflection->getProperties() as $property) {
                self::assertFalse(
                    $property->isStatic(),
                    $class . '::$' . $property->getName() . ' must not be static',
                );
            }
        }
    }

    public function testRouteCannotBeRedefinedOnTheSameApp(): void
    {
        $app = (new App())->route(static function (Request $r): void {
            $r->root(fn () => $r->json(['ok' => true]));
        });

        $this->expectException(\LogicException::class);
        $app->route(static function (Request $r): void {
            $r->root(fn () => $r->json(['other' => true]));
        });
    }

    public function testHandleWithoutRouteFails(): void
    {
        $this->expectException(\LogicException::class);
        (new App())->handle(Request::create('GET', '/'));
    }

    public function testFrankenPhpWorkerRequiresTheExtension(): void
    {
        if (function_exists('frankenphp_handle_request')) {
            self::markTestSkipped('ext-frankenphp is loaded');
        }

        $this->expectException(\RuntimeException::class);
        FrankenPhpWorker::run(new App());
    }
}
