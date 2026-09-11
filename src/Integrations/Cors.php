<?php

declare(strict_types=1);

namespace Stem\Integrations;

use Stem\Request;

/**
 * Call inside a matched branch. Not a middleware stack.
 */
final class Cors
{
    /**
     * @param list<string> $origins
     * @param list<string> $methods
     * @param list<string> $headers
     */
    public static function allow(
        Request $r,
        array $origins = ['*'],
        array $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        array $headers = ['Content-Type', 'Authorization'],
        bool $credentials = false,
    ): void {
        $origin = $r->header('origin');
        $wildcard = $origins === ['*'];

        if ($origin !== null && !$wildcard && !in_array($origin, $origins, true)) {
            return;
        }

        $allowOrigin = '*';
        if ($origin !== null && ($wildcard || in_array($origin, $origins, true))) {
            $allowOrigin = ($credentials || !$wildcard) ? $origin : '*';
        }

        $response = $r->response();
        $response->withHeader('Access-Control-Allow-Origin', $allowOrigin);
        $response->withHeader('Access-Control-Allow-Methods', implode(', ', $methods));
        $response->withHeader('Access-Control-Allow-Headers', implode(', ', $headers));
        if ($credentials) {
            $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        if ($r->method() === 'OPTIONS') {
            $r->noContent();
        }
    }
}
