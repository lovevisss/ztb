<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Lovevisss\LaravelZufedfcCas\CasClient;

class SchoolCasLoginController extends Controller
{
    public function __invoke(Request $request, CasClient $cas): RedirectResponse
    {
        $returnUrl = $request->query('returnUrl');
        if (! is_string($returnUrl)
            || ! str_starts_with($returnUrl, '/')
            || str_starts_with($returnUrl, '//')
            || str_contains($returnUrl, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $returnUrl)
            || preg_match('~^/%(?:2f|5c)~i', $returnUrl)) {
            $returnUrl = null;
        }

        return redirect()->away($cas->loginUrl($returnUrl));
    }
}
