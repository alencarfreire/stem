<?php

declare(strict_types=1);

namespace Stem;

/**
 * Path cursor for the executable routing tree.
 *
 * Segments are stored once; consume*() advances an integer offset (O(1)).
 * Segment count is cached so atEnd()/consumeExact() never call count().
 *
 * @internal
 */
final class Router
{
    private readonly int $count;

    private readonly string $path;

    /**
     * @param list<string> $segments
     */
    public function __construct(
        private array $segments,
        private int $offset = 0,
        ?string $path = null,
    ) {
        $this->count = count($this->segments);
        $this->path = $path ?? ($this->count === 0 ? '/' : '/' . implode('/', $this->segments));
    }

    public static function fromPath(string $path): self
    {
        $qPos = strpos($path, '?');
        if ($qPos !== false) {
            $path = substr($path, 0, $qPos);
        }

        $hashPos = strpos($path, '#');
        if ($hashPos !== false) {
            $path = substr($path, 0, $hashPos);
        }

        if ($path === '' || $path === '/') {
            return new self([], 0, '/');
        }

        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        if (str_ends_with($path, '/')) {
            $path = substr($path, 0, -1);
            if ($path === '') {
                return new self([], 0, '/');
            }
        }

        $needsDecode = str_contains($path, '%');

        if (!str_contains($path, '//')) {
            /** @var list<string> $segments */
            $segments = explode('/', substr($path, 1));
            if ($needsDecode) {
                foreach ($segments as $i => $segment) {
                    $segments[$i] = rawurldecode($segment);
                }

                return new self($segments);
            }

            return new self($segments, 0, $path);
        }

        $segments = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '') {
                continue;
            }

            $segments[] = $needsDecode ? rawurldecode($part) : $part;
        }

        return new self($segments);
    }

    /**
     * Normalized path at offset 0 (`/` or `/a/b`).
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return list<string>
     */
    public function segments(): array
    {
        return $this->segments;
    }

    public function offset(): int
    {
        return $this->offset;
    }

    public function remaining(): string
    {
        if ($this->offset === $this->count) {
            return '';
        }

        if ($this->offset === 0) {
            return $this->path === '/' ? '' : $this->path;
        }

        return '/' . implode('/', array_slice($this->segments, $this->offset));
    }

    public function peek(): ?string
    {
        return $this->offset === $this->count ? null : $this->segments[$this->offset];
    }

    public function atEnd(): bool
    {
        return $this->offset === $this->count;
    }

    public function consume(string $segment): bool
    {
        if ($this->offset === $this->count || $this->segments[$this->offset] !== $segment) {
            return false;
        }

        $this->offset++;

        return true;
    }

    public function isExact(string $segment): bool
    {
        return $this->offset === $this->count - 1 && $this->segments[$this->offset] === $segment;
    }

    public function consumeExact(string $segment): bool
    {
        if (!$this->isExact($segment)) {
            return false;
        }

        $this->offset++;

        return true;
    }

    public function consumeInt(): ?int
    {
        if ($this->offset === $this->count) {
            return null;
        }

        $segment = $this->segments[$this->offset];
        if ($segment === '0') {
            $this->offset++;

            return 0;
        }

        if ($segment === '' || $segment[0] === '0' || !ctype_digit($segment)) {
            return null;
        }

        $int = (int) $segment;
        if ((string) $int !== $segment) {
            return null;
        }

        $this->offset++;

        return $int;
    }

    public function consumeParam(): ?string
    {
        if ($this->offset === $this->count) {
            return null;
        }

        $segment = $this->segments[$this->offset];
        $this->offset++;

        return $segment;
    }

    public function snapshot(): self
    {
        return new self($this->segments, $this->offset, $this->path);
    }
}
