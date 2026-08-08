<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

class SecureAuthenticationEndpoints
{
    public function __construct(private readonly ThrottleRequests $throttleRequests) {}

    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();

        if (in_array($routeName, ['register', 'register.store'], true)) {
            abort_unless(in_array($request->ip(), ['127.0.0.1', '::1'], true), Response::HTTP_FORBIDDEN);
        }

        $limiter = match ($routeName) {
            'register.store' => 'registration',
            'password.email' => 'password-reset-link',
            'password.update' => 'password-reset',
            'password.confirm.store' => 'password-confirmation',
            default => null,
        };

        if ($limiter === null) {
            return $next($request);
        }

        return $this->throttleRequests->handle($request, $next, $limiter);
    }
}
