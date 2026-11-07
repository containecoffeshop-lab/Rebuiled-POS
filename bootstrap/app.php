<?php

use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureModuleAccess;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\ExpireDailySession;
use App\Http\Middleware\RecordAuditEvent;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', ExpireDailySession::class);
        $middleware->alias([
            'active' => EnsureActiveUser::class,
            'audit.write' => RecordAuditEvent::class,
            'role' => EnsureRole::class,
            'module.access' => EnsureModuleAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
