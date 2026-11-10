<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureStockOwnership;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role' => EnsureRole::class,
            'owns.stock' => EnsureStockOwnership::class,
        ]);

        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo(
            fn (Request $request) => $request->user()?->isAdmin() ? '/admin/users' : '/stocks'
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();