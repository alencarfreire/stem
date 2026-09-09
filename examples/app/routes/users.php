<?php

declare(strict_types=1);

use Stem\Request;
use StemExample\Repository\UserRepository;

/**
 * @return \Closure(Request): void
 */
return static function (UserRepository $users): \Closure {
    return static function (Request $r) use ($users): void {
        $r->on('users', function () use ($r, $users): void {
            $r->get(fn () => $r->json($users->all()));

            $r->post(function () use ($r, $users): void {
                $body = $r->jsonBody() ?? [];
                $name = $body['name'] ?? '';
                if (!is_string($name) || trim($name) === '') {
                    $r->json(['error' => 'name required'], 422);

                    return;
                }

                $r->json($users->create(trim($name)), 201);
            });

            $r->onInt(function (int $id) use ($r, $users): void {
                $user = $users->find($id);
                if ($user === null) {
                    $r->json(['error' => 'not_found'], 404);

                    return;
                }

                $r->get(fn () => $r->json($user));
                $r->delete(function () use ($r, $users, $id): void {
                    $users->delete($id);
                    $r->noContent();
                });
            });
        });
    };
};
