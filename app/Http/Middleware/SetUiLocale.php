<?php

namespace App\Http\Middleware;

use App\Domain\Localization\Services\ResolveUiLocale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

final class SetUiLocale
{
    public function __construct(private readonly ResolveUiLocale $locales) {}

    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($this->locales->forRequest($request)->value);

        return $next($request);
    }
}
