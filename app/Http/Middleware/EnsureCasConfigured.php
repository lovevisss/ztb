<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCasConfigured
{
    public static function isConfigured(): bool
    {
        $service = config('cas.service_url');
        $parts = is_string($service) ? parse_url($service) : false;

        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && ! empty($parts['host'])
            && ($parts['path'] ?? null) === '/auth/cas/callback'
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && ! isset($parts['query'])
            && ! isset($parts['fragment']);
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! self::isConfigured()) {
            return response('统一认证回调地址尚未正确配置。', 503);
        }

        return $next($request);
    }
}
