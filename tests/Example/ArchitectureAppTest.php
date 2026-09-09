<?php

declare(strict_types=1);

namespace Stem\Tests\Example;

use PHPUnit\Framework\TestCase;
use Stem\Request;
use StemExample\AppFactory;
use StemExample\Database;

final class ArchitectureAppTest extends TestCase
{
    protected function setUp(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('pdo_sqlite is required for the architecture example');
        }
    }

    public function testUsersCrud(): void
    {
        $app = AppFactory::make(Database::connect('sqlite::memory:'));

        $list = $app->handle(Request::create('GET', '/users'));
        self::assertSame(200, $list->status());
        self::assertStringContainsString('Ada', $list->body());

        $created = $app->handle(Request::create(
            'POST',
            '/users',
            ['Content-Type' => 'application/json'],
            [],
            '{"name":"Grace"}',
        ));
        self::assertSame(201, $created->status());
        self::assertStringContainsString('Grace', $created->body());

        $show = $app->handle(Request::create('GET', '/users/1'));
        self::assertSame(200, $show->status());
        self::assertSame('{"id":1,"name":"Ada"}', $show->body());

        $missing = $app->handle(Request::create('GET', '/users/99'));
        self::assertSame(404, $missing->status());

        $deleted = $app->handle(Request::create('DELETE', '/users/1'));
        self::assertSame(204, $deleted->status());

        $gone = $app->handle(Request::create('GET', '/users/1'));
        self::assertSame(404, $gone->status());

        $blank = $app->handle(Request::create(
            'POST',
            '/users',
            ['Content-Type' => 'application/json'],
            [],
            '{"name":""}',
        ));
        self::assertSame(422, $blank->status());
    }
}
