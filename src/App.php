<?php

declare(strict_types=1);

namespace Stem;

use Stem\Exceptions\HaltException;

final class App
{
    /** @var array<string, string> */
    private const array NOT_FOUND_HEADERS = ['Content-Type' => 'text/plain; charset=utf-8'];

    /** @var (\Closure(Request): void)|null */
    private ?\Closure $route = null;

    /** @var (\Closure(Request): void)|null */
    private ?\Closure $notFound = null;

    /** @var (\Closure(\Throwable, Request): void)|null */
    private ?\Closure $error = null;

    /**
     * @param \Closure(Request): void $route
     */
    public function route(\Closure $route): self
    {
        if ($this->route !== null) {
            throw new \LogicException('Route tree already defined. Create a new App to register another tree.');
        }

        $this->route = $route;

        return $this;
    }

    /**
     * @param \Closure(Request): void $handler
     */
    public function notFound(\Closure $handler): self
    {
        $this->notFound = $handler;

        return $this;
    }

    /**
     * @param \Closure(\Throwable, Request): void $handler
     */
    public function error(\Closure $handler): self
    {
        $this->error = $handler;

        return $this;
    }

    public function handle(Request $request): Response
    {
        $route = $this->route;
        if ($route === null) {
            throw new \LogicException('No route defined. Call App::route() first.');
        }

        try {
            $route($request);
        } catch (HaltException $exception) {
            return $exception->response();
        } catch (\Throwable $exception) {
            if ($this->error === null) {
                throw $exception;
            }

            ($this->error)($exception, $request);
            if ($request->isWritten()) {
                return $request->response();
            }

            throw $exception;
        }

        if ($request->isWritten()) {
            return $request->response();
        }

        return $this->unmatched($request);
    }

    private function unmatched(Request $request): Response
    {
        $allowed = $request->allowed();
        if ($allowed !== []) {
            sort($allowed);
            $allowHeader = implode(', ', $allowed);

            if ($request->method() === 'OPTIONS') {
                return new Response(204, ['Allow' => $allowHeader], '');
            }

            if (!in_array($request->method(), $allowed, true)) {
                return new Response(405, [
                    'Allow' => $allowHeader,
                    'Content-Type' => 'text/plain; charset=utf-8',
                ], 'Method Not Allowed');
            }
        }

        if ($this->notFound !== null) {
            ($this->notFound)($request);
            if ($request->isWritten()) {
                return $request->response();
            }
        }

        return new Response(404, self::NOT_FOUND_HEADERS, 'Not Found');
    }

    public function run(): void
    {
        $request = Request::fromGlobals();
        $this->handle($request)->send($request->method() !== 'HEAD');
    }
}
