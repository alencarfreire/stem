<?php

declare(strict_types=1);

namespace Stem\Exceptions;

use Stem\Response;

/**
 * Control-flow exception used only by Request::halt() for early exits.
 * The happy path (json/html/matched routes) does not throw.
 */
final class HaltException extends \RuntimeException
{
    public function __construct(private readonly Response $response)
    {
        parent::__construct('Halted', $response->status());
    }

    public function response(): Response
    {
        return $this->response;
    }
}
