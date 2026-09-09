<?php

declare(strict_types=1);

use Stem\Integrations\FrankenPhpWorker;
use StemExample\AppFactory;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$app = AppFactory::make();

FrankenPhpWorker::run($app, maxRequests: (int) ($_SERVER['MAX_REQUESTS'] ?? 0));
