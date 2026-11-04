<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCasConfigured
{
    public function handle(Request $request, Closure $next): Response
    {
        $service = config('cas.service_url');
        $parts = is_string($service) ? parse_url($service) : false;
        if (! is_array($parts)
            || ($parts['scheme'] ?? null) !== 'https'
            || empty($parts['host'])
            || ($parts['path'] ?? null) !== '/auth/cas/callback'
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])) {
            return response('统一认证回调地址尚未正确配置。', 503);
        }

        return $next($request);
    }
}
