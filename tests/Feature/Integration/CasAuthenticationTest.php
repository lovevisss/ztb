<?php

namespace Tests\Feature\Integration;

use App\Auth\CasIdentityResolver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Lovevisss\LaravelZufedfcCas\Data\CasIdentity;
use Lovevisss\LaravelZufedfcCas\Testing\CasResponse;
use Tests\TestCase;

class CasAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('cas.service_url', 'https://ztb.zufedfc.edu.cn/auth/cas/callback');
        config()->set('cas.post_logout_url', 'https://ztb.zufedfc.edu.cn/');
        config()->set('cas.identity_resolver', CasIdentityResolver::class);
    }

    private function schoolUser(string $account = '007', bool $active = true): User
    {
        $department = \Illuminate\Support\Facades\DB::table('departments')->insertGetId([
            'name' => '信息中心', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return User::factory()->create([
            'department_id' => $department,
            'school_account' => $account,
            'is_active' => $active,
        ]);
    }

    public function test_cas_login_redirects_to_school_login_with_exact_registered_service(): void
    {
        $this->get('/auth/cas/login')
            ->assertRedirect()
            ->assertRedirect('https://cas.paas.zufedfc.edu.cn/cas/login?service=https%3A%2F%2Fztb.zufedfc.edu.cn%2Fauth%2Fcas%2Fcallback');
        $this->get('/auth/cas/login?returnUrl=https://other.example/secret')
            ->assertRedirect();
        $this->assertSame('/dashboard', session('cas.return_url'));
        $this->get('/auth/cas/login?returnUrl=//other.example/secret')
            ->assertRedirect();
        $this->assertSame('/dashboard', session('cas.return_url'));
        $this->get('/auth/cas/login?returnUrl=%2F%5Cother.example')
            ->assertRedirect();
        $this->assertSame('/dashboard', session('cas.return_url'));
        $this->get('/auth/cas/login?returnUrl=/procurement/approvals')
            ->assertRedirect();
        $this->assertSame('/procurement/approvals', session('cas.return_url'));
    }

    public function test_cas_login_refuses_an_unregistered_or_insecure_callback_url(): void
    {
        config()->set('cas.service_url', null);
        $this->get('/auth/cas/login')->assertServiceUnavailable();
        config()->set('cas.service_url', 'http://ztb.zufedfc.edu.cn/auth/cas/callback');
        $this->get('/auth/cas/login')->assertServiceUnavailable();
    }

    public function test_exact_active_account_can_log_in_and_ticket_validation_uses_same_service(): void
    {
        $user = $this->schoolUser('007');
        Http::fake(['cas.paas.zufedfc.edu.cn/*' => Http::response(CasResponse::success('007', ['name' => '测试人员']), 200)]);

        $before = session()->getId();
        $this->get('/auth/cas/callback?ticket=ST-test-007')
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($before, session()->getId());
        $this->assertTrue(session('cas.authenticated'));
        $this->get('/dashboard')->assertSuccessful()
            ->assertInertia(fn ($page) => $page->where('auth.casAuthenticated', true));
        Http::assertSent(function ($request) {
            $parts = parse_url($request->url());
            parse_str($parts['query'] ?? '', $query);

            return ($parts['scheme'] ?? null) === 'https'
                && ($parts['host'] ?? null) === 'cas.paas.zufedfc.edu.cn'
                && ($parts['path'] ?? null) === '/cas/serviceValidate'
                && ($query['service'] ?? null) === 'https://ztb.zufedfc.edu.cn/auth/cas/callback'
                && ($query['ticket'] ?? null) === 'ST-test-007';
        });
        $this->assertNotNull(Cache::get('cas:slo:'.hash('sha256', 'ST-test-007')));
        $this->assertNull(Cache::get('cas:slo:ST-test-007'));
    }

    public function test_missing_inactive_and_fuzzy_accounts_are_denied(): void
    {
        $this->schoolUser('007', false);
        $resolver = app(CasIdentityResolver::class);
        $this->assertNull($resolver->resolve(new CasIdentity('007', '007')));
        $this->assertNull($resolver->resolve(new CasIdentity('7', '7')));
        $this->assertNull($resolver->resolve(new CasIdentity('missing', 'missing')));

        Http::fake(['cas.paas.zufedfc.edu.cn/*' => Http::response(CasResponse::success('007'), 200)]);
        $this->get('/auth/cas/callback?ticket=ST-inactive')->assertRedirect('/access-denied');
        $this->assertGuest();
        $this->get('/access-denied')->assertSuccessful();
    }

    public function test_invalid_ticket_and_unsafe_xml_never_authenticate(): void
    {
        $this->schoolUser();
        Http::fake(['cas.paas.zufedfc.edu.cn/*' => Http::response(CasResponse::failure(), 200)]);
        $this->get('/auth/cas/callback?ticket=ST-invalid')->assertUnprocessable();
        $this->assertGuest();

        Http::fake(['cas.paas.zufedfc.edu.cn/*' => Http::response('<!DOCTYPE foo><foo/>', 200)]);
        $this->get('/auth/cas/callback?ticket=ST-unsafe')->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_cas_network_failure_returns_service_unavailable_without_authentication(): void
    {
        $this->schoolUser();
        Http::fake(['cas.paas.zufedfc.edu.cn/*' => Http::failedConnection()]);

        $this->get('/auth/cas/callback?ticket=ST-network-failure')->assertServiceUnavailable();
        $this->assertGuest();
    }

    public function test_local_logout_and_school_single_logout_end_the_session(): void
    {
        $user = $this->schoolUser();
        Http::fake(['cas.paas.zufedfc.edu.cn/*' => Http::response(CasResponse::success('007'), 200)]);
        $this->get('/auth/cas/callback?ticket=ST-logout')->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $xml = '<samlp:LogoutRequest xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"><samlp:SessionIndex>ST-logout</samlp:SessionIndex></samlp:LogoutRequest>';
        $this->post('/auth/cas/slo', ['logoutRequest' => $xml])->assertSuccessful();
        $this->assertNull(Cache::get('cas:slo:'.hash('sha256', 'ST-logout')));

        $this->actingAs($user)->post('/auth/cas/logout')
            ->assertRedirect('https://cas.paas.zufedfc.edu.cn/cas/logout?service=https%3A%2F%2Fztb.zufedfc.edu.cn%2F');
        $this->assertGuest();
        $this->get('/auth/cas/slo?callback=schoolLogout')->assertSuccessful()
            ->assertHeader('content-type', 'application/javascript; charset=UTF-8')
            ->assertSee('schoolLogout(', false);
    }

    public function test_deactivated_person_loses_existing_application_session(): void
    {
        $user = $this->schoolUser();
        Http::fake(['cas.paas.zufedfc.edu.cn/*' => Http::response(CasResponse::success('007'), 200)]);
        $this->get('/auth/cas/callback?ticket=ST-active')->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $user->update(['is_active' => false]);

        $this->get('/dashboard')->assertRedirect('/access-denied');
        $this->assertGuest();
    }
}
