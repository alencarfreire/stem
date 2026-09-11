<?php

declare(strict_types=1);

namespace Stem\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stem\App;
use Stem\Request;
use Stem\Response;

final class RoutingTreeTest extends TestCase
{
    public function testRootMatchesOnlyTheSlash(): void
    {
        $app = $this->app(function (Request $r): void {
            $r->root(fn () => $r->json(['root' => true]));
        });

        $ok = $this->handle($app, 'GET', '/');
        self::assertSame(200, $ok->status());
        self::assertSame('{"root":true}', $ok->body());

        $miss = $this->handle($app, 'GET', '/users');
        self::assertSame(404, $miss->status());
    }

    public function testOnMatchesPrefixAndIsRequiresExactRemaining(): void
    {
        $app = $this->app(function (Request $r): void {
            $r->on('users', function () use ($r): void {
                $r->json(['branch' => true, 'remaining' => $r->remaining()]);
            });
        });

        $list = $this->handle($app, 'GET', '/users');
        self::assertSame(200, $list->status());
        self::assertSame('{"branch":true,"remaining":""}', $list->body());

        $nested = $this->handle($app, 'GET', '/users/1');
        self::assertSame('{"branch":true,"remaining":"/1"}', $nested->body());

        $miss = $this->handle($app, 'GET', '/posts');
        self::assertSame(404, $miss->status());

        $exact = $this->app(function (Request $r): void {
            $r->is('users', fn () => $r->json(['exact' => true]));
        });

        self::assertSame(200, $this->handle($exact, 'GET', '/users')->status());
        self::assertSame(404, $this->handle($exact, 'GET', '/users/1')->status());
    }

    public function testGetIsTerminalForMethodAndRemainingPath(): void
    {
        $app = $this->app(function (Request $r): void {
            $r->on('users', function () use ($r): void {
                $r->get(fn () => $r->json(['verb' => 'GET']));
                $r->post(fn () => $r->json(['verb' => 'POST'], 201));
            });
        });

        $get = $this->handle($app, 'GET', '/users');
        self::assertSame(200, $get->status());
        self::assertSame('{"verb":"GET"}', $get->body());

        $post = $this->handle($app, 'POST', '/users');
        self::assertSame(201, $post->status());
        self::assertSame('{"verb":"POST"}', $post->body());

        $extra = $this->handle($app, 'GET', '/users/1');
        self::assertSame(404, $extra->status());
        self::assertSame('Not Found', $extra->body());

        $put = $this->handle($app, 'PUT', '/users');
        self::assertSame(405, $put->status());
        self::assertSame('GET, HEAD, POST', $put->headers()['Allow']);
        self::assertSame('Method Not Allowed', $put->body());
    }

    public function testAllVerbsRespectTheHttpMethod(): void
    {
        $app = $this->app(function (Request $r): void {
            $r->is(function () use ($r): void {
                $r->put(fn () => $r->json(['verb' => 'PUT']));
                $r->patch(fn () => $r->json(['verb' => 'PATCH']));
                $r->delete(fn () => $r->halt(204));
            });
        });

        self::assertSame('{"verb":"PUT"}', $this->handle($app, 'PUT', '/')->body());
        self::assertSame('{"verb":"PATCH"}', $this->handle($app, 'PATCH', '/')->body());
        self::assertSame(204, $this->handle($app, 'DELETE', '/')->status());
        $get = $this->handle($app, 'GET', '/');
        self::assertSame(405, $get->status());
        self::assertSame('DELETE, PATCH, PUT', $get->headers()['Allow']);
    }

    public function testGetWithSegmentIsExact(): void
    {
        $app = $this->app(function (Request $r): void {
            $r->get('about', fn () => $r->html('<h1>About</h1>'));
        });

        $ok = $this->handle($app, 'GET', '/about');
        self::assertSame(200, $ok->status());
        self::assertSame('<h1>About</h1>', $ok->body());
        self::assertSame(Response::CONTENT_TYPE_HTML, $ok->headers()['Content-Type']);
        self::assertSame(404, $this->handle($app, 'GET', '/about/team')->status());
        $post = $this->handle($app, 'POST', '/about');
        self::assertSame(405, $post->status());
        self::assertSame('GET, HEAD', $post->headers()['Allow']);
    }

