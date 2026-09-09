# StemPHP

[![Tests](https://github.com/alencarfreire/stem/actions/workflows/tests.yml/badge.svg)](https://github.com/alencarfreire/stem/actions/workflows/tests.yml)
[![Packagist](https://img.shields.io/packagist/v/alencarfreire/stem.svg)](https://packagist.org/packages/alencarfreire/stem)
[![PHP](https://img.shields.io/badge/php-%3E%3D8.3-777bb4.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![Docs](https://img.shields.io/badge/docs-GitHub%20Pages-d4f25a?labelColor=0c0d0b)](https://alencarfreire.github.io/stem/)

Ultralight **executable routing-tree** micro-framework for PHP 8.3+. Inspired by [Roda](https://roda.jeremyevans.net/) (Ruby). Zero runtime dependencies.

**Docs:** [English](https://alencarfreire.github.io/stem/) · [Português (Brasil)](https://alencarfreire.github.io/stem/pt/)

Runs on **PHP-FPM**, `php -S`, Apache, FrankenPHP (with or without worker mode), RoadRunner, and Swoole. FrankenPHP is an optional fast path, not a requirement.

```php
use Stem\App;
use Stem\Request;

$app = new App();

$app->route(function (Request $r): void {
    $r->root(fn () => $r->json(['message' => 'StemPHP API']));

    $r->on('users', function () use ($r): void {
        $r->get(fn () => $r->json([['id' => 1, 'name' => 'John']]));

        $r->onInt(function (int $id) use ($r): void {
            $r->get(fn () => $r->json(['id' => $id, 'name' => 'John']));
        });
    });
});

$app->run();
```

```bash
composer require alencarfreire/stem
php -S localhost:8080 examples/basic/index.php
```

## Routing tree

The route closure **runs on every request**. Matchers consume the remaining path. A match finishes that level (`$done`); siblings do not run. If nothing matches, `handle()` returns 404.

| Call | Meaning |
|---|---|
| `$r->on('users', $cb)` | Prefix. Consumes `users`, enters the branch. |
| `$r->is('users', $cb)` | Exact remaining path `/users`. |
| `$r->is($cb)` | Remaining path is empty. |
| `$r->get($cb)` / `post` / `put` / `delete` / `patch` | HTTP method **and** remaining path empty. |
| `$r->get('users', $cb)` | Method + exact remaining `/users`. |
| `$r->root($cb)` | Remaining path is `/` or empty (any method). |
| `$r->onInt($cb)` | Prefix: next segment is an integer (`0`, `42`; not `01`, `-1`). |
| `$r->onParam($cb)` | Prefix: next non-empty segment, as `string`. |
| `$r->json($data, $status = 200)` | JSON body + `Content-Type`. Sets `$done`. Does not throw. |
| `$r->html($html, $status = 200)` | HTML body. Sets `$done`. Does not throw. |
| `$r->halt($status, $body, $headers)` | Early exit (`never`). Throws an internal `HaltException`. |

Matchers do **not** inject `Request` as an argument (no Reflection on the hot path). Use `use ($r)` or arrow functions, which already capture `$r` from the `route()` closure. Captures (`onInt` / `onParam`) are the only extra arguments.

## Request isolation

`App` stores only the route closure. Each `handle()` gets a new `Request`; `Response` is created on first write. No static request state. Safe for FrankenPHP worker mode, RoadRunner, and Swoole.

```php
$response = $app->handle(Request::create('GET', '/users/1'));
```

`Request::fromGlobals()` must be called **inside** the per-request handler, never at worker boot.

## Deploy

### PHP-FPM / `php -S` (default)

Point the document root at a front controller that calls `$app->run()`. See `examples/basic/index.php`.

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

Measure locally; do not publish invented numbers:

```bash
php -S 127.0.0.1:8080 examples/bench/hello.php
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

## License

MIT
