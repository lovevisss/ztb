<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

type Application = {
    id: number;
    applicant_id: number;
    executor_id: number | null;
    title: string;
    project_number: string;
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
    is_non_expenditure: boolean;
    remarks: string | null;
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
};
type Event = {
    id: number;
    event: string;
    details: string | null;
    created_at: string;
};
type Attachment = { id: number; kind: string; original_name: string };
type User = { id: number; name: string };

const props = defineProps<{
    application: Application;
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
}>();
const decision = useForm({ action: 'approve', opinion: '' });
const reassignment = useForm({ assignee_id: '' });
const executorAssignment = useForm({
    executor_id: props.application.executor_id ?? ('' as number | ''),
});
const archiving = useForm({ materials: '' });

function decide(stepId: number, action: 'approve' | 'return' | 'reject') {
    decision.action = action;
    decision.post(`/procurement/approvals/${stepId}/decision`, {
        onSuccess: () => decision.reset('opinion'),
    });
}

function reassign(stepId: number) {
    reassignment.put(`/procurement/approvals/${stepId}/assignee`, {
        onSuccess: () => reassignment.reset(),
    });
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
    rejected: '已驳回',
    cancelled: '已取消',
};
</script>

<template>
    <Head :title="application.title" />
    <main
        class="mx-auto w-full max-w-6xl px-[var(--brand-space-24)] py-[var(--brand-space-32)] md:px-[var(--brand-space-32)] md:py-[var(--brand-space-32)]"
    >
        <Link
            href="/procurement/approvals"
            class="text-sm text-[var(--brand-primary)] hover:underline"
            >← 返回待办</Link
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
                        application.purchase_type === 'central_tender'
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

        <div
            class="mt-[var(--brand-space-32)] grid gap-[var(--brand-space-32)] lg:grid-cols-[minmax(0,1fr)_340px]"
        >
            <div class="space-y-[var(--brand-space-32)]">
                <section
                    class="rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)] shadow-sm"
                >
                    <h2
                        class="font-sans text-xl font-semibold text-[var(--brand-title)]"
                    >
                        申请内容
                    </h2>
                    <dl
                        class="mt-[var(--brand-space-24)] grid gap-[var(--brand-space-16)] text-sm sm:grid-cols-2"
                    >
                        <div>
                            <dt class="text-[var(--brand-muted)]">经费列支</dt>
                            <dd
                                class="mt-[var(--brand-space-8)] text-[var(--brand-title)]"
                            >
                                {{ application.funding_source }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[var(--brand-muted)]">归口部门</dt>
                            <dd
                                class="mt-[var(--brand-space-8)] text-[var(--brand-title)]"
                            >
                                {{ application.responsible_department }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[var(--brand-muted)]">组织形式</dt>
                            <dd
                                class="mt-[var(--brand-space-8)] text-[var(--brand-title)]"
                            >
                                {{ application.organization_form }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[var(--brand-muted)]">采购方式</dt>
                            <dd
                                class="mt-[var(--brand-space-8)] text-[var(--brand-title)]"
                            >
                                {{ application.purchase_method }}
                            </dd>
                        </div>
                        <div v-if="application.reason" class="sm:col-span-2">
                            <dt class="text-[var(--brand-muted)]">采购理由</dt>
                            <dd
                                class="mt-[var(--brand-space-8)] whitespace-pre-wrap text-[var(--brand-title)]"
                            >
                                {{ application.reason }}
                            </dd>
                        </div>
                        <div
                            v-if="application.procurement_requirements"
                            class="sm:col-span-2"
                        >
                            <dt class="text-[var(--brand-muted)]">采购需求</dt>
                            <dd
                                class="mt-[var(--brand-space-8)] whitespace-pre-wrap text-[var(--brand-title)]"
                            >
                                {{ application.procurement_requirements }}
                            </dd>
                        </div>
                        <div
                            v-if="application.purchase_list"
                            class="sm:col-span-2"
                        >
                            <dt class="text-[var(--brand-muted)]">采购清单</dt>
                            <dd
                                class="mt-[var(--brand-space-8)] whitespace-pre-wrap text-[var(--brand-title)]"
                            >
                                {{ application.purchase_list }}
                            </dd>
                        </div>
                        <div
                            v-if="application.technical_parameters"
                            class="sm:col-span-2"
                        >
                            <dt class="text-[var(--brand-muted)]">技术参数</dt>
                            <dd
                                class="mt-[var(--brand-space-8)] whitespace-pre-wrap text-[var(--brand-title)]"
                            >
                                {{ application.technical_parameters }}
                            </dd>
                        </div>
                        <div
                            v-if="application.service_requirements"
                            class="sm:col-span-2"
                        >
                            <dt class="text-[var(--brand-muted)]">服务要求</dt>
                            <dd
                                class="mt-[var(--brand-space-8)] whitespace-pre-wrap text-[var(--brand-title)]"
                            >
                                {{ application.service_requirements }}
                            </dd>
                        </div>
                        <div
                            v-if="application.acceptance_criteria"
                            class="sm:col-span-2"
                        >
                            <dt class="text-[var(--brand-muted)]">验收标准</dt>
                            <dd
                                class="mt-[var(--brand-space-8)] whitespace-pre-wrap text-[var(--brand-title)]"
                            >
                                {{ application.acceptance_criteria }}
                            </dd>
                        </div>
                        <div
                            v-if="application.budget_basis_note"
                            class="sm:col-span-2"
                        >
                            <dt class="text-[var(--brand-muted)]">预算测算</dt>
                            <dd
                                class="mt-[var(--brand-space-8)] whitespace-pre-wrap text-[var(--brand-title)]"
                            >
                                {{ application.budget_basis_note }}
                            </dd>
                        </div>
                    </dl>
                    <div v-if="application.category || lineItems.length" class="mt-6 border-t border-[var(--brand-border)] pt-5 text-sm">
                        <p>归属：{{ application.category }}　经办人电话：{{ application.contact_phone }}　使用部门：{{ usageDepartmentName ?? '无' }}</p>
                        <p class="mt-2">特殊采购：{{ application.is_special_purchase ? '是' : '否' }}　非支出项目：{{ application.is_non_expenditure ? '是' : '否' }}</p>
                        <p v-if="application.remarks" class="mt-2 whitespace-pre-wrap">备注：{{ application.remarks }}</p>
                        <h3 class="mt-5 font-semibold">采购清单</h3>
                        <div class="mt-2 overflow-x-auto"><table class="w-full min-w-[600px] text-left"><thead><tr class="border-b border-[var(--brand-border)]"><th>名称</th><th>技术参数</th><th>数量</th><th>单价（元）</th><th>总价（元）</th></tr></thead><tbody><tr v-for="item in lineItems" :key="item.id" class="border-b border-[var(--brand-border)]"><td>{{ item.name }}</td><td>{{ item.specification }}</td><td>{{ item.quantity }} {{ item.unit }}</td><td>{{ (item.unit_price_cents / 100).toFixed(2) }}</td><td>{{ (item.total_cents / 100).toFixed(2) }}</td></tr></tbody></table></div>
                        <h3 class="mt-5 font-semibold">项目预算指标</h3>
                        <ul class="mt-2 space-y-1"><li v-for="allocation in budgetAllocations" :key="allocation.id">{{ allocation.code }} · {{ allocation.name }} · {{ allocation.director }}：¥{{ (allocation.amount_cents / 100).toFixed(2) }}</li></ul>
                    </div>
                    <div
                        v-if="attachments.length"
                        class="mt-[var(--brand-space-24)] border-t border-[var(--brand-border)] pt-[var(--brand-space-24)]"
                    >
                        <h3
                            class="text-sm font-semibold text-[var(--brand-title)]"
                        >
                            申请附件
                        </h3>
                        <ul
                            class="mt-[var(--brand-space-16)] space-y-[var(--brand-space-8)]"
                        >
                            <li v-for="file in attachments" :key="file.id">
                                <a
                                    :href="`/procurement/attachments/${file.id}/download`"
                                    class="text-sm text-[var(--brand-primary)] hover:underline"
                                    >{{ file.original_name }} ↗</a
                                >
                            </li>
                        </ul>
                    </div>
                </section>

                <section
                    class="rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)] shadow-sm"
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

            <aside class="space-y-[var(--brand-space-24)]">
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
                    class="rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)]"
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
                    class="rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)]"
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
                        <p
                            class="mb-[var(--brand-space-8)] text-xs text-[var(--brand-muted)]"
                        >
                            {{ roleLabel[step.role_code] ?? step.role_code }}
                        </p>
                        <el-select
                            v-model="reassignment.assignee_id"
                            filterable
                            placeholder="选择新审批人"
                            class="w-full"
                            ><el-option
                                v-for="user in users"
                                :key="user.id"
                                :label="user.name"
                                :value="user.id" /></el-select
                        ><el-button
                            class="mt-[var(--brand-space-8)]"
                            size="small"
                            @click="reassign(step.id)"
                            >更新此节点</el-button
                        >
                    </div>
                </section>
                <section
                    v-if="
                        application.status === 'returned' &&
                        application.applicant_id === currentUserId &&
                        application.application_form === 'self_purchase'
                    "
                    class="rounded-[var(--brand-radius-md)] border border-[var(--brand-warning)] bg-[var(--brand-page)] p-[var(--brand-space-24)]"
                >
                    <p class="text-sm text-[var(--brand-warning)]">
                        申请已退回，请修改后重新提交。
                    </p>
                    <Link
                        :href="`/procurement/applications/${application.id}/edit`"
                        class="mt-[var(--brand-space-16)] inline-block text-sm font-semibold text-[var(--brand-warning)] underline"
                        >编辑申请</Link
                    >
                </section>
                <section
                    v-if="
                        application.status === 'returned' &&
                        application.applicant_id === currentUserId &&
                        application.application_form === 'central_tender'
                    "
                    class="rounded-[var(--brand-radius-md)] border border-[var(--brand-warning)] bg-[var(--brand-page)] p-[var(--brand-space-24)]"
                >
                    <p class="text-sm text-[var(--brand-warning)]">
                        申请已退回，请修改后重新提交。
                    </p>
                    <Link
                        :href="`/procurement/tenders/${application.id}/edit`"
                        class="mt-[var(--brand-space-16)] inline-block text-sm font-semibold text-[var(--brand-warning)] underline"
                        >编辑申请</Link
                    >
                </section>
                <section
                    v-if="events.length"
                    class="rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)]"
                >
                    <h2 class="font-sans text-lg text-[var(--brand-title)]">
                        操作历史
                    </h2>
                    <ul
                        class="mt-[var(--brand-space-16)] space-y-[var(--brand-space-16)] text-xs text-[var(--brand-muted)]"
                    >
                        <li v-for="event in events" :key="event.id">
                            {{ event.event }} · {{ event.details || '无备注' }}
                        </li>
                    </ul>
                </section>
            </aside>
        </div>
    </main>
</template>
