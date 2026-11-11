<?php

namespace App\Http\Middleware;

use App\Models\ApprovalStep;
use App\Services\OperationRecorder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RecordOperation
{
    private const ACTIONS = [
        'procurement.applications.store' => ['procurement', 'application_created', '创建采购申请'],
        'procurement.applications.update' => ['procurement', 'application_updated', '修改采购申请'],
        'procurement.tenders.store' => ['procurement', 'tender_created', '创建统一招标申请'],
        'procurement.tenders.update' => ['procurement', 'tender_updated', '修改统一招标申请'],
        'procurement.tenders.sections.create' => ['procurement', 'tender_section_created', '创建统一招标草稿'],
        'procurement.tenders.sections.update' => ['procurement', 'tender_section_updated', '保存统一招标模块'],
        'procurement.tenders.sections.submit' => ['procurement', 'tender_submitted', '提交统一招标申请'],
        'procurement.catalog.store' => ['procurement', 'catalog_created', '新增个人采购清单'],
        'procurement.catalog.update' => ['procurement', 'catalog_updated', '修改个人采购清单'],
        'procurement.catalog.delete' => ['procurement', 'catalog_deleted', '删除个人采购清单'],
        'procurement.budgets.sync' => ['procurement', 'budget_synced', '同步预算指标'],
        'procurement.archives.store' => ['procurement', 'archived', '归档采购项目'],
        'procurement.history.copy' => ['procurement', 'application_copied', '复制采购申请'],
        'procurement.executions.save' => ['execution', 'execution_saved', '保存采购执行信息'],
        'procurement.executions.assign' => ['execution', 'executor_assigned', '调整采购执行人'],
        'procurement.approvals.decide' => ['approval', 'decision', '处理采购审批'],
        'procurement.approvals.reassign' => ['approval', 'reassigned', '调整审批人'],
        'procurement.approval-templates.store' => ['configuration', 'approval_template_updated', '保存审批流程配置'],
        'procurement.classification-rules.store' => ['configuration', 'classification_rule_updated', '保存分类规则'],
        'procurement.archive-rules.update' => ['configuration', 'archive_rule_updated', '修改归档规则'],
        'school-identities.update' => ['account', 'identity_updated', '修改校内账号'],
        'school-identities.roles.update' => ['account', 'roles_updated', '修改手动角色'],
        'organization.sync' => ['account', 'organization_synced', '同步组织人员'],
        'organization.position-roles.update' => ['account', 'position_roles_updated', '修改岗位角色'],
        'profile.update' => ['account', 'profile_updated', '修改个人资料'],
        'profile.destroy' => ['account', 'profile_deleted', '删除个人账号'],
        'user-password.update' => ['account', 'password_updated', '修改登录密码'],
    ];

    public function __construct(private readonly OperationRecorder $recorder) {}

    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->user();
        $actorId = $actor?->getAuthIdentifier();
        $actorName = $actor?->name;
        $response = $next($request);
        $definition = self::ACTIONS[$request->route()?->getName() ?? ''] ?? null;
        if ($actorId === null || $definition === null || $response->getStatusCode() >= 400
            || in_array('errors', $request->session()->get('_flash.new', []), true)) {
            return $response;
        }

        [$category, $action, $summary] = $definition;
        $summary = match ($request->route()?->getName()) {
            'procurement.approvals.decide' => match ($request->input('action')) {
                'approve' => '审批通过', 'return' => '退回修改', 'reject' => '驳回申请', default => $summary,
            },
            'procurement.tenders.sections.update', 'procurement.tenders.sections.create' => match ($request->input('section')) {
                'basic' => '保存基本信息', 'items' => '保存采购清单', 'engineering' => '保存工程信息',
                'budget' => '保存预算指标', 'attachments' => '保存附件', default => $summary,
            },
            'procurement.executions.save' => $request->input('intent') === 'complete' ? '完成采购执行' : $summary,
            default => $summary,
        };
        $subject = $request->attributes->get('audit_subject');
        if (! is_array($subject)) {
            foreach (['application', 'step', 'user', 'item', 'code'] as $key) {
                $parameter = $request->route($key);
                if ($parameter !== null) {
                    if ($parameter instanceof ApprovalStep) {
                        $application = DB::table('procurement_applications')->where('id', $parameter->application_id)->first(['id', 'project_number', 'title']);
                        $subject = ['type' => 'application', 'id' => (string) $parameter->application_id,
                            'label' => $application?->project_number ?? $application?->title];
                    } else {
                        $subject = ['type' => $key, 'id' => (string) (is_object($parameter) ? $parameter->getKey() : $parameter),
                            'label' => is_object($parameter) ? ($parameter->project_number ?? $parameter->name ?? null) : null];
                    }
                    break;
                }
            }
        }
        if (! is_array($subject) && (str_starts_with((string) $request->route()?->getName(), 'profile.')
            || $request->route()?->getName() === 'user-password.update')) {
            $subject = ['type' => 'user', 'id' => (string) $actorId, 'label' => $actorName];
        }
        $this->recorder->record((int) $actorId, $actorName, $category, $action, $summary,
            $subject['type'] ?? null, $subject['id'] ?? null, $subject['label'] ?? null);

        return $response;
    }
}
