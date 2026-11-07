<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk()->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('summary.projectCount', 0)
            ->where('summary.averageApprovalHours', null)
            ->has('monthlyTrend', 0)
            ->has('recentProjects', 0));
    }

    public function test_dashboard_only_summarizes_projects_visible_to_the_user(): void
    {
        $ownDepartment = DB::table('departments')->insertGetId(['name' => '信息中心', 'created_at' => now(), 'updated_at' => now()]);
        $otherDepartment = DB::table('departments')->insertGetId(['name' => '后勤处', 'created_at' => now(), 'updated_at' => now()]);
        $user = User::factory()->create(['department_id' => $ownDepartment]);
        $other = User::factory()->create(['department_id' => $otherDepartment]);

        foreach ([[$user, '本部门设备', 200_000], [$other, '其他部门设备', 900_000]] as [$applicant, $title, $budget]) {
            DB::table('procurement_applications')->insert([
                'applicant_id' => $applicant->id,
                'department_id' => $applicant->department_id,
                'title' => $title,
                'budget_cents' => $budget,
                'application_form' => 'self_purchase',
                'purchase_type' => 'self_purchase',
                'status' => 'submitted',
                'submitted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('summary.projectCount', 1)
                ->where('summary.budgetCents', 200_000)
                ->has('recentProjects', 1)
                ->where('recentProjects.0.title', '本部门设备'));
    }
}