    public function testOnIntCapturesIntegersAndRejectsInvalidSegments(): void
    {
        $app = $this->app(function (Request $r): void {
            $r->on('users', function () use ($r): void {
                $r->onInt(function (int $id) use ($r): void {
                    $r->get(fn () => $r->json(['id' => $id]));
                });
            });
        });

        self::assertSame('{"id":42}', $this->handle($app, 'GET', '/users/42')->body());
        self::assertSame('{"id":0}', $this->handle($app, 'GET', '/users/0')->body());

        foreach (['/users/abc', '/users/01', '/users/-1', '/users/1.5', '/users/9223372036854775808'] as $path) {
            $miss = $this->handle($app, 'GET', $path);
            self::assertSame(404, $miss->status(), $path);
            self::assertSame('Not Found', $miss->body(), $path);
        }

        $top = $this->app(function (Request $r): void {
            $r->onInt(function (int $id) use ($r): void {
                $r->json(['id' => $id]);
            });
        });
        self::assertSame(404, $this->handle($top, 'GET', '/abc')->status());
        self::assertSame('{"id":7}', $this->handle($top, 'GET', '/7')->body());
    }

    public function testOnParamCapturesTheNextSegment(): void
    {
        $app = $this->app(function (Request $r): void {
            $r->on('users', function () use ($r): void {
                $r->onParam(function (string $name) use ($r): void {
                    $r->get(fn () => $r->json(['name' => $name]));
                });
            });
        });

        self::assertSame('{"name":"john-doe"}', $this->handle($app, 'GET', '/users/john-doe')->body());
    }

    public function testIsIntDoesNotSwallowExtraSegments(): void
    {
        $app = $this->app(function (Request $r): void {
            $r->on('users', function () use ($r): void {
                $r->isInt(function (int $id) use ($r): void {
                    $r->get(fn () => $r->json(['id' => $id]));
                });
                $r->onInt(function (int $id) use ($r): void {
                    $r->on('posts', function () use ($r, $id): void {
                        $r->get(fn () => $r->json(['user' => $id, 'posts' => true]));
                    });
                });
            });
        });

        self::assertSame('{"id":7}', $this->handle($app, 'GET', '/users/7')->body());
        self::assertSame('{"user":7,"posts":true}', $this->handle($app, 'GET', '/users/7/posts')->body());
        self::assertSame(404, $this->handle($app, 'GET', '/users/abc')->status());
    }

    public function testIsParamIsExactRemaining(): void
    {
        $app = $this->app(function (Request $r): void {
            $r->on('u', function () use ($r): void {
                $r->isParam(function (string $name) use ($r): void {
                    $r->get(fn () => $r->json(['name' => $name]));
                });
            });
        });

        self::assertSame('{"name":"ada"}', $this->handle($app, 'GET', '/u/ada')->body());
        self::assertSame(404, $this->handle($app, 'GET', '/u/ada/edit')->status());
    }

    public function testRunComposesBranchesWithoutSealingOnMiss(): void
    {
        $users = static function (Request $r): void {
            $r->on('users', function () use ($r): void {
                $r->get(fn () => $r->json(['from' => 'users']));
            });
        };
        $posts = static function (Request $r): void {
            $r->on('posts', function () use ($r): void {
                $r->get(fn () => $r->json(['from' => 'posts']));
            });
        };

        $app = $this->app(function (Request $r) use ($users, $posts): void {
            $r->run($users);
            $r->run($posts);
        });

        self::assertSame('{"from":"users"}', $this->handle($app, 'GET', '/users')->body());
        self::assertSame('{"from":"posts"}', $this->handle($app, 'GET', '/posts')->body());
        self::assertSame(404, $this->handle($app, 'GET', '/other')->status());
    }

