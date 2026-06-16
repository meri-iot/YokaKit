<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class TraceLog
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param  \Closure(\Illuminate\Http\Request): (Response|\Illuminate\Http\RedirectResponse)  $next
     * @return Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $context = ['method' => $request->method(), 'url' => $request->url(), 'from' => $request->ip()];
        Log::debug('start', $context);
        $response = $next($request);
        Log::debug('end', $context);
        return $response;
    }
}
