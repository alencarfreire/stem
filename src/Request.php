<?php

declare(strict_types=1);

namespace Stem;

use Stem\Exceptions\HaltException;

final class Request
{
    private bool $written = false;

    private bool $sealed = false;

    /** @var list<string> */
    private array $allowed = [];

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly Router $router,
        private ?Response $response = null,
        private array $headers = [],
        private readonly array $query = [],
        private readonly string $rawBody = '',
        private bool $lazyHeaders = false,
        private readonly array $post = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!is_string($method) || $method === '') {
            $method = 'GET';
        } elseif (
            $method !== 'GET'
            && $method !== 'POST'
            && $method !== 'HEAD'
            && $method !== 'PUT'
            && $method !== 'PATCH'
            && $method !== 'DELETE'
            && $method !== 'OPTIONS'
        ) {
            $method = strtoupper($method);
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        if (!is_string($uri) || $uri === '') {
            $uri = '/';
        } else {
            $qPos = strpos($uri, '?');
            if ($qPos !== false) {
                $uri = substr($uri, 0, $qPos);
            }
        }

        $body = '';
        if ($method !== 'GET' && $method !== 'HEAD') {
            $read = file_get_contents('php://input');
            if ($read !== false) {
                $body = $read;
            }
        }

        /** @var array<string, mixed> $query */
        $query = $_GET;
        /** @var array<string, mixed> $post */
        $post = $_POST;

        $router = Router::fromPath($uri);

        return new self(
            $method,
            $router->path(),
            $router,
            null,
            [],
            $query,
            $body,
            true,
            $post,
        );
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     */
    public static function create(
        string $method,
        string $path,
        array $headers = [],
        array $query = [],
        string $body = '',
        array $post = [],
    ): self {
        if ($method !== 'GET' && $method !== 'POST' && $method !== 'HEAD' && $method !== 'PUT' && $method !== 'PATCH' && $method !== 'DELETE' && $method !== 'OPTIONS') {
            $method = strtoupper($method);
        }

        if ($headers !== []) {
            $normalized = [];
            foreach ($headers as $name => $value) {
                $normalized[strtolower($name)] = $value;
            }
            $headers = $normalized;
        }

        $router = Router::fromPath($path);

        return new self(
            $method,
            $router->path(),
            $router,
            null,
            $headers,
            $query,
            $body,
            false,
            $post,
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function remaining(): string
    {
        return $this->router->remaining();
    }

    public function isDone(): bool
    {
        return $this->written || $this->sealed;
    }

    public function isWritten(): bool
    {
        return $this->written;
    }

    /**
     * @return list<string>
     */
    public function allowed(): array
    {
        return $this->allowed;
    }

    public function header(string $name): ?string
    {
        $headers = $this->headers();

        return $headers[strtolower($name)] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        if ($this->lazyHeaders) {
            $this->headers = self::headersFromGlobals();
            $this->lazyHeaders = false;
        }

        return $this->headers;
    }

    /**
     * @return array<string, mixed>
     */
    public function query(): array
    {
        return $this->query;
    }

    public function queryParam(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function form(): array
    {
        return $this->post;
    }

    public function formParam(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    public function jsonBody(): mixed
    {
        if ($this->rawBody === '') {
            return null;
        }

        try {
            return json_decode($this->rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->halt(400, 'Invalid JSON', ['Content-Type' => 'text/plain; charset=utf-8']);
        }
    }

    public function bearerToken(): ?string
    {
        $header = $this->header('authorization');
        if ($header === null || !str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = substr($header, 7);

        return $token === '' ? null : $token;
    }

    public function wantsJson(): bool
    {
        $accept = $this->header('accept') ?? '';

        return str_contains($accept, 'application/json') || str_contains($accept, '+json');
    }

    public function response(): Response
    {
        return $this->response ??= new Response();
    }

    /**
     * @param callable(): void $callback
     */
    public function on(string $segment, callable $callback): void
    {
        if ($this->isDone() || !$this->router->consume($segment)) {
            return;
        }

        $callback();
        $this->seal();
    }

    /**
     * @param string|callable(): void $exactOrCallback
     * @param (callable(): void)|null $callback
     */
    public function is(string|callable $exactOrCallback, ?callable $callback = null): void
    {
        if ($this->isDone()) {
            return;
        }

        if ($callback === null) {
            if (is_string($exactOrCallback)) {
                throw new \InvalidArgumentException('is() requires a callback.');
            }

            if (!$this->router->atEnd()) {
                return;
            }

            $exactOrCallback();
            $this->seal();

            return;
        }

        if (!is_string($exactOrCallback)) {
            throw new \InvalidArgumentException('is() segment must be a string.');
        }

        if (!$this->router->consumeExact($exactOrCallback)) {
            return;
        }

        $callback();
        $this->seal();
    }

    /**
     * @param callable(): void $callback
     */
    public function root(callable $callback): void
    {
        if ($this->isDone() || !$this->router->atEnd()) {
            return;
        }

        $callback();
        $this->seal();
    }

    /**
     * @param callable(string): void $callback
     */
    public function onParam(callable $callback): void
    {
        if ($this->isDone()) {
            return;
        }

        $param = $this->router->consumeParam();
        if ($param === null) {
            return;
        }

        $callback($param);
        $this->seal();
    }

    /**
     * @param callable(int): void $callback
     */
    public function onInt(callable $callback): void
    {
        if ($this->isDone()) {
            return;
        }

        $id = $this->router->consumeInt();
        if ($id === null) {
            return;
        }

        $callback($id);
        $this->seal();
    }

    /**
     * Exact remaining segment as int (does not swallow `/users/1/posts`).
     *
     * @param callable(int): void $callback
     */
    public function isInt(callable $callback): void
    {
        if ($this->isDone()) {
            return;
        }

        $id = $this->router->consumeExactInt();
        if ($id === null) {
            return;
        }

        $callback($id);
        $this->seal();
    }

    /**
     * Exact remaining segment as string.
     *
     * @param callable(string): void $callback
     */
    public function isParam(callable $callback): void
    {
        if ($this->isDone()) {
            return;
        }

        $param = $this->router->consumeExactParam();
        if ($param === null) {
            return;
        }

        $callback($param);
        $this->seal();
    }

    /**
     * Run another routing closure on the current remaining path. Does not seal;
     * if the branch writes nothing, later siblings can still match.
     *
     * @param callable(Request): void $branch
     */
    public function run(callable $branch): void
    {
        if ($this->isDone()) {
            return;
        }

        $branch($this);
    }

    /**
     * O(1) dispatch on the next path segment (Roda hash_branches).
     * On hit, consumes the segment, runs the branch, then seals.
     * On miss, does nothing — later matchers can still run.
     *
     * The callable receives the current Request (remaining path is after the key).
     *
     * @param array<string, callable(Request): void> $branches
     */
    public function branches(array $branches): void
    {
        if ($this->isDone()) {
            return;
        }

        $segment = $this->router->peek();
        if ($segment === null || !isset($branches[$segment])) {
            return;
        }

        $this->router->consume($segment);
        $branches[$segment]($this);
        $this->seal();
    }

    /**
     * @param string|callable(): void $segmentOrCallback
     * @param (callable(): void)|null $callback
     */
    public function get(string|callable $segmentOrCallback, ?callable $callback = null): void
    {
        $this->verb('GET', $segmentOrCallback, $callback);
    }

    /**
     * @param string|callable(): void $segmentOrCallback
     * @param (callable(): void)|null $callback
     */
    public function post(string|callable $segmentOrCallback, ?callable $callback = null): void
    {
        $this->verb('POST', $segmentOrCallback, $callback);
    }

    /**
     * @param string|callable(): void $segmentOrCallback
     * @param (callable(): void)|null $callback
     */
    public function put(string|callable $segmentOrCallback, ?callable $callback = null): void
    {
        $this->verb('PUT', $segmentOrCallback, $callback);
    }

    /**
     * @param string|callable(): void $segmentOrCallback
     * @param (callable(): void)|null $callback
     */
    public function delete(string|callable $segmentOrCallback, ?callable $callback = null): void
    {
        $this->verb('DELETE', $segmentOrCallback, $callback);
    }

    /**
     * @param string|callable(): void $segmentOrCallback
     * @param (callable(): void)|null $callback
     */
    public function patch(string|callable $segmentOrCallback, ?callable $callback = null): void
    {
        $this->verb('PATCH', $segmentOrCallback, $callback);
    }

    /**
     * @param string|callable(): void $segmentOrCallback
     * @param (callable(): void)|null $callback
     */
    public function options(string|callable $segmentOrCallback, ?callable $callback = null): void
    {
        $this->verb('OPTIONS', $segmentOrCallback, $callback);
    }

    /**
     * @param array<string, string> $headers
     */
    public function halt(int $status, string $body = '', array $headers = []): never
    {
        $response = $this->response();
        $response->withStatus($status)->withBody($body);
        foreach ($headers as $name => $value) {
            $response->withHeader($name, $value);
        }

        $this->written = true;

        throw new HaltException($response);
    }

    public function json(mixed $data, int $status = 200): void
    {
        if ($this->written) {
            return;
        }

        $this->response()->writeJson($data, $status);
        $this->written = true;
    }

    public function html(string $html, int $status = 200): void
    {
        if ($this->written) {
            return;
        }

        $this->response()->writeHtml($html, $status);
        $this->written = true;
    }

    public function redirect(string $url, int $status = 302): void
    {
        if ($this->written) {
            return;
        }

        $this->response()
            ->withStatus($status)
            ->withHeader('Location', $url)
            ->withBody('');
        $this->written = true;
    }

    public function noContent(): void
    {
        if ($this->written) {
            return;
        }

        $this->response()->withStatus(204)->withBody('');
        $this->written = true;
    }

    /**
     * @param array{expires?:int, path?:string, domain?:string, secure?:bool, httponly?:bool, samesite?:string, maxage?:int} $options
     */
    public function cookie(string $name, string $value, array $options = []): void
    {
        $this->response()->withCookie($name, $value, $options);
    }

    /**
     * @param string|callable(): void $segmentOrCallback
     * @param (callable(): void)|null $callback
     */
    private function verb(string $want, string|callable $segmentOrCallback, ?callable $callback): void
    {
        if ($this->isDone()) {
            return;
        }

        $pathOk = false;
        $segment = null;

        if ($callback === null) {
            if (is_string($segmentOrCallback)) {
                throw new \InvalidArgumentException($want . '() requires a callback.');
            }

            $pathOk = $this->router->atEnd();
        } else {
            if (!is_string($segmentOrCallback)) {
                throw new \InvalidArgumentException($want . '() segment must be a string.');
            }

            $segment = $segmentOrCallback;
            $pathOk = $this->router->isExact($segment);
        }

        if ($pathOk) {
            $this->allow($want);
            if ($want === 'GET') {
                $this->allow('HEAD');
            }
        }

        if (!$pathOk || !$this->methodMatches($want)) {
            return;
        }

        if ($segment !== null && !$this->router->consumeExact($segment)) {
            return;
        }

        if ($callback === null) {
            $segmentOrCallback();
        } else {
            $callback();
        }

        $this->written = true;
    }

    private function methodMatches(string $want): bool
    {
        if ($this->method === $want) {
            return true;
        }

        return $want === 'GET' && $this->method === 'HEAD';
    }

    private function allow(string $method): void
    {
        if (!in_array($method, $this->allowed, true)) {
            $this->allowed[] = $method;
        }
    }

    private function seal(): void
    {
        $this->sealed = true;
    }

    /**
     * @return array<string, string>
     */
    private static function headersFromGlobals(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (!is_string($key) || !is_string($value) || $value === '') {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
                continue;
            }

            if ($key === 'CONTENT_TYPE') {
                $headers['content-type'] = $value;
                continue;
            }

            if ($key === 'CONTENT_LENGTH') {
                $headers['content-length'] = $value;
            }
        }

        return $headers;
    }
}
