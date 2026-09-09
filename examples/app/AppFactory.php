<?php

declare(strict_types=1);

namespace StemExample;

use Stem\App;
use Stem\Request;
use StemExample\Repository\UserRepository;

final class AppFactory
{
    public static function make(?\PDO $pdo = null): App
    {
        $pdo ??= Database::connect();
        $users = new UserRepository($pdo);

        /** @var callable(UserRepository): (\Closure(Request): void) $makeUsers */
        $makeUsers = require __DIR__ . '/routes/users.php';
        $usersRoutes = $makeUsers($users);

        $app = new App();
        $app->notFound(static fn (Request $r) => $r->json(['error' => 'not_found'], 404));
        $app->route(static function (Request $r) use ($usersRoutes): void {
            $r->run($usersRoutes);
        });

        return $app;
    }
}
