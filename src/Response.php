<?php

declare(strict_types=1);

namespace Stem;

final class Response
{
    public const string CONTENT_TYPE_JSON = 'application/json; charset=utf-8';

    public const string CONTENT_TYPE_HTML = 'text/html; charset=utf-8';

    private const int JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    private bool $sent = false;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private int $status = 200,
        private array $headers = [],
        private string $body = '',
    ) {
        if ($status !== 200) {
            $this->assertStatus($status);
        }

        foreach ($headers as $name => $value) {
            $this->assertHeader($name, $value);
        }
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function sent(): bool
    {
        return $this->sent;
    }

    public function withStatus(int $status): self
    {
        $this->assertStatus($status);
        $this->status = $status;

        return $this;
    }

    public function withHeader(string $name, string $value): self
    {
        $this->assertHeader($name, $value);
        $this->headers[$name] = $value;

        return $this;
    }

    public function withBody(string $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function writeJson(mixed $data, int $status = 200): self
    {
        if ($status !== 200) {
            $this->assertStatus($status);
        }

        $this->status = $status;
        $this->headers['Content-Type'] = self::CONTENT_TYPE_JSON;
        $this->body = json_encode($data, self::JSON_FLAGS);

        return $this;
    }

    public function writeHtml(string $html, int $status = 200): self
    {
        if ($status !== 200) {
            $this->assertStatus($status);
        }

        $this->status = $status;
        $this->headers['Content-Type'] = self::CONTENT_TYPE_HTML;
        $this->body = $html;

        return $this;
    }

    public function send(): void
    {
        if ($this->sent) {
            return;
        }

        $this->sent = true;

        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
        }

        echo $this->body;
    }

    private function assertStatus(int $status): void
    {
        if ($status < 100 || $status > 599) {
            throw new \InvalidArgumentException('HTTP status must be between 100 and 599.');
        }
    }

    private function assertHeader(string $name, string $value): void
    {
        if ($name === '' || strpbrk($name, "\r\n") !== false || strpbrk($value, "\r\n") !== false) {
            throw new \InvalidArgumentException('Invalid HTTP header.');
        }
    }
}
