<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import WorkflowDiagram from './WorkflowDiagram.vue';
import { canPreviewAttachment } from '@/lib/procurement-attachment';

type FlowNode = { key: string; label: string; state: 'done' | 'active' | 'waiting' | 'stopped'; time?: string | null; timeLabel?: string | null; person?: string | null; personLabel?: string | null; result?: string | null; note?: string | null };

type Application = {
    id: number;
    applicant_id: number;
    executor_id: number | null;
    title: string;
    project_number: string;
    project_leader: string | null;
    budget_cents: number;
    purchase_type: string;
    application_form: string;
    status: string;
    funding_source: string;
    organization_form: string;
    purchase_method: string;
    reason: string | null;
    responsible_department: string;
    procurement_requirements: string | null;
    purchase_list: string | null;
    technical_parameters: string | null;
    service_requirements: string | null;
    acceptance_criteria: string | null;
    budget_basis_note: string | null;
    category: string | null;
    contact_phone: string | null;
    is_special_purchase: boolean;
    special_purchase_reason: string | null;
    is_non_expenditure: boolean;
    remarks: string | null;
    submitted_at: string | null;
};
type Step = {
    id: number;
    attempt: number;
    position: number;
    role_code: string;
    assignee_id: number;
    status: string;
    opinion: string | null;
    acted_at: string | null;
    assignee_name: string | null;
};
type Event = {
    id: number;
    event: string;
    details: string | null;
    created_at: string;
    actor_name: string | null;
};
type Attachment = { id: number; kind: string; original_name: string; mime_type: string | null };
type Engineering = { project_name: string | null; construction_content: string | null; project_overview: string | null; schedule_requirements: string | null; estimated_cost_cents: number | null };
type User = { id: number; name: string };

const props = defineProps<{
    application: Application;
    applicantName: string | null;
    applicantDepartmentName: string | null;
    sourceApplication: {
        id: number;
        project_number: string;
        title: string;
        canView: boolean;
    } | null;
    steps: Step[];
    events: Event[];
    attachments: Attachment[];
    lineItems: { id: number; name: string; specification: string | null; quantity: number; unit: string | null; unit_price_cents: number; total_cents: number; remarks: string | null }[];
    engineering: Engineering | null;
    budgetAllocations: { id: number; code: string | null; name: string; director: string | null; amount_cents: number }[];
    usageDepartmentName: string | null;
    execution: {
        actual_amount_cents: number | null;
        supplier: string | null;
        status: string;
    } | null;
    canArchive: boolean;
    missingArchiveMaterials: string[];
    currentUserId: number;
    canManageApprovals: boolean;
    users: User[];
    workflowReturnUrl: string | null;
}>();
const decision = useForm({ action: 'approve', opinion: '' });
type AssigneeSuggestion = { value: string; id: number };
const selectedAssignees = reactive<Record<number, number | null>>(Object.fromEntries(
    props.steps.filter((step) => ['pending', 'queued'].includes(step.status)).map((step) => [step.id, step.assignee_id]),
));
const assigneeInputs = reactive<Record<number, string>>(Object.fromEntries(
    props.steps.filter((step) => ['pending', 'queued'].includes(step.status)).map((step) => [step.id, step.assignee_name ?? '']),
));
const reassignmentErrors = reactive<Record<number, string>>({});
const reassigning = reactive<Record<number, boolean>>({});
const executorAssignment = useForm({
    executor_id: props.application.executor_id ?? ('' as number | ''),
});
const archiving = useForm({ materials: '' });
const withdrawing = useForm({});

function withdraw() {
    if (window.confirm('撤回后本轮审批停止，重新提交将从第一个环节重新审核。确定撤回吗？')) {
        withdrawing.post(`/procurement/applications/${props.application.id}/withdraw`);
    }
}

function decide(stepId: number, action: 'approve' | 'return' | 'reject') {
    decision.action = action;
    decision.post(`/procurement/approvals/${stepId}/decision`, {
        onSuccess: () => decision.reset('opinion'),
    });
}

function reassign(stepId: number) {
    reassigning[stepId] = true;
    delete reassignmentErrors[stepId];
    router.put(`/procurement/approvals/${stepId}/assignee${props.workflowReturnUrl ? window.location.search : ''}`, { assignee_id: selectedAssignees[stepId] }, {
        preserveScroll: true,
        onError: (errors) => { reassignmentErrors[stepId] = errors.assignee_id ?? '更新失败，请重试'; },
        onFinish: () => { reassigning[stepId] = false; },
    });
}

