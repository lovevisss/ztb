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
    <main class="mx-auto w-full max-w-6xl px-5 py-8 md:px-10 md:py-12">
        <Link
            href="/procurement/approvals"
            class="text-sm text-emerald-800 hover:underline"
            >← 返回待办</Link
        >
        <header
            class="mt-7 flex flex-wrap items-end justify-between gap-4 border-b border-slate-200 pb-7"
        >
            <div>
                <p class="font-mono text-xs text-emerald-700">
                    {{ application.project_number }}
                </p>
                <h1
                    class="mt-2 font-serif text-3xl font-semibold text-slate-900"
                >
                    {{ application.title }}
                </h1>
                <p class="mt-3 text-sm text-slate-500">
                    {{
                        application.purchase_type === 'central_tender'
                            ? '统一招标'
                            : '部门自行采购'
                    }}
                    ·
                    {{ statusLabel[application.status] ?? application.status }}
                </p>
            </div>
            <p class="font-serif text-2xl text-slate-900">
                ¥{{ (application.budget_cents / 100).toLocaleString('zh-CN') }}
            </p>
        </header>

        <div
            v-if="sourceApplication"
            class="mt-6 rounded-md border border-emerald-200 bg-emerald-50 px-5 py-3 text-sm text-emerald-900"
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

        <div class="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_340px]">
            <div class="space-y-7">
                <section
                    class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm"
                >
                    <h2 class="font-serif text-xl font-semibold text-slate-900">
                        申请内容
                    </h2>
                    <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-slate-500">经费列支</dt>
                            <dd class="mt-1 text-slate-900">
                                {{ application.funding_source }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">归口部门</dt>
                            <dd class="mt-1 text-slate-900">
                                {{ application.responsible_department }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">组织形式</dt>
                            <dd class="mt-1 text-slate-900">
                                {{ application.organization_form }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">采购方式</dt>
                            <dd class="mt-1 text-slate-900">
                                {{ application.purchase_method }}
                            </dd>
                        </div>
                        <div v-if="application.reason" class="sm:col-span-2">
                            <dt class="text-slate-500">采购理由</dt>
                            <dd class="mt-1 whitespace-pre-wrap text-slate-900">
                                {{ application.reason }}
                            </dd>
                        </div>
                        <div
                            v-if="application.procurement_requirements"
                            class="sm:col-span-2"
                        >
                            <dt class="text-slate-500">采购需求</dt>
                            <dd class="mt-1 whitespace-pre-wrap text-slate-900">
                                {{ application.procurement_requirements }}
                            </dd>
                        </div>
                        <div
                            v-if="application.purchase_list"
                            class="sm:col-span-2"
                        >
                            <dt class="text-slate-500">采购清单</dt>
                            <dd class="mt-1 whitespace-pre-wrap text-slate-900">
                                {{ application.purchase_list }}
                            </dd>
                        </div>
                        <div
                            v-if="application.technical_parameters"
                            class="sm:col-span-2"
                        >
                            <dt class="text-slate-500">技术参数</dt>
                            <dd class="mt-1 whitespace-pre-wrap text-slate-900">
                                {{ application.technical_parameters }}
                            </dd>
                        </div>
                        <div
                            v-if="application.service_requirements"
                            class="sm:col-span-2"
                        >
                            <dt class="text-slate-500">服务要求</dt>
                            <dd class="mt-1 whitespace-pre-wrap text-slate-900">
                                {{ application.service_requirements }}
                            </dd>
                        </div>
                        <div
                            v-if="application.acceptance_criteria"
                            class="sm:col-span-2"
                        >
                            <dt class="text-slate-500">验收标准</dt>
                            <dd class="mt-1 whitespace-pre-wrap text-slate-900">
                                {{ application.acceptance_criteria }}
                            </dd>
                        </div>
                        <div
                            v-if="application.budget_basis_note"
                            class="sm:col-span-2"
                        >
                            <dt class="text-slate-500">预算测算</dt>
                            <dd class="mt-1 whitespace-pre-wrap text-slate-900">
                                {{ application.budget_basis_note }}
                            </dd>
                        </div>
                    </dl>
                    <div
                        v-if="attachments.length"
                        class="mt-6 border-t border-slate-100 pt-5"
                    >
                        <h3 class="text-sm font-semibold text-slate-800">
                            申请附件
                        </h3>
                        <ul class="mt-3 space-y-2">
                            <li v-for="file in attachments" :key="file.id">
                                <a
                                    :href="`/procurement/attachments/${file.id}/download`"
                                    class="text-sm text-emerald-800 hover:underline"
                                    >{{ file.original_name }} ↗</a
                                >
                            </li>
                        </ul>
                    </div>
                </section>

                <section
                    class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm"
                >
                    <h2 class="font-serif text-xl font-semibold text-slate-900">
                        审批记录
                    </h2>
                    <ol class="mt-5 space-y-5 border-l border-slate-200 pl-5">
                        <li
                            v-for="step in steps"
                            :key="step.id"
                            class="relative text-sm"
                        >
                            <span
                                class="absolute top-1 -left-[25px] size-2 rounded-full bg-emerald-700"
                            />
                            <p class="font-medium text-slate-900">
                                第 {{ step.attempt }} 轮 ·
                                {{
                                    roleLabel[step.role_code] ?? step.role_code
                                }}
                                · {{ statusLabel[step.status] ?? step.status }}
                            </p>
                            <p
                                v-if="step.opinion"
                                class="mt-1 whitespace-pre-wrap text-slate-600"
                            >
                                {{ step.opinion }}
                            </p>
                            <p
                                v-if="step.acted_at"
                                class="mt-1 text-xs text-slate-400"
                            >
                                {{
                                    step.acted_at.slice(0, 16).replace('T', ' ')
                                }}
                            </p>
                        </li>
                    </ol>
                </section>
            </div>

            <aside class="space-y-5">
                <section
                    v-if="
                        [
                            'approved',
                            'executing',
                            'execution_completed',
                            'archived',
                        ].includes(application.status)
                    "
                    class="rounded-lg border border-emerald-200 bg-emerald-50 p-6"
                >
                    <h2 class="font-serif text-lg text-slate-900">采购执行</h2>
                    <p
                        v-if="execution?.supplier"
                        class="mt-3 text-sm text-slate-700"
                    >
                        供应商：{{ execution.supplier }}
                    </p>
                    <p
                        v-if="execution?.actual_amount_cents != null"
                        class="mt-1 text-sm text-slate-700"
                    >
                        实际金额：¥{{
                            (
                                execution.actual_amount_cents / 100
                            ).toLocaleString('zh-CN')
                        }}
                    </p>
                    <Link
                        :href="`/procurement/applications/${application.id}/execution`"
                        class="mt-4 inline-block text-sm font-semibold text-emerald-800 underline"
                        >查看执行登记 →</Link
                    >
                    <div
                        v-if="
                            canManageApprovals &&
                            application.status !== 'archived'
                        "
                        class="mt-5 border-t border-emerald-200 pt-4"
                    >
                        <label class="text-sm text-slate-700">指定执行人</label>
                        <el-select
                            v-model="executorAssignment.executor_id"
                            filterable
                            placeholder="选择执行人"
                            class="mt-2 w-full"
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
                            class="mt-1 text-xs text-red-700"
                        >
                            {{ executorAssignment.errors.executor_id }}
                        </p>
                        <el-button
                            class="mt-3"
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
                    class="rounded-lg border border-slate-200 bg-white p-6"
                >
                    <h2 class="font-serif text-lg text-slate-900">项目归档</h2>
                    <template v-if="application.status === 'archived'">
                        <p class="mt-3 text-sm text-slate-600">
                            材料已核验，档案只读。
                        </p>
                        <Link
                            :href="`/procurement/applications/${application.id}/archive`"
                            class="mt-3 inline-block text-sm font-medium text-emerald-800 underline"
                            >查看项目档案 →</Link
                        >
                    </template>
                    <template v-else>
                        <p
                            v-if="missingArchiveMaterials.length"
                            class="mt-3 text-sm leading-6 text-amber-800"
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
                        <p v-else class="mt-3 text-sm text-emerald-800">
                            必需材料已齐全，可以归档。
                        </p>
                        <p
                            v-if="archiving.errors.materials"
                            class="mt-2 text-xs text-red-700"
                        >
                            {{ archiving.errors.materials }}
                        </p>
                        <el-button
                            v-if="canArchive"
                            class="mt-4"
                            type="primary"
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
                    class="rounded-lg border border-emerald-200 bg-emerald-50/70 p-6"
                >
                    <h2 class="font-serif text-xl text-slate-900">
                        填写审批意见
                    </h2>
                    <el-input
                        v-model="decision.opinion"
                        class="mt-4"
                        type="textarea"
                        :rows="4"
                        maxlength="2000"
                        show-word-limit
                        placeholder="通过可选填；退回和驳回需填写原因"
                    />
                    <p
                        v-if="decision.errors.opinion"
                        class="mt-2 text-xs text-red-700"
                    >
                        {{ decision.errors.opinion }}
                    </p>
                    <div class="mt-5 flex flex-wrap gap-2">
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
                    class="rounded-lg border border-slate-200 bg-white p-6"
                >
                    <h2 class="font-serif text-lg text-slate-900">
                        调整审批人
                    </h2>
                    <div
                        v-for="step in steps.filter((item) =>
                            ['pending', 'queued'].includes(item.status),
                        )"
                        :key="step.id"
                        class="mt-4 border-t border-slate-100 pt-4"
                    >
                        <p class="mb-2 text-xs text-slate-500">
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
                            class="mt-2"
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
                    class="rounded-lg border border-amber-200 bg-amber-50 p-6"
                >
                    <p class="text-sm text-amber-900">
                        申请已退回，请修改后重新提交。
                    </p>
                    <Link
                        :href="`/procurement/applications/${application.id}/edit`"
                        class="mt-3 inline-block text-sm font-semibold text-amber-900 underline"
                        >编辑申请</Link
                    >
                </section>
                <section
                    v-if="
                        application.status === 'returned' &&
                        application.applicant_id === currentUserId &&
                        application.application_form === 'central_tender'
                    "
                    class="rounded-lg border border-amber-200 bg-amber-50 p-6"
                >
                    <p class="text-sm text-amber-900">
                        申请已退回，请修改后重新提交。
                    </p>
                    <Link
                        :href="`/procurement/tenders/${application.id}/edit`"
                        class="mt-3 inline-block text-sm font-semibold text-amber-900 underline"
                        >编辑申请</Link
                    >
                </section>
                <section
                    v-if="events.length"
                    class="rounded-lg border border-slate-200 bg-white p-6"
                >
                    <h2 class="font-serif text-lg text-slate-900">操作历史</h2>
                    <ul class="mt-4 space-y-3 text-xs text-slate-500">
                        <li v-for="event in events" :key="event.id">
                            {{ event.event }} · {{ event.details || '无备注' }}
                        </li>
                    </ul>
                </section>
            </aside>
        </div>
    </main>
</template>
