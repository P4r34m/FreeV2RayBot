<?php

namespace App\Http\Middleware;

use App\Support\PanelConfig;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns 404 for the whole web panel when it's switched off (from the bot or
 * the server). 404 (not 403) so a disabled panel looks like it doesn't exist.
 */
class EnsureWebPanelEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(PanelConfig::enabled(), 404);

        return $next($request);
    }
}
