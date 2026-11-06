<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Lovevisss\LaravelZufedfcCas\CasClient;
use Lovevisss\LaravelZufedfcCas\Contracts\CasIdentityResolver;
use Lovevisss\LaravelZufedfcCas\Events\CasAuthenticated;
use Lovevisss\LaravelZufedfcCas\Events\CasAuthenticationRejected;
use Lovevisss\LaravelZufedfcCas\SessionRegistry;

class SchoolCasCallbackController extends Controller
{
    public function __invoke(Request $request, CasClient $cas, CasIdentityResolver $resolver, SessionRegistry $sessions): RedirectResponse
    {
        $validated = $request->validate(['ticket' => ['required', 'string', 'max:2048']]);
        $identity = $cas->validateTicket($validated['ticket']);
        $user = $resolver->resolve($identity);
        if (! $user instanceof Authenticatable) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            event(new CasAuthenticationRejected($identity));

            return redirect()->route('access-denied');
        }

        Auth::login($user);
        $request->session()->regenerate();
        $sessions->remember($validated['ticket'], $request->session()->getId());
        event(new CasAuthenticated($identity, $user));

        return redirect()->to($request->session()->pull('cas.return_url', (string) config('cas.redirect_after_login', '/dashboard')));
    }
}