function searchAssignees(query: string, callback: (suggestions: AssigneeSuggestion[]) => void) {
    const term = query.trim().toLocaleLowerCase();
    callback(term ? props.users.filter((user) => user.name.toLocaleLowerCase().includes(term))
        .map((user) => ({ value: user.name, id: user.id })) : []);
}

function editAssignee(stepId: number, name: string) {
    assigneeInputs[stepId] = name;
    selectedAssignees[stepId] = null;
    delete reassignmentErrors[stepId];
}

function selectAssignee(stepId: number, suggestion: AssigneeSuggestion) {
    assigneeInputs[stepId] = suggestion.value;
    selectedAssignees[stepId] = suggestion.id;
}

function assignExecutor() {
    executorAssignment.put(
        `/procurement/applications/${props.application.id}/executor`,
    );
}

function archiveProject() {
    archiving.post(`/procurement/applications/${props.application.id}/archive`);
}

const archiveKindLabel: Record<string, string> = {
    contract: '合同',
    result_announcement: '结果公告',
    acceptance: '验收材料',
    invoice: '发票',
    other_execution: '其他材料',
};
const attachmentKindLabel: Record<string, string> = {
    engineering_bill: '工程清单', demand_document: '采购需求',
    budget_basis_document: '预算依据', other: '其他附件',
    ...archiveKindLabel,
};

const roleLabel: Record<string, string> = {
    department_head: '部门负责人',
    usage_department_head: '使用部门负责人',
    procurement_manager: '采购管理部门',
    responsible_department: '归口部门',
    finance: '财务人员',
    school_leader: '校领导',
};
const statusLabel: Record<string, string> = {
    draft: '草稿',
    submitted: '审批中',
    queued: '待流转',
    pending: '待审批',
    approved: '已通过',
    executing: '执行中',
    execution_completed: '执行已完成',
    archived: '已归档',
    returned: '退回修改',
    withdrawn: '已撤回待修改',
    rejected: '已驳回',
    cancelled: '已取消',
};
const eventLabel: Record<string, string> = {
    submitted: '提交申请', resubmitted: '重新提交', approved: '审批通过',
    returned: '退回修改', withdrawn: '经办人撤回', rejected: '驳回申请', reassigned: '调整审批人',
};
const editHref = computed(() => ['returned', 'withdrawn'].includes(props.application.status) && props.application.applicant_id === props.currentUserId
    ? props.application.application_form === 'central_tender'
        ? `/procurement/tenders/${props.application.id}/edit`
        : `/procurement/applications/${props.application.id}/edit`
    : null);