    public function testBranchesLooksUpTheNextSegmentInConstantTime(): void
    {
        $log = new class () {
            /** @var list<string> */
            public array $hits = [];
        };
        $app = $this->app(function (Request $r) use ($log): void {
            $r->root(fn () => $r->json(['root' => true]));
            $r->branches([
                'users' => function (Request $r) use ($log): void {
                    $log->hits[] = 'users';
                    $r->get(fn () => $r->json(['from' => 'users']));
                },
                'customers' => function (Request $r) use ($log): void {
                    $log->hits[] = 'customers';
                    $r->get(fn () => $r->json(['from' => 'customers']));
                },
            ]);
        });

        self::assertSame('{"root":true}', $this->handle($app, 'GET', '/')->body());
        self::assertSame([], $log->hits);

        self::assertSame('{"from":"customers"}', $this->handle($app, 'GET', '/customers')->body());
        self::assertSame(['customers'], $log->hits);

        $log->hits = [];
        self::assertSame('{"from":"users"}', $this->handle($app, 'GET', '/users')->body());
        self::assertSame(['users'], $log->hits);

        $log->hits = [];
        self::assertSame(404, $this->handle($app, 'GET', '/other')->status());
        self::assertSame([], $log->hits);
    }

    public function testOnUuidAndIsUuid(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $app = $this->app(function (Request $r): void {
            $r->on('items', function () use ($r): void {
                $r->isUuid(function (string $id) use ($r): void {
                    $r->get(fn () => $r->json(['id' => $id, 'exact' => true]));
                });
                $r->onUuid(function (string $id) use ($r): void {
                    $r->on('meta', function () use ($r, $id): void {
                        $r->get(fn () => $r->json(['id' => $id, 'meta' => true]));
                    });
                });
            });
        });

        self::assertSame(
            '{"id":"' . $uuid . '","exact":true}',
            $this->handle($app, 'GET', '/items/' . $uuid)->body(),
        );
        self::assertSame(
            '{"id":"' . $uuid . '","meta":true}',
            $this->handle($app, 'GET', '/items/' . $uuid . '/meta')->body(),
        );
        self::assertSame(404, $this->handle($app, 'GET', '/items/not-a-uuid')->status());
    }

    public function testWhenSealsOnlyIfPredicateIsTrue(): void
    {
        $app = $this->app(function (Request $r): void {
            $r->when(fn () => $r->wantsJson(), fn () => $r->json(['json' => true]));
            $r->when(fn () => true, fn () => $r->html('<p>html</p>'));
        });

        $json = $this->handle($app, 'GET', '/', ['Accept' => 'application/json']);
        self::assertSame('{"json":true}', $json->body());

        $html = $this->handle($app, 'GET', '/', ['Accept' => 'text/html']);
        self::assertSame('<p>html</p>', $html->body());
    }

    public function testCtxStoresValuesForTheCurrentRequest(): void
    {
        $app = $this->app(function (Request $r): void {
            $r->onInt(function (int $id) use ($r): void {
                $r->ctx('id', $id);
                $r->get(fn () => $r->json(['id' => $r->ctx('id')]));
            });
        });

        self::assertSame('{"id":9}', $this->handle($app, 'GET', '/9')->body());
    }

    public function testNestedUsersIdGet(): void
    {
        $app = $this->canonical();

        $response = $this->handle($app, 'GET', '/users/7');
        self::assertSame(200, $response->status());
        self::assertSame('{"id":7,"name":"John"}', $response->body());
        self::assertSame(Response::CONTENT_TYPE_JSON, $response->headers()['Content-Type']);
    }

    public function testHaltSetsStatusAndBody(): void
    {
        $app = $this->app(function (Request $r): void {
            $r->on('secret', function () use ($r): void {
                $r->halt(403, 'no', ['X-Denied' => '1']);
            });
        });

        $response = $this->handle($app, 'GET', '/secret');
        self::assertSame(403, $response->status());
        self::assertSame('no', $response->body());
        self::assertSame('1', $response->headers()['X-Denied']);
    }

    public function testHaltInTheMiddleOfACallbackSkipsLaterMatchers(): void
    {
        $app = $this->app(function (Request $r): void {
            $r->onInt(function (int $id) use ($r): void {
                if ($id < 1) {
                    $r->halt(404, 'missing');
                }

                $r->get(fn () => $r->json(['id' => $id]));
            });
        });

        $denied = $this->handle($app, 'GET', '/0');
        self::assertSame(404, $denied->status());
        self::assertSame('missing', $denied->body());

        $ok = $this->handle($app, 'GET', '/2');
        self::assertSame('{"id":2}', $ok->body());
    }

    public function testTotalMissIs404(): void
    {
        $app = $this->canonical();
        $response = $this->handle($app, 'GET', '/nope');
        self::assertSame(404, $response->status());
        self::assertSame('Not Found', $response->body());
    }

