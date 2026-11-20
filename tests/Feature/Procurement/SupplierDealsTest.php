<?php

namespace Tests\Feature\Procurement;

use App\Models\ProcurementSupplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SupplierDealsTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_counts_matched_projects_once_and_history_includes_all_eligible_deals(): void
    {
        $department = DB::table('departments')->insertGetId(['name' => '信息技术部']);
        $manager = User::factory()->create(['department_id' => $department, 'is_active' => true]);
        DB::table('role_user')->insert([
            'user_id' => $manager->id,
            'role_id' => DB::table('roles')->where('code', 'procurement_manager')->value('id'),
        ]);
        $supplier = ProcurementSupplier::create([
            'name' => '示例设备', 'name_key' => hash('sha256', '示例设备'),
            'contact_name' => '王经理', 'contact_phone' => '0571-12345678',
            'department_id' => $department, 'created_by' => $manager->id, 'is_active' => false,
        ]);
        $network = $this->project($manager, $supplier, '网络改造', '2026-10-01', 'archived');
        $engineering = $this->project($manager, $supplier, '场地改造', '2026-11-01', 'execution_completed');
        DB::table('procurement_engineering_details')->insert(['application_id' => $engineering, 'construction_content' => '实验室墙面维修']);
        foreach (['核心交换机', '接入交换机'] as $index => $name) {
            DB::table('procurement_line_items')->insert([
                'application_id' => $network, 'position' => $index + 1, 'name' => $name,
                'specification' => '高速 10GbE', 'quantity' => 1, 'unit_price_cents' => 1200, 'total_cents' => 1200,
            ]);
        }
        $this->project($manager, $supplier, '未完成交换机', '2026-11-10', 'executing', executionStatus: 'draft');
        $this->project($manager, $supplier, '统一招标交换机', '2026-11-11', 'execution_completed', 'central_tender');
        $this->project($manager, null, '未关联交换机', '2026-11-12', 'execution_completed');

        $this->actingAs($manager)->get(route('procurement.supplier-deals.index', ['keyword' => '10gbe']))
            ->assertOk()->assertInertia(fn ($page) => $page->component('procurement/SupplierDeals')
            ->where('suppliers.total', 1)
            ->where('suppliers.data.0.name', $supplier->name)
            ->where('suppliers.data.0.contact_phone', $supplier->contact_phone)
            ->where('suppliers.data.0.is_active', false)
            ->where('suppliers.data.0.matched_count', 1)
            ->where('suppliers.data.0.project_count', 2)
            ->where('suppliers.data.0.contents.0', '核心交换机 · 高速 10GbE'));
        $this->get(route('procurement.supplier-deals.index', ['keyword' => '交换机', 'status' => 'active']))
            ->assertInertia(fn ($page) => $page->where('suppliers.total', 0));
        $this->get(route('procurement.supplier-deals.index', ['keyword' => '墙面', 'supplier' => '示例', 'status' => 'inactive']))
            ->assertInertia(fn ($page) => $page->where('suppliers.data.0.contents.0', '实验室墙面维修'));
        $this->get(route('procurement.supplier-deals.index', ['keyword' => '%']))
            ->assertInertia(fn ($page) => $page->where('suppliers.total', 0));
        $this->get(route('procurement.supplier-deals.show', ['supplier' => $supplier, 'keyword' => '交换机']))
            ->assertOk()->assertInertia(fn ($page) => $page->component('procurement/SupplierDealHistory')
            ->where('projects.total', 2)->where('projects.data.0.id', $engineering)
            ->where('projects.data.0.actual_amount_cents', 5000)
            ->where('projects.data.1.id', $network)->where('projects.data.1.status', 'archived')
            ->has('projects.data.1.items', 2));

        $applicant = User::factory()->create(['department_id' => $department, 'is_active' => true]);
        $this->actingAs($applicant)->get(route('procurement.supplier-deals.index'))->assertForbidden();
        $this->get(route('procurement.supplier-deals.show', $supplier))->assertForbidden();
        $this->get(route('procurement.suppliers.index'))->assertOk();
    }

    private function project(User $user, ?ProcurementSupplier $supplier, string $title, string $date, string $status, string $form = 'self_purchase', string $executionStatus = 'completed'): int
    {
        $id = DB::table('procurement_applications')->insertGetId([
            'applicant_id' => $user->id, 'department_id' => $user->department_id,
            'title' => $title, 'application_form' => $form, 'status' => $status,
        ]);
        DB::table('procurement_executions')->insert([
            'application_id' => $id, 'supplier_id' => $supplier?->id, 'supplier' => '登记时供应商名称',
            'completed_on' => $date, 'actual_amount_cents' => 5000, 'status' => $executionStatus,
        ]);

        return $id;
    }
}