const latestReturn = computed(() => [...props.steps].reverse().find((step) => step.status === 'returned') ?? null);
const flowRounds = computed(() => {
    const attempts = [...new Set(props.steps.map((step) => step.attempt))];
    const submissions = props.events.filter((event) => ['submitted', 'resubmitted'].includes(event.event));
    if (!attempts.length) {
        return [{ attempt: 0, nodes: [{ key: 'draft', label: '待提交', state: 'active' as const, time: null, result: '尚未提交' }] }];
    }
    return attempts.map((attempt, index) => {
        const steps = props.steps.filter((step) => step.attempt === attempt);
        const terminal = steps.find((step) => ['returned', 'rejected'].includes(step.status));
        const submissionEventIndex = props.events.findIndex((event) => event.id === submissions[index]?.id);
        const nextSubmissionEventIndex = props.events.findIndex((event, eventIndex) => eventIndex > submissionEventIndex && ['submitted', 'resubmitted'].includes(event.event));
        const withdrawn = props.events.slice(submissionEventIndex + 1, nextSubmissionEventIndex < 0 ? undefined : nextSubmissionEventIndex)
            .find((event) => event.event === 'withdrawn');
        const last = steps.at(-1);
        const complete = steps.length > 0 && steps.every((step) => step.status === 'approved');
        const nodes: FlowNode[] = [{
            key: `submit-${attempt}`, label: index ? '重新提交' : '提交', state: 'done',
            time: submissions[index]?.created_at ?? null, timeLabel: '提交时间',
            person: props.applicantName ?? '未指定', personLabel: '申请人', result: '已提交',
        }];
        for (const step of steps) nodes.push({
            key: String(step.id), label: roleLabel[step.role_code] ?? step.role_code,
            state: step.status === 'approved' ? 'done' : step.status === 'pending' ? 'active' : ['returned', 'rejected'].includes(step.status) ? 'stopped' : 'waiting',
            time: step.acted_at, timeLabel: '处理时间', person: step.assignee_name ?? '未指定', personLabel: '审批人',
            result: step.status === 'cancelled' ? '未进入（流程终止）' : statusLabel[step.status] ?? step.status,
            note: step.opinion,
        });
        nodes.push({
            key: `end-${attempt}`, label: terminal ? statusLabel[terminal.status] ?? terminal.status : withdrawn ? '已撤回' : '完成',
            state: terminal || withdrawn ? 'stopped' : complete ? 'done' : 'waiting',
            time: terminal?.acted_at ?? withdrawn?.created_at ?? (complete ? last?.acted_at : null),
            timeLabel: '结束时间',
        });
        return { attempt, nodes };
    });
});
const visibleAttachments = computed(() => props.attachments.filter((item) =>
    props.application.category === '工程类' || item.kind !== 'engineering_bill',
));
const money = (cents: number | null | undefined) => cents == null ? '—' : `¥${(cents / 100).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const display = (value: string | null | undefined) => value?.trim() || '—';
const basicFields = computed(() => [
    ['项目编号', display(props.application.project_number)],
    ['申报项目', display(props.application.title)],
    ['项目经办人', display(props.applicantName)],
    ['经办人电话', display(props.application.contact_phone)],
    ['经办人所在部门', display(props.applicantDepartmentName)],
    ['项目预算', money(props.application.budget_cents)],
    ['归属', display(props.application.category)],
    ...(props.application.organization_form?.trim() ? [['组织形式', props.application.organization_form]] : []),
    ...(props.application.funding_source?.trim() ? [['经费列支', props.application.funding_source]] : []),
    ...(props.application.responsible_department?.trim() ? [['归口部门', props.application.responsible_department]] : []),
    ['拟采购方式', display(props.application.purchase_method)],
    ['是否特殊采购', props.application.is_special_purchase ? '是' : '否'],
    ['使用部门', display(props.usageDepartmentName)],
    ['是否为非支出项目', props.application.is_non_expenditure ? '是' : '否'],
    ...(props.application.project_leader?.trim() ? [['项目负责人', props.application.project_leader]] : []),
    ['申报时间', props.application.submitted_at?.slice(0, 16).replace('T', ' ') || '—'],
]);
const longFields = computed(() => [
    ['申请特殊采购方式理由', props.application.special_purchase_reason],
    ['采购要求', props.application.procurement_requirements],
    ['采购清单说明', props.application.purchase_list],
    ['采购理由', props.application.reason],
    ['技术参数', props.application.technical_parameters],
    ['服务要求', props.application.service_requirements],
    ['验收标准', props.application.acceptance_criteria],
    ['预算测算说明', props.application.budget_basis_note],
    ['备注', props.application.remarks],
].filter((entry): entry is [string, string] => !!entry[1]?.trim()));
</script>

<template>
    <Head :title="application.title" />
    <main
        class="procurement-detail-page w-full min-w-0 px-[var(--brand-space-24)] py-[var(--brand-space-32)] md:px-[var(--brand-space-32)] md:py-[var(--brand-space-32)]"
    >
        <Link
            :href="workflowReturnUrl ?? (currentUserId === application.applicant_id ? '/procurement/applications' : '/procurement/approvals')"
            class="text-sm text-[var(--brand-primary)] hover:underline"
            >← 返回{{ workflowReturnUrl ? '流程进度' : currentUserId === application.applicant_id ? '申请列表' : '待办' }}</Link
        >
        <header
            class="mt-[var(--brand-space-32)] flex flex-wrap items-end justify-between gap-[var(--brand-space-16)] border-b border-[var(--brand-border)] pb-[var(--brand-space-32)]"
        >
            <div>
                <p class="font-mono text-xs text-[var(--brand-primary)]">
                    {{ application.project_number }}
                </p>
                <h1
                    class="mt-[var(--brand-space-8)] font-sans text-3xl font-semibold text-[var(--brand-title)]"
                >
                    {{ application.title }}
                </h1>
                <p
                    class="mt-[var(--brand-space-16)] text-sm text-[var(--brand-muted)]"
                >
                    {{
                        application.application_form === 'central_tender'
                            ? '统一招标'
                            : '部门自行采购'
                    }}
                    ·
                    {{ statusLabel[application.status] ?? application.status }}
                </p>
            </div>
            <p class="font-sans text-2xl text-[var(--brand-title)]">
                ¥{{ (application.budget_cents / 100).toLocaleString('zh-CN') }}
            </p>
        </header>

        <section v-if="application.status === 'submitted' && application.applicant_id === currentUserId" class="mt-[var(--brand-space-24)] flex flex-wrap items-center justify-between gap-[var(--brand-space-16)] rounded-[var(--brand-radius-lg)] border border-[var(--brand-warning)] bg-[var(--brand-soft)] p-[var(--brand-space-24)]">
            <div><h2 class="text-lg font-semibold text-[var(--brand-title)]">需要修改申请？</h2><p class="mt-[var(--brand-space-8)] text-sm text-[var(--brand-body)]">撤回后当前审批停止，重新提交将从首环节重新审核。</p></div>
            <button type="button" :disabled="withdrawing.processing" class="inline-flex min-h-10 items-center rounded-[var(--brand-radius-md)] border border-[var(--brand-warning)] px-[var(--brand-space-24)] text-sm font-semibold text-[var(--brand-warning)] disabled:opacity-50" @click="withdraw">撤回修改</button>
        </section>
        <section v-if="editHref" class="mt-[var(--brand-space-24)] flex flex-wrap items-center justify-between gap-[var(--brand-space-16)] rounded-[var(--brand-radius-lg)] border border-[var(--brand-warning)] bg-[var(--brand-soft)] p-[var(--brand-space-24)]">
            <div>
                <h2 class="text-lg font-semibold text-[var(--brand-title)]">{{ application.status === 'withdrawn' ? '申请已撤回，等待修改后重新提交' : '申请已退回，等待修改后重新提交' }}</h2>
                <p v-if="application.status === 'returned'" class="mt-[var(--brand-space-8)] text-sm text-[var(--brand-body)]">退回审批人：{{ latestReturn?.assignee_name || '未指定' }}<span v-if="latestReturn?.opinion"> · 意见：{{ latestReturn.opinion }}</span></p>
            </div>
            <Link :href="editHref" class="inline-flex min-h-10 items-center rounded-[var(--brand-radius-md)] bg-[var(--brand-primary)] px-[var(--brand-space-24)] text-sm font-semibold text-[var(--brand-on-primary)] hover:bg-[var(--brand-primary-hover)]">修改申请</Link>
        </section>

        <div
            v-if="sourceApplication"
            class="mt-[var(--brand-space-24)] rounded-[var(--brand-radius-md)] border border-[var(--brand-primary)] bg-[var(--brand-primary-light)] px-[var(--brand-space-24)] py-[var(--brand-space-16)] text-sm text-[var(--brand-primary)]"
        >
            来源项目：<Link
                v-if="sourceApplication.canView"
                :href="`/procurement/applications/${sourceApplication.id}`"
                class="font-medium underline"
                >{{ sourceApplication.project_number }} ·
                {{ sourceApplication.title }}</Link
            ><span v-else
                >{{ sourceApplication.project_number }} ·
                {{ sourceApplication.title }}</span
            >
        </div>

        <div class="mt-[var(--brand-space-32)] space-y-[var(--brand-space-16)]">
            <WorkflowDiagram v-for="round in flowRounds" :key="round.attempt" :title="round.attempt ? `第 ${round.attempt} 轮审批流程` : '申请流程'" :nodes="round.nodes" />
        </div>

        <div
            class="mt-[var(--brand-space-32)] space-y-[var(--brand-space-32)]"
        >
            <div class="space-y-[var(--brand-space-32)]">
                <section class="rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)] shadow-sm">
                    <h2 class="border-b border-[var(--brand-border)] pb-[var(--brand-space-16)] text-xl font-semibold text-[var(--brand-title)]">申请材料</h2>
                    <ul v-if="visibleAttachments.length" class="mt-[var(--brand-space-16)] divide-y divide-[var(--brand-border)]">
                        <li v-for="file in visibleAttachments" :key="file.id" class="flex flex-wrap items-center justify-between gap-[var(--brand-space-16)] py-[var(--brand-space-16)] text-sm">
                            <span class="min-w-0 break-all text-[var(--brand-body)]"><span class="mr-3 text-[var(--brand-muted)]">{{ attachmentKindLabel[file.kind] ?? file.kind }}</span>{{ file.original_name }}</span>
                            <span class="flex shrink-0 items-center gap-[var(--brand-space-16)]">
                                <a v-if="canPreviewAttachment(file.mime_type)" :href="`/procurement/attachments/${file.id}/preview`" target="_blank" rel="noopener noreferrer" class="font-medium text-[var(--brand-primary)] hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--brand-primary)]">预览</a>
                                <a :href="`/procurement/attachments/${file.id}/download`" class="font-medium text-[var(--brand-primary)] hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--brand-primary)]">下载</a>
                            </span>
                        </li>
                    </ul>
                    <p v-else class="pt-[var(--brand-space-16)] text-sm text-[var(--brand-muted)]">暂无上传材料</p>
                </section>

                <section class="rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)] shadow-sm">
                    <h2 class="border-b border-[var(--brand-border)] pb-[var(--brand-space-16)] text-xl font-semibold text-[var(--brand-title)]">基本信息</h2>
                    <dl class="mt-[var(--brand-space-16)] grid overflow-hidden rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] sm:grid-cols-2 xl:grid-cols-3">
                        <div v-for="[label, value] in basicFields" :key="label" class="grid min-w-0 grid-cols-[minmax(100px,36%)_minmax(0,1fr)] border-b border-r border-[var(--brand-border)] text-sm last:border-b-0">
                            <dt class="bg-[var(--brand-soft)] px-[var(--brand-space-16)] py-[var(--brand-space-16)] text-[var(--brand-muted)]">{{ label }}</dt>
                            <dd class="min-w-0 break-words px-[var(--brand-space-16)] py-[var(--brand-space-16)] text-[var(--brand-title)]">{{ value }}</dd>
                        </div>
                    </dl>
                    <dl v-if="longFields.length" class="mt-[var(--brand-space-16)] overflow-hidden rounded-[var(--brand-radius-md)] border border-[var(--brand-border)]">
                        <div v-for="[label, value] in longFields" :key="label" class="grid border-b border-[var(--brand-border)] text-sm last:border-b-0 sm:grid-cols-[180px_minmax(0,1fr)]">
                            <dt class="bg-[var(--brand-soft)] px-[var(--brand-space-16)] py-[var(--brand-space-16)] text-[var(--brand-muted)]">{{ label }}</dt>
                            <dd class="min-w-0 whitespace-pre-wrap break-words px-[var(--brand-space-16)] py-[var(--brand-space-16)] leading-7 text-[var(--brand-body)]">{{ value }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)] shadow-sm">
                    <h2 class="border-b border-[var(--brand-border)] pb-[var(--brand-space-16)] text-xl font-semibold text-[var(--brand-title)]">采购清单</h2>
                    <dl v-if="application.category === '工程类' && engineering" class="mt-[var(--brand-space-16)] overflow-hidden rounded-[var(--brand-radius-md)] border border-[var(--brand-border)]">
                        <div v-for="[label, value] in [['工程名称', engineering.project_name], ['项目建设内容', engineering.construction_content], ['项目概况', engineering.project_overview], ['项目工期要求', engineering.schedule_requirements], ['工程总造价', money(engineering.estimated_cost_cents)]]" :key="label" class="grid border-b border-[var(--brand-border)] text-sm last:border-b-0 sm:grid-cols-[180px_minmax(0,1fr)]">
                            <dt class="bg-[var(--brand-soft)] px-[var(--brand-space-16)] py-[var(--brand-space-16)] text-[var(--brand-muted)]">{{ label }}</dt>
                            <dd class="whitespace-pre-wrap break-words px-[var(--brand-space-16)] py-[var(--brand-space-16)] leading-7 text-[var(--brand-title)]">{{ value || '—' }}</dd>
                        </div>
                    </dl>
                    <div v-else-if="application.category !== '工程类'" class="mt-[var(--brand-space-16)] overflow-x-auto rounded-[var(--brand-radius-md)] border border-[var(--brand-border)]">
                        <table class="w-full min-w-[900px] border-collapse text-left text-sm">
                            <thead class="bg-[var(--brand-soft)] text-[var(--brand-body)]"><tr><th class="p-3">序号</th><th class="p-3">名称</th><th class="p-3">技术参数</th><th class="p-3">数量</th><th class="p-3">单位</th><th class="p-3">单价（元）</th><th class="p-3">总价（元）</th><th class="p-3">备注</th></tr></thead>
                            <tbody class="divide-y divide-[var(--brand-border)] text-[var(--brand-body)]"><tr v-for="(item, index) in lineItems" :key="item.id" class="hover:bg-[var(--brand-primary-light)]"><td class="p-3">{{ index + 1 }}</td><td class="p-3">{{ item.name }}</td><td class="p-3 whitespace-pre-wrap">{{ item.specification || '—' }}</td><td class="p-3 tabular-nums">{{ item.quantity }}</td><td class="p-3">{{ item.unit || '—' }}</td><td class="p-3 whitespace-nowrap tabular-nums">{{ money(item.unit_price_cents) }}</td><td class="p-3 whitespace-nowrap tabular-nums">{{ money(item.total_cents) }}</td><td class="p-3">{{ item.remarks || '—' }}</td></tr></tbody>
                            <tfoot class="border-t border-[var(--brand-border)] bg-[var(--brand-soft)] font-semibold text-[var(--brand-title)]"><tr><td colspan="6" class="p-3 text-right">合计</td><td class="whitespace-nowrap p-3 tabular-nums">{{ money(lineItems.reduce((sum, item) => sum + item.total_cents, 0)) }}</td><td></td></tr></tfoot>
                        </table>
                        <p v-if="!lineItems.length" class="p-4 text-sm text-[var(--brand-muted)]">暂无结构化采购清单</p>
                    </div>
                    <p v-else class="pt-[var(--brand-space-16)] text-sm text-[var(--brand-muted)]">暂无工程清单信息</p>
                </section>

                <section class="rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)] shadow-sm">
                    <h2 class="border-b border-[var(--brand-border)] pb-[var(--brand-space-16)] text-xl font-semibold text-[var(--brand-title)]">项目预算指标</h2>
                    <div class="mt-[var(--brand-space-16)] overflow-x-auto rounded-[var(--brand-radius-md)] border border-[var(--brand-border)]">
                        <table class="w-full min-w-[640px] border-collapse text-left text-sm">
                            <thead class="bg-[var(--brand-soft)] text-[var(--brand-body)]"><tr><th class="p-3">预算代码</th><th class="p-3">经费名称</th><th class="p-3">负责人</th><th class="p-3">使用金额（元）</th></tr></thead>
                            <tbody class="divide-y divide-[var(--brand-border)] text-[var(--brand-body)]"><tr v-for="allocation in budgetAllocations" :key="allocation.id"><td class="p-3">{{ allocation.code || '—' }}</td><td class="p-3">{{ allocation.name }}</td><td class="p-3">{{ allocation.director || '—' }}</td><td class="whitespace-nowrap p-3 tabular-nums">{{ money(allocation.amount_cents) }}</td></tr></tbody>
                            <tfoot v-if="budgetAllocations.length" class="border-t border-[var(--brand-border)] bg-[var(--brand-soft)] font-semibold text-[var(--brand-title)]"><tr><td colspan="3" class="p-3 text-right">合计</td><td class="whitespace-nowrap p-3 tabular-nums">{{ money(budgetAllocations.reduce((sum, allocation) => sum + allocation.amount_cents, 0)) }}</td></tr></tfoot>
                        </table>
                        <p v-if="!budgetAllocations.length" class="p-4 text-sm text-[var(--brand-muted)]">暂无预算指标记录</p>
                    </div>
                </section>

                <section
                    class="rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)] shadow-sm"
                >
                    <h2
                        class="font-sans text-xl font-semibold text-[var(--brand-title)]"
                    >
                        审批记录
                    </h2>
                    <ol
                        class="mt-[var(--brand-space-24)] space-y-[var(--brand-space-24)] border-l border-[var(--brand-border)] pl-[var(--brand-space-24)]"
                    >
                        <li
                            v-for="step in steps"
                            :key="step.id"
                            class="relative text-sm"
                        >
                            <span
                                class="absolute top-1 -left-[25px] size-2 rounded-full bg-[var(--brand-primary)]"
                            />
                            <p class="font-medium text-[var(--brand-title)]">
                                第 {{ step.attempt }} 轮 ·
                                {{
                                    roleLabel[step.role_code] ?? step.role_code
                                }}
                                · {{ statusLabel[step.status] ?? step.status }}
                            </p>
                            <p
                                v-if="step.opinion"
                                class="mt-[var(--brand-space-8)] whitespace-pre-wrap text-[var(--brand-body)]"
                            >
                                {{ step.opinion }}
                            </p>
                            <p
                                v-if="step.acted_at"
                                class="mt-[var(--brand-space-8)] text-xs text-[var(--brand-muted)]"
                            >
                                {{
                                    step.acted_at.slice(0, 16).replace('T', ' ')
                                }}
                            </p>
                        </li>
                    </ol>
                </section>
            </div>

            <aside class="grid items-start gap-[var(--brand-space-24)] lg:grid-cols-2 xl:grid-cols-3">
                <section
                    v-if="
                        [
                            'approved',
                            'executing',
                            'execution_completed',
                            'archived',
                        ].includes(application.status)
                    "
                    class="rounded-[var(--brand-radius-md)] border border-[var(--brand-primary)] bg-[var(--brand-primary-light)] p-[var(--brand-space-24)]"
                >
                    <h2 class="font-sans text-lg text-[var(--brand-title)]">
                        采购执行
                    </h2>
                    <p
                        v-if="execution?.supplier"
                        class="mt-[var(--brand-space-16)] text-sm text-[var(--brand-body)]"
                    >
                        供应商：{{ execution.supplier }}
                    </p>
                    <p
                        v-if="execution?.actual_amount_cents != null"
                        class="mt-[var(--brand-space-8)] text-sm text-[var(--brand-body)]"
                    >
                        实际金额：¥{{
                            (
                                execution.actual_amount_cents / 100
                            ).toLocaleString('zh-CN')
                        }}
                    </p>
                    <Link
                        :href="`/procurement/applications/${application.id}/execution`"
                        class="mt-[var(--brand-space-16)] inline-block text-sm font-semibold text-[var(--brand-primary)] underline"
                        >查看执行登记 →</Link
                    >
                    <div
                        v-if="
                            canManageApprovals &&
                            application.status !== 'archived'
                        "
                        class="mt-[var(--brand-space-24)] border-t border-[var(--brand-primary)] pt-[var(--brand-space-16)]"
                    >
                        <label class="text-sm text-[var(--brand-body)]"
                            >指定执行人</label
                        >
                        <el-select
                            v-model="executorAssignment.executor_id"
                            filterable
                            placeholder="选择执行人"
                            class="mt-[var(--brand-space-8)] w-full"
                        >
                            <el-option
                                v-for="user in users"
                                :key="user.id"
                                :label="user.name"
                                :value="user.id"
                            />
                        </el-select>
                        <p
                            v-if="executorAssignment.errors.executor_id"
                            class="mt-[var(--brand-space-8)] text-xs text-[var(--brand-danger)]"
                        >
                            {{ executorAssignment.errors.executor_id }}
                        </p>
                        <el-button
                            class="mt-[var(--brand-space-16)]"
                            :loading="executorAssignment.processing"
                            @click="assignExecutor"
                            >保存执行人</el-button
                        >
                    </div>
                </section>
                <section
                    v-if="
                        application.status === 'execution_completed' ||
                        application.status === 'archived'
                    "
                    class="rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)]"
                >
                    <h2 class="font-sans text-lg text-[var(--brand-title)]">
                        项目归档
                    </h2>
                    <template v-if="application.status === 'archived'">
                        <p
                            class="mt-[var(--brand-space-16)] text-sm text-[var(--brand-body)]"
                        >
                            材料已核验，档案只读。
                        </p>
                        <Link
                            :href="`/procurement/applications/${application.id}/archive`"
                            class="mt-[var(--brand-space-16)] inline-block text-sm font-medium text-[var(--brand-primary)] underline"
                            >查看项目档案 →</Link
                        >
                    </template>
                    <template v-else>
                        <p
                            v-if="missingArchiveMaterials.length"
                            class="mt-[var(--brand-space-16)] text-sm leading-6 text-[var(--brand-warning)]"
                        >
                            尚缺：{{
                                missingArchiveMaterials
                                    .map(
                                        (kind) =>
                                            archiveKindLabel[kind] ?? kind,
                                    )
                                    .join('、')
                            }}
                        </p>
                        <p
                            v-else
                            class="mt-[var(--brand-space-16)] text-sm text-[var(--brand-primary)]"
                        >
                            必需材料已齐全，可以归档。
                        </p>
                        <p
                            v-if="archiving.errors.materials"
                            class="mt-[var(--brand-space-8)] text-xs text-[var(--brand-danger)]"
                        >
                            {{ archiving.errors.materials }}
                        </p>
                        <el-button
                            v-if="canArchive"
                            class="mt-[var(--brand-space-16)]"
                            :disabled="missingArchiveMaterials.length > 0"
                            :loading="archiving.processing"
                            @click="archiveProject"
                            >归档项目</el-button
                        >
                    </template>
                </section>
                <section
                    v-for="step in steps.filter(
                        (item) =>
                            item.status === 'pending' &&
                            item.assignee_id === currentUserId,
                    )"
                    :key="step.id"
                    class="rounded-[var(--brand-radius-md)] border border-[var(--brand-primary)] bg-[var(--brand-primary-light)]/70 p-[var(--brand-space-24)]"
                >
                    <h2 class="font-sans text-xl text-[var(--brand-title)]">
                        填写审批意见
                    </h2>
                    <el-input
                        v-model="decision.opinion"
                        class="mt-[var(--brand-space-16)]"
                        type="textarea"
                        :rows="4"
                        maxlength="2000"
                        show-word-limit
                        placeholder="通过可选填；退回和驳回需填写原因"
                    />
                    <p
                        v-if="decision.errors.opinion"
                        class="mt-[var(--brand-space-8)] text-xs text-[var(--brand-danger)]"
                    >
                        {{ decision.errors.opinion }}
                    </p>
                    <div
                        class="mt-[var(--brand-space-24)] flex flex-wrap gap-[var(--brand-space-8)]"
                    >
                        <el-button
                            type="primary"
                            :loading="decision.processing"
                            @click="decide(step.id, 'approve')"
                            >通过</el-button
                        ><el-button
                            :loading="decision.processing"
                            @click="decide(step.id, 'return')"
                            >退回修改</el-button
                        ><el-button
                            type="danger"
                            plain
                            :loading="decision.processing"
                            @click="decide(step.id, 'reject')"
                            >驳回</el-button
                        >
                    </div>
                </section>
                <section
                    v-if="
                        canManageApprovals &&
                        steps.some((step) =>
                            ['pending', 'queued'].includes(step.status),
                        )
                    "
                    class="rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)]"
                >
                    <h2 class="font-sans text-lg text-[var(--brand-title)]">
                        调整审批人
                    </h2>
                    <div
                        v-for="step in steps.filter((item) =>
                            ['pending', 'queued'].includes(item.status),
                        )"
                        :key="step.id"
                        class="mt-[var(--brand-space-16)] border-t border-[var(--brand-border)] pt-[var(--brand-space-16)]"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-[var(--brand-space-8)] text-sm">
                            <p class="font-semibold text-[var(--brand-title)]">{{ roleLabel[step.role_code] ?? step.role_code }}</p>
                            <p class="text-[var(--brand-muted)]">当前审批人：{{ step.assignee_name || '未指定' }}</p>
                        </div>
                        <label :for="`assignee-${step.id}`" class="mt-[var(--brand-space-16)] block text-sm text-[var(--brand-body)]">选择审批人</label>
                        <el-autocomplete
                            :id="`assignee-${step.id}`"
                            :model-value="assigneeInputs[step.id]"
                            :fetch-suggestions="searchAssignees"
                            :trigger-on-focus="false"
                            highlight-first-item
                            placeholder="输入姓名搜索审批人"
                            class="mt-[var(--brand-space-8)] w-full"
                            @update:model-value="editAssignee(step.id, $event)"
                            @select="selectAssignee(step.id, $event)"
                        />
                        <p v-if="assigneeInputs[step.id].trim() && !users.some((user) => user.name.includes(assigneeInputs[step.id].trim()))" class="mt-[var(--brand-space-8)] text-xs text-[var(--brand-muted)]">没有匹配的审批人</p>
                        <p v-if="reassignmentErrors[step.id]" class="mt-[var(--brand-space-8)] text-sm text-[var(--brand-danger)]">{{ reassignmentErrors[step.id] }}</p>
                        <button type="button" class="mt-[var(--brand-space-16)] min-h-10 rounded-[var(--brand-radius-md)] bg-[var(--brand-primary)] px-[var(--brand-space-24)] text-sm font-semibold text-[var(--brand-on-primary)] hover:bg-[var(--brand-primary-hover)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--brand-primary)] disabled:opacity-50" :disabled="reassigning[step.id] || selectedAssignees[step.id] === step.assignee_id || !users.some((user) => user.id === selectedAssignees[step.id])" @click="reassign(step.id)">{{ reassigning[step.id] ? '更新中…' : '更新此节点' }}</button>
                    </div>
                </section>
                <section
                    v-if="events.length"
                    class="rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)]"
                >
                    <h2 class="font-sans text-lg text-[var(--brand-title)]">
                        操作历史
                    </h2>
                    <ul
                        class="mt-[var(--brand-space-16)] space-y-[var(--brand-space-16)] text-xs text-[var(--brand-muted)]"
                    >
                        <li v-for="event in events" :key="event.id" class="border-b border-[var(--brand-border)] pb-[var(--brand-space-8)] last:border-0">
                            <span class="font-medium text-[var(--brand-title)]">{{ eventLabel[event.event] ?? event.event }}</span>
                            · {{ event.actor_name || '人员未记录' }}
                            · {{ event.created_at.slice(0, 16).replace('T', ' ') }}
                            <p v-if="event.details" class="mt-[var(--brand-space-8)] text-[var(--brand-body)]">{{ event.details }}</p>
                        </li>
                    </ul>
                </section>
            </aside>
        </div>
    </main>
</template>