    public function testMatchedSiblingIsNotExecuted(): void
    {
        $hits = [];
        $app = $this->app(function (Request $r) use (&$hits): void {
            $r->on('users', function () use ($r, &$hits): void {
                $hits[] = 'users';
                $r->json(['ok' => 'users']);
            });
            $r->on('users', function () use (&$hits): void {
                $hits[] = 'duplicate';
            });
            $r->on('posts', function () use ($r, &$hits): void {
                $hits[] = 'posts';
                $r->json(['ok' => 'posts']);
            });
        });

        $response = $this->handle($app, 'GET', '/users');
        self::assertSame('{"ok":"users"}', $response->body());
        self::assertSame(['users'], $hits);
    }

    public function testJsonOnTheHappyPathDoesNotRequireHalt(): void
    {
        $app = $this->canonical();
        $response = $this->handle($app, 'GET', '/');
        self::assertSame(200, $response->status());
        self::assertSame('{"message":"StemPHP API"}', $response->body());
    }

    public function testRemainingPathAfterOn(): void
    {
        $app = $this->app(function (Request $r): void {
            $r->on('api', function () use ($r): void {
                $r->json(['remaining' => $r->remaining()]);
            });
        });

        self::assertSame('{"remaining":"/v1"}', $this->handle($app, 'GET', '/api/v1')->body());
    }

    public function testTrailingSlashEqualsCanonicalPath(): void
    {
        $app = $this->canonical();
        self::assertSame(
            $this->handle($app, 'GET', '/users')->body(),
            $this->handle($app, 'GET', '/users/')->body(),
        );
    }

    public function testHeadUsesGetHandler(): void
    {
        $app = $this->canonical();
        $response = $this->handle($app, 'HEAD', '/users');
        self::assertSame(200, $response->status());
        self::assertSame('[{"id":1,"name":"John"}]', $response->body());
    }

    public function testOptionsReturnsAllowWhenVerbsWereVisited(): void
    {
        $app = $this->canonical();
        $response = $this->handle($app, 'OPTIONS', '/users');
        self::assertSame(204, $response->status());
        self::assertSame('GET, HEAD, POST', $response->headers()['Allow']);
        self::assertSame('', $response->body());
    }

    public function testCustomNotFoundHandler(): void
    {
        $app = $this->canonical()->notFound(function (Request $r): void {
            $r->json(['error' => 'missing'], 404);
        });

        $response = $this->handle($app, 'GET', '/nope');
        self::assertSame(404, $response->status());
        self::assertSame('{"error":"missing"}', $response->body());
    }

    public function testErrorHandlerCatchesThrowables(): void
    {
        $app = $this->app(function (Request $r): void {
            $r->root(function (): void {
                throw new \RuntimeException('boom');
            });
        })->error(function (\Throwable $e, Request $r): void {
            $r->json(['error' => $e->getMessage()], 500);
        });

        $response = $this->handle($app, 'GET', '/');
        self::assertSame(500, $response->status());
        self::assertSame('{"error":"boom"}', $response->body());
    }

    public function testRedirectAndNoContent(): void
    {
        $app = $this->app(function (Request $r): void {
            $r->get('go', fn () => $r->redirect('/users', 301));
            $r->delete('go', fn () => $r->noContent());
        });

        $redirect = $this->handle($app, 'GET', '/go');
        self::assertSame(301, $redirect->status());
        self::assertSame('/users', $redirect->headers()['Location']);

        $empty = $this->handle($app, 'DELETE', '/go');
        self::assertSame(204, $empty->status());
        self::assertSame('', $empty->body());
    }

    public function testQueryStringDoesNotAffectMatching(): void
    {
        $app = $this->canonical();
        $response = $this->handle($app, 'GET', '/users?x=1', [], ['x' => '1']);
        self::assertSame(200, $response->status());
        self::assertSame('[{"id":1,"name":"John"}]', $response->body());
    }

    /**
     * @param \Closure(Request): void $route
     */
    private function app(\Closure $route): App
    {
        return (new App())->route($route);
    }

    private function canonical(): App
    {
        return $this->app(function (Request $r): void {
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
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $query
     */
    private function handle(
        App $app,
        string $method,
        string $path,
        array $headers = [],
        array $query = [],
        string $body = '',
    ): Response {
        return $app->handle(Request::create($method, $path, $headers, $query, $body));
    }
}
