# StemPHP — instructions for coding agents

This file is the source of truth for **changing this repository**. Human docs: `docs/index.html` (EN), `docs/pt/index.html` (pt-BR). Consumer-facing machine spec: `docs/llms.txt`.

Package: `alencarfreire/stem` · namespace `Stem\` · type `library` · PHP `>=8.3` · MIT · current tag `v0.2.0` (0.x: public API still treated as stable unless the user asks for a break).

After any behavioral change: `composer test` and `composer analyze` must pass. If you change routing semantics, examples, or public signatures, update **both** EN and pt-BR docs **and** `docs/llms.txt`.

---

## What this is

Roda-style **executable routing tree**. `App` stores one `Closure`. That closure **runs on every request**. Matchers are runtime `if`s that consume URI segments. There is no compiled route table, no FastRoute, no regex router, no middleware stack in core.

```
Request::create/fromGlobals → App::handle → route closure → matchers → Response
```

## Hard constraints (do not violate)

1. **Zero runtime Composer dependencies.** `require` stays `{ "php": ">=8.3" }`. FrankenPHP / RoadRunner / Swoole are `suggest` + examples only.
2. **No static request state** on `App`, `Request`, `Response`, `Router`. Integration test `LifecycleTest` asserts this.
3. **No Reflection / `is_callable` / `ArgumentCountError` arity sniffing** on the matching hot path.
4. **Matchers do not inject `Request`.** Callbacks are `function () use ($r)` or arrows. Only `onInt(int)` / `onParam(string)` pass captures.
5. **Happy path does not throw.** `json()` / `html()` set `$done` and return. Only `halt()` throws `HaltException`.
6. **No backtracking.** Once `on`/`onInt`/`onParam` consumes, siblings at that level must not see the original path. Unhandled leftover inside a taken branch → **404**. Wrong method on a visited leaf path → **405** + `Allow`.
7. **Total miss → 404.** Nothing written and no allowed methods.
8. **PHP 8.3 syntax only** in `src/` (no property hooks / 8.4-only). `declare(strict_types=1);` every PHP file. Classes `final`. No `__call` / `__get`.
9. **PHPStan level 9 + strict-rules** on `src` and `tests`. Do not weaken the config to land a change.
10. **Do not add PSR-7/PSR-15 to core.** `handle(Request): Response` is the runtime contract. Adapters live in `examples/`.
11. **Do not invent benchmarks** in README or docs.

## File map

| Path | Role |
|---|---|
| `src/App.php` | Kernel: `route` (once), `handle`, `run` |
| `src/Request.php` | HTTP message + routing API (`$r`) |
| `src/Router.php` | Path cursor (`list<string> $segments` + `int $offset`). `@internal` |
| `src/Response.php` | Mutable status/headers/body; lazy-allocated from Request |
| `src/Exceptions/HaltException.php` | Only `Request::halt()` |
| `src/Integrations/FrankenPhpWorker.php` | Optional worker loop; `function_exists` guard |
| `tests/Unit/RoutingTreeTest.php` | Matcher semantics |
| `tests/Unit/RequestPathTest.php` | Normalization, `fromGlobals`, `onInt` rules |
| `tests/Unit/ResponseTest.php` | JSON/HTML, header injection |
| `tests/Integration/LifecycleTest.php` | No leak across `handle()` on the same `App` |
| `examples/basic/` | Default FPM / `php -S` |
| `examples/frankenphp/` | Worker |
| `examples/roadrunner/` `examples/swoole/` | Adapters, **not** required packages |
| `docs/` | GitHub Pages (`/docs` on `main`) |
| `docs/llms.txt` | Machine spec for other agents |

Do not invent new public classes without a product reason. Prefer extending `Request` matchers over a plugin system (out of scope until asked).

## Routing semantics (must preserve)

| Call | Match rule | After match |
|---|---|---|
| `on($seg, $cb)` | prefix consume | `$cb()` then seal (siblings skip) |
| `is($seg, $cb)` | exact remaining `$seg` | same |
| `is($cb)` | `atEnd()` | same |
| `root($cb)` | `atEnd()`, any method | same |
| `get/post/put/delete/patch/options($cb)` | method **and** `atEnd()` (terminal; **not** Roda’s bare `r.get`). `HEAD` matches `get()`. | write if callback runs |
| `get($seg, $cb)` | method + `consumeExact($seg)` | same |
| `onInt($cb)` | `ctype_digit`, no leading zeros except `"0"`, `(int)` round-trip (overflow reject) | `$cb($id)` then seal |
| `onParam($cb)` | next non-empty segment | `$cb($seg)` then seal |
| `json` / `html` / `redirect` / `noContent` | write response | written, **no throw** |
| `halt` | write response | written, **throw HaltException** |

Every matcher starts with `if ($this->done) return;` **before** consuming. Consuming before the `$done` check is a bug (it advances the cursor after the request is finished).

Path parse (`Router::fromPath`): strip `?` and `#` with `strpos` (not `parse_url`); rtrim `/` except root; collapse `//`; `rawurldecode` only if `%` present; cache segment count.

`fromGlobals`: GET/HEAD skip `php://input`; headers lazy (`$lazyHeaders`); do not clone `$_SERVER`.

`Response` is `null` until first `json`/`html`/`halt`/`response()`. 404 allocates a **new** `Response` in `App`, not the request’s.

## Performance (hot path)

Keep these true unless a measured profile says otherwise:

- No regex in matching.
- No `filter_var` in `onInt` — `ctype_digit` + `(int)` + `(string)$int !== $segment` for overflow.
- `count()` of segments is cached on `Router`.
- `FrankenPhpWorker::run(..., collectEvery: 0)` — never default to `gc_collect_cycles()` per request.
- `is_string` to distinguish `get($cb)` vs `get($seg, $cb)`, not `is_callable`.

## Tests

```bash
composer test      # PHPUnit 11
composer analyze   # PHPStan 9
composer format    # PHP-CS-Fixer PSR-12
```

Tests must not hit the network. Use `Request::create` + `App::handle`. Do not call `send()` except the dedicated output-buffer test.

When adding a matcher or changing consume rules, add cases to `RoutingTreeTest` **and** a sibling-skip / `$done` case.

In-repo fixture: `examples/basic/index.php`. Consumer docs must show `index.php` next to `vendor/` and `php -S localhost:8080 index.php`, never `examples/basic/`.

## Docs site

GitHub Pages serves `docs/` on `main` → https://alencarfreire.github.io/stem/

- EN: `docs/index.html`
- pt-BR: `docs/pt/index.html` (paths to CSS/JS are `../`)
- Shared: `docs/styles.css`, `docs/app.js` (playground strings follow `html[lang]`)
- Machine: `docs/llms.txt`

Language switcher must stay in the topbar of both pages. Do not ship EN-only doc changes.

## Versioning / publish

- Do **not** put `"version"` in `composer.json`.
- Versions come from annotated git tags: `v0.1.0`.
- After a user-approved release: `git tag -a vX.Y.Z` and `git push origin vX.Y.Z` (+ `gh release create` if that is the repo habit).
- Packagist name is `alencarfreire/stem`. Similar names (`danharper/stem`, `stem-press/stem`) are unrelated — do not rename the vendor to dodge that notice.

## Out of scope until explicitly asked

PSR-7/15 core, sessions, views, DI container, plugin system, Roda `hash_branches`, RoadRunner/Swoole as Composer requires, fake wrk numbers, PHP < 8.3.
