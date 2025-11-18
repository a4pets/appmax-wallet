<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UnwrapRequestData
{
    /**
     * Handle an incoming request.
     *
     * Unwraps the 'data' envelope from request body if present.
     * Transforms { "data": { "field": "value" } } to { "field": "value" }
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isJson() && $request->has('data') && is_array($request->input('data'))) {
            $data = $request->input('data');

            $request->replace($data);
        }

        return $next($request);
    }
}
