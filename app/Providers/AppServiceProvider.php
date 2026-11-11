<?php

namespace App\Providers;

use App\Services\OperationRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
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
        Event::listen(Login::class, function (Login $event): void {
            if (request()->route()?->getName() === 'cas.callback') {
                return;
            }
            app(OperationRecorder::class)->record((int) $event->user->getAuthIdentifier(), $event->user->name,
                'login', 'local_authenticated', '本地账号登录成功', 'user', (string) $event->user->getAuthIdentifier(), $event->user->name);
        });
        Event::listen(Failed::class, function (Failed $event): void {
            $identifier = $event->credentials['email'] ?? null;
            app(OperationRecorder::class)->record(null, null, 'login', 'local_failed', '本地账号登录失败',
                'account', is_string($identifier) ? hash('sha256', mb_strtolower($identifier)) : null,
                null, 'failure');
        });
        Event::listen(CasAuthenticated::class, function (CasAuthenticated $event): void {
            session(['cas.authenticated' => true]);
            DB::table('cas_auth_events')->insert([
                'user_id' => $event->user->getAuthIdentifier(),
                'account_hash' => hash('sha256', $event->identity->account),
                'outcome' => 'authenticated', 'created_at' => now(), 'updated_at' => now(),
            ]);
            app(OperationRecorder::class)->record((int) $event->user->getAuthIdentifier(), $event->user->name,
                'login', 'cas_authenticated', '统一认证登录成功', 'user', (string) $event->user->getAuthIdentifier(), $event->user->name);
        });
        Event::listen(CasAuthenticationRejected::class, function (CasAuthenticationRejected $event): void {
            DB::table('cas_auth_events')->insert([
                'user_id' => null,
                'account_hash' => hash('sha256', $event->identity->account),
                'outcome' => 'rejected', 'created_at' => now(), 'updated_at' => now(),
            ]);
            app(OperationRecorder::class)->record(null, null, 'login', 'cas_rejected', '统一认证登录未通过',
                'account', hash('sha256', $event->identity->account), null, 'failure');
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
