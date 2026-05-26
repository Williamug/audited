<?php

use Illuminate\Support\Facades\Route;
use Williamug\Audited\Http\Controllers\AuditLogApiController;

Route::middleware(config('audit.api_middleware', ['web', 'auth']))
    ->prefix(config('audit.api_prefix', 'audited/api'))
    ->group(function () {
        Route::get('logs',     [AuditLogApiController::class, 'index'])->name('audited.api.logs');
        Route::get('timeline', [AuditLogApiController::class, 'timeline'])->name('audited.api.timeline');
    });
