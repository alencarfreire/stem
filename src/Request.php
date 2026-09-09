<?php

declare(strict_types=1);

namespace Stem;

use Stem\Exceptions\HaltException;

final class Request
{
    private bool $done = false;

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $query
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
        );
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $query
     */
    public static function create(
        string $method,
        string $path,
        array $headers = [],
        array $query = [],
        string $body = '',
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
        return $this->done;
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

    public function rawBody(): string
    {
        return $this->rawBody;
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
        if ($this->done || !$this->router->consume($segment)) {
            return;
        }

        $callback();
        $this->done = true;
    }

    /**
     * @param string|callable(): void $exactOrCallback
     * @param (callable(): void)|null $callback
     */
    public function is(string|callable $exactOrCallback, ?callable $callback = null): void
    {
        if ($this->done) {
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
            $this->done = true;

            return;
        }

        if (!is_string($exactOrCallback)) {
            throw new \InvalidArgumentException('is() segment must be a string.');
        }

        if (!$this->router->consumeExact($exactOrCallback)) {
            return;
        }

        $callback();
        $this->done = true;
    }

    /**
     * @param callable(): void $callback
     */
    public function root(callable $callback): void
    {
        if ($this->done || !$this->router->atEnd()) {
            return;
        }

        $callback();
        $this->done = true;
    }

    /**
     * @param callable(string): void $callback
     */
    public function onParam(callable $callback): void
    {
        if ($this->done) {
            return;
        }

        $param = $this->router->consumeParam();
        if ($param === null) {
            return;
        }

        $callback($param);
        $this->done = true;
    }

    /**
     * @param callable(int): void $callback
     */
    public function onInt(callable $callback): void
    {
        if ($this->done) {
            return;
        }

        $id = $this->router->consumeInt();
        if ($id === null) {
            return;
        }

        $callback($id);
        $this->done = true;
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
     * @param array<string, string> $headers
     */
    public function halt(int $status, string $body = '', array $headers = []): never
    {
        $response = $this->response();
        $response->withStatus($status)->withBody($body);
        foreach ($headers as $name => $value) {
            $response->withHeader($name, $value);
        }

        $this->done = true;

        throw new HaltException($response);
    }

    public function json(mixed $data, int $status = 200): void
    {
        if ($this->done) {
            return;
        }

        $this->response()->writeJson($data, $status);
        $this->done = true;
    }

    public function html(string $html, int $status = 200): void
    {
        if ($this->done) {
            return;
        }

        $this->response()->writeHtml($html, $status);
        $this->done = true;
    }

    /**
     * @param string|callable(): void $segmentOrCallback
     * @param (callable(): void)|null $callback
     */
    private function verb(string $want, string|callable $segmentOrCallback, ?callable $callback): void
    {
        if ($this->done || $this->method !== $want) {
            return;
        }

        if ($callback === null) {
            if (is_string($segmentOrCallback)) {
                throw new \InvalidArgumentException($want . '() requires a callback.');
            }

            if (!$this->router->atEnd()) {
                return;
            }

            $segmentOrCallback();
            $this->done = true;

            return;
        }

        if (!is_string($segmentOrCallback)) {
            throw new \InvalidArgumentException($want . '() segment must be a string.');
        }

        if (!$this->router->consumeExact($segmentOrCallback)) {
            return;
        }

        $callback();
        $this->done = true;
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
