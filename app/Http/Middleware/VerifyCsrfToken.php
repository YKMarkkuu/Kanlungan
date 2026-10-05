<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        // TODO(commit D): revisit once sessions exist — these routes have no session dependency yet, so CSRF protects nothing. Re-enable once frontend wires up.
        'auth/*',
    ];
}
