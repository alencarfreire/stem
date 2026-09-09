<?php

declare(strict_types=1);

namespace Stem\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stem\Response;

final class ResponseTest extends TestCase
{
    public function testWriteJsonSetsStatusContentTypeAndBody(): void
    {
        $response = (new Response())->writeJson(['ok' => true, 'path' => '/a'], 201);

        self::assertSame(201, $response->status());
        self::assertSame(Response::CONTENT_TYPE_JSON, $response->headers()['Content-Type']);
        self::assertSame('{"ok":true,"path":"/a"}', $response->body());
    }

    public function testWriteHtmlSetsContentType(): void
    {
        $response = (new Response())->writeHtml('<p>hi</p>', 200);

        self::assertSame(200, $response->status());
        self::assertSame(Response::CONTENT_TYPE_HTML, $response->headers()['Content-Type']);
        self::assertSame('<p>hi</p>', $response->body());
    }

    public function testInvalidStatusIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Response(99);
    }

    public function testHeaderInjectionIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Response())->withHeader("X-Foo\r\nX-Evil", '1');
    }

    public function testHeaderValueInjectionIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Response())->withHeader('X-Foo', "1\r\nX-Evil: 1");
    }

    public function testSendIsIdempotentAndWritesTheBody(): void
    {
        $response = (new Response())->withBody('hello');
        ob_start();
        $response->send();
        $response->send();
        $output = ob_get_clean();

        self::assertSame('hello', $output);
        self::assertTrue($response->sent());
    }
}
