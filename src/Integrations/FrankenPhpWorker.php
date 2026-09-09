<?php

declare(strict_types=1);

namespace Stem\Integrations;

use Stem\App;
use Stem\Request;

/**
 * Optional FrankenPHP worker loop. The core library does not require ext-frankenphp.
 *
 * Boot the App once, then handle each request with a fresh Request from globals.
 * gc_collect_cycles() is off by default — enabling it every request dominates hello-world.
 */
final class FrankenPhpWorker
{
    /**
     * @param int $maxRequests 0 = unlimited
     * @param int $collectEvery collect cycles every N requests; 0 disables
     */
    public static function run(App $app, int $maxRequests = 0, int $collectEvery = 0): void
    {
        if (!function_exists('frankenphp_handle_request')) {
            throw new \RuntimeException('ext-frankenphp is required for FrankenPhpWorker::run().');
        }

        ignore_user_abort(true);

        $handler = static function () use ($app): void {
            $app->handle(Request::fromGlobals())->send();
        };

        /** @var callable(callable(): void): bool $loop */
        $loop = 'frankenphp_handle_request';

        $processed = 0;

        do {
            $keepRunning = $loop($handler);
            $processed++;

            if ($collectEvery > 0 && $processed % $collectEvery === 0) {
                gc_collect_cycles();
            }

            if (!$keepRunning) {
                break;
            }
        } while ($maxRequests === 0 || $processed < $maxRequests);
    }
}
