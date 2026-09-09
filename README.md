# StemPHP

[![Tests](https://github.com/alencarfreire/stem/actions/workflows/tests.yml/badge.svg)](https://github.com/alencarfreire/stem/actions/workflows/tests.yml)
[![Packagist](https://img.shields.io/packagist/v/alencarfreire/stem.svg)](https://packagist.org/packages/alencarfreire/stem)
[![PHP](https://img.shields.io/badge/php-%3E%3D8.3-777bb4.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![Docs](https://img.shields.io/badge/docs-GitHub%20Pages-d4f25a?labelColor=0c0d0b)](https://alencarfreire.github.io/stem/)

Ultralight **executable routing-tree** micro-framework for PHP 8.3+. Inspired by [Roda](https://roda.jeremyevans.net/) (Ruby). Zero runtime dependencies.

**Docs:** [English](https://alencarfreire.github.io/stem/) · [Português (Brasil)](https://alencarfreire.github.io/stem/pt/) · [llms.txt](https://alencarfreire.github.io/stem/llms.txt)

Indicated app layout (PDO, not an ORM): [`examples/app/`](examples/app/) — [Architecture](https://alencarfreire.github.io/stem/#architecture).

Runs on **PHP-FPM**, `php -S`, Apache, FrankenPHP (with or without worker mode), RoadRunner, and Swoole. FrankenPHP is an optional fast path, not a requirement.

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Stem\App;
use Stem\Request;

$app = new App();
$app->notFound(fn (Request $r) => $r->json(['error' => 'not_found'], 404));

$app->route(function (Request $r): void {
    $r->root(fn () => $r->json(['ok' => true]));

    $r->on('users', function () use ($r): void {
        $r->get(fn () => $r->json([['id' => 1, 'name' => 'Ada']]));

        $r->post(function () use ($r): void {
            $name = ($r->jsonBody() ?? [])['name'] ?? '';
            if ($name === '') {
                $r->json(['error' => 'name required'], 422);
                return;
            }
            $r->json(['id' => 2, 'name' => $name], 201);
        });

        $r->onInt(function (int $id) use ($r): void {
            $r->get(fn () => $r->json(['id' => $id, 'name' => 'Ada']));
            $r->delete(fn () => $r->noContent());
        });
    });
});

$app->run();
```

```bash
composer require alencarfreire/stem:^0.2
php -S localhost:8080 index.php

curl -s localhost:8080/users
curl -s -X POST localhost:8080/users -H 'Content-Type: application/json' -d '{"name":"Grace"}'
curl -s localhost:8080/users/1
```

## Routing tree

The route closure **runs on every request**. Matchers consume the remaining path. A match finishes that level; siblings do not run. Total miss → 404. Branch taken, leftover path → 404. Wrong method → 405 + `Allow`.

| Call | Meaning |
|---|---|
| `$r->on('users', $cb)` | Prefix. Consumes `users`, enters the branch. |
| `$r->is('users', $cb)` | Exact remaining path `/users`. |
| `$r->is($cb)` | Remaining path is empty. |
| `$r->get($cb)` / `post` / `put` / `delete` / `patch` | HTTP method **and** remaining path empty. |
| `$r->get('users', $cb)` | Method + exact remaining `/users`. |
| `$r->root($cb)` | Remaining path is `/` or empty (any method). |
| `$r->onInt($cb)` | Prefix: next segment is an integer (`0`, `42`; not `01`, `-1`). |
| `$r->isInt($cb)` | Exact remaining integer (not `/users/1/posts`). |
| `$r->onParam($cb)` | Prefix: next non-empty segment, as `string`. |
| `$r->isParam($cb)` | Exact remaining one segment. |
| `$r->run($branch)` | Run `function (Request $r)` on the current path; does not seal on miss. |
| `$r->json($data, $status = 200)` | JSON body + `Content-Type`. Sets `$done`. Does not throw. |
| `$r->html($html, $status = 200)` | HTML body. Sets `$done`. Does not throw. |
| `$r->halt($status, $body, $headers)` | Early exit (`never`). Throws an internal `HaltException`. |
| `$r->redirect($url, $status = 302)` | `Location`. |
| `$r->noContent()` | HTTP 204. |
| `$r->jsonBody()` / `queryParam` / `formParam` / `bearerToken` | Request helpers. Invalid JSON → `halt(400)`. |

Matchers do **not** inject `Request` as an argument (no Reflection on the hot path). Use `use ($r)` or arrow functions, which already capture `$r` from the `route()` closure. Captures (`onInt` / `onParam`) are the only extra arguments.

## Request isolation

`App` stores only the route closure. Each `handle()` gets a new `Request`; `Response` is created on first write. No static request state. Safe for FrankenPHP worker mode, RoadRunner, and Swoole.

```php
$response = $app->handle(Request::create('GET', '/users/1'));
```

`Request::fromGlobals()` must be called **inside** the per-request handler, never at worker boot.

## Deploy

### PHP-FPM / `php -S` (default)

Save the app as `index.php` next to `vendor/` and serve it as the front controller (`php -S localhost:8080 index.php`, or point PHP-FPM/Apache at that file).

### FrankenPHP worker mode (optional)

```php
use Stem\Integrations\FrankenPhpWorker;

FrankenPhpWorker::run($app, maxRequests: (int) ($_SERVER['MAX_REQUESTS'] ?? 0));
// collectEvery: 0 (default) skips gc_collect_cycles(); set e.g. 128 if a leaky extension forces it.
```

`$app` is created **outside** the loop. See `examples/frankenphp/`.

### RoadRunner / Swoole

`App::handle()` is the runtime contract. Convert the incoming request to `Request::create(...)`, then write `$response->status()`, `headers()`, and `body()`. See `examples/roadrunner/` and `examples/swoole/`. Those packages are **not** Composer requirements.

## Performance

StemPHP matches Roda's model: routing cost is `O(path depth × siblings at that level)`, not `O(total routes)`. It is not a compiled matcher (FastRoute / Symfony Routing). The hot path has no regex, no Reflection, and no exception on `json()` / `html()`.

Per-request work that is **not** on the matching path is deferred or skipped:

- Path parse uses `strpos`/`explode`, not `parse_url`. `rawurldecode` runs only if the URI contains `%`.
- `GET`/`HEAD` do not read `php://input`.
- `$_SERVER` headers are scanned only if `$r->header()` / `headers()` is called.
- `Response` is allocated on first write (`json`/`html`/`halt`/`response()`), not on every request.
- `onInt` uses `ctype_digit` + `(int)` (no `filter_var`).
- Worker `gc_collect_cycles()` is off unless you pass `collectEvery`.

Measure against your own `index.php`. Do not publish invented numbers:

```bash
php -S 127.0.0.1:8080 index.php
wrk -t4 -c64 -d10s http://127.0.0.1:8080/
```

## Development

```bash
composer install
composer test
composer analyze
composer format
```

Requires PHP 8.3+. CI runs 8.3 and 8.4.

## AI agents

- Maintain this repo: [`AGENTS.md`](AGENTS.md)
- Consume the API from another project: [`docs/llms.txt`](https://alencarfreire.github.io/stem/llms.txt)

## License

MIT
