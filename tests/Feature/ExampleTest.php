<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_redirects_to_dashboard_for_guests_and_authenticated_users()
    {
        $this->get(route('home'))->assertRedirect('/dashboard');
        $this->actingAs(User::factory()->create())->get(route('home'))->assertRedirect('/dashboard');
    }
}
