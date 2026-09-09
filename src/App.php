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
        }

        if ($request->isDone()) {
            return $request->response();
        }

        return new Response(404, self::NOT_FOUND_HEADERS, 'Not Found');
    }

    public function run(): void
    {
        $this->handle(Request::fromGlobals())->send();
    }
}
