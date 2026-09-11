<?php

declare(strict_types=1);

namespace Stem\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stem\Request;
use Stem\Router;

final class RequestPathTest extends TestCase
{
    public function testRootPathsNormalizeToSlash(): void
    {
        foreach (['', '/', '//'] as $path) {
            $request = Request::create('GET', $path);
            self::assertSame('/', $request->path());
            self::assertSame('', $request->remaining());
            self::assertSame(200, $request->response()->status());
        }
    }

    public function testTrailingSlashAndRepeatedSlashesCollapse(): void
    {
        $request = Request::create('GET', '/a//b/');
        self::assertSame('/a/b', $request->path());
        self::assertSame(['a', 'b'], Router::fromPath('/a//b/')->segments());
    }

    public function testQueryStringIsNotPartOfThePath(): void
    {
        $request = Request::create('GET', '/users?x=1', [], ['x' => '1']);
        self::assertSame('/users', $request->path());
        self::assertSame(['x' => '1'], $request->query());
    }

    public function testFragmentIsNotPartOfThePath(): void
    {
        $request = Request::create('GET', '/users#top');
        self::assertSame('/users', $request->path());
    }

    public function testPercentEncodedSpacesAreDecodedPerSegment(): void
    {
        $request = Request::create('GET', '/hello%20world');
        self::assertSame('/hello world', $request->path());
    }

    public function testEncodedSlashStaysInsideASegment(): void
    {
        $request = Request::create('GET', '/a%2Fb');
        self::assertSame('/a/b', $request->path());
        self::assertSame(['a/b'], Router::fromPath('/a%2Fb')->segments());
    }

    public function testMethodIsUppercased(): void
    {
        $request = Request::create('get', '/');
        self::assertSame('GET', $request->method());
    }

    public function testHeadersAreLowercased(): void
    {
        $request = Request::create('GET', '/', ['X-Request-Id' => 'abc']);
        self::assertSame('abc', $request->header('x-request-id'));
        self::assertSame('abc', $request->header('X-Request-Id'));
    }

    public function testFromGlobalsReadsMethodPathQueryAndHeaders(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/users/42?tag=php';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer tok';
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $_GET = ['tag' => 'php'];

        try {
            $request = Request::fromGlobals();
            self::assertSame('POST', $request->method());
            self::assertSame('/users/42', $request->path());
            self::assertSame(['tag' => 'php'], $request->query());
            self::assertSame('Bearer tok', $request->header('authorization'));
            self::assertSame('application/json', $request->header('content-type'));
        } finally {
            unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_SERVER['HTTP_AUTHORIZATION'], $_SERVER['CONTENT_TYPE']);
            $_GET = [];
        }
    }

    public function testFromGlobalsSkipsInputOnGet(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_GET = [];

        try {
            $request = Request::fromGlobals();
            self::assertSame('GET', $request->method());
            self::assertSame('/', $request->path());
            self::assertSame('', $request->rawBody());
        } finally {
            unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
        }
    }

    public function testConsumeIntRejectsLeadingZerosAndOverflow(): void
    {
        self::assertNull(Router::fromPath('/01')->consumeInt());
        self::assertNull(Router::fromPath('/-1')->consumeInt());
        self::assertNull(Router::fromPath('/1.5')->consumeInt());
        self::assertNull(Router::fromPath('/abc')->consumeInt());
        self::assertNull(Router::fromPath('/9223372036854775808')->consumeInt());
        self::assertSame(0, Router::fromPath('/0')->consumeInt());
        self::assertSame(42, Router::fromPath('/42')->consumeInt());
        self::assertSame(PHP_INT_MAX, Router::fromPath('/' . (string) PHP_INT_MAX)->consumeInt());
    }

    public function testUuidMatcherAcceptsCanonicalFormOnly(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        self::assertTrue(Router::isUuid($uuid));
        self::assertSame($uuid, Router::fromPath('/' . $uuid)->consumeUuid());
        self::assertNull(Router::fromPath('/550e8400e29b41d4a716446655440000')->consumeUuid());
        self::assertNull(Router::fromPath('/not-a-uuid')->consumeUuid());
        self::assertNull(Router::fromPath('/' . $uuid . '/extra')->consumeExactUuid());
        self::assertSame($uuid, Router::fromPath('/' . $uuid)->consumeExactUuid());
    }

    public function testPeekAndSnapshotDoNotAdvanceTheOriginalCursor(): void
    {
        $router = Router::fromPath('/users/1');
        self::assertSame('users', $router->peek());
        $snap = $router->snapshot();
        self::assertTrue($snap->consume('users'));
        self::assertSame('users', $router->peek());
        self::assertSame(0, $router->offset());
        self::assertSame(1, $snap->offset());
    }
}
