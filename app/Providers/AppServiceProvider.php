<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Lovevisss\LaravelZufedfcCas\Events\CasAuthenticated;
use Lovevisss\LaravelZufedfcCas\Events\CasAuthenticationRejected;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        Event::listen(CasAuthenticated::class, function (CasAuthenticated $event): void {
            session(['cas.authenticated' => true]);
            DB::table('cas_auth_events')->insert([
                'user_id' => $event->user->getAuthIdentifier(),
                'account_hash' => hash('sha256', $event->identity->account),
                'outcome' => 'authenticated', 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
        Event::listen(CasAuthenticationRejected::class, function (CasAuthenticationRejected $event): void {
            DB::table('cas_auth_events')->insert([
                'user_id' => null,
                'account_hash' => hash('sha256', $event->identity->account),
                'outcome' => 'rejected', 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
