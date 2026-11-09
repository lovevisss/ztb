<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

type TemplateStep = { role_code: string; assignee_id: number | null };
type Template = {
    id: number;
    name: string;
    purchase_type: string;
    version: number;
    min_budget_cents: number;
    max_budget_cents: number | null;
    applicant_department_id: number | null;
    responsible_department: string | null;
    steps: TemplateStep[];
};
type Department = { id: number; name: string };
type User = { id: number; name: string; department_id: number | null };
type Role = { code: string; name: string };

defineProps<{
    templates: Template[];
    departments: Department[];
    users: User[];
    roles: Role[];
}>();

const form = useForm({
    name: '',
    purchase_type: 'self_purchase',
    min_budget_yuan: '0',
    max_budget_yuan: '',
    applicant_department_id: null as number | null,
    responsible_department: '',
    steps: [
        { role_code: 'department_head', assignee_id: null },
        { role_code: 'procurement_manager', assignee_id: null },
    ] as TemplateStep[],
});

function addStep() {
    form.steps.push({ role_code: 'procurement_manager', assignee_id: null });
}
function removeStep(index: number) {
    form.steps.splice(index, 1);
}
function save() {
    form.post('/procurement/approval-templates');
}
</script>

<template>
    <Head title="审批流程配置" />
    <main
        class="mx-auto w-full max-w-6xl px-[var(--brand-space-24)] py-[var(--brand-space-32)] md:px-[var(--brand-space-32)] md:py-[var(--brand-space-32)]"
    >
        <header
            class="border-b border-[var(--brand-border)] pb-[var(--brand-space-32)]"
        >
            <p
                class="mb-[var(--brand-space-8)] text-xs font-semibold tracking-[0.28em] text-[var(--brand-primary)]"
            >
                WORKFLOW / 采购管理
            </p>
            <h1
                class="font-sans text-3xl font-semibold text-[var(--brand-title)] md:text-4xl"
            >
                审批流程配置
            </h1>
            <p
                class="mt-[var(--brand-space-16)] text-sm text-[var(--brand-muted)]"
            >
                按采购类型、金额和部门配置顺序审批。新版本仅影响之后提交的项目。
            </p>
        </header>
        <div
            class="mt-[var(--brand-space-32)] grid gap-[var(--brand-space-32)] lg:grid-cols-[minmax(0,1fr)_390px]"
        >
            <section class="space-y-[var(--brand-space-16)]">
                <article
                    v-for="template in templates"
                    :key="template.id"
                    class="rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)] shadow-sm"
                >
                    <div
                        class="flex items-start justify-between gap-[var(--brand-space-16)]"
                    >
                        <div>
                            <p
                                class="text-xs font-semibold text-[var(--brand-primary)]"
                            >
                                版本 {{ template.version }} ·
                                {{
                                    template.purchase_type === 'central_tender'
                                        ? '统一招标'
                                        : '部门自行采购'
                                }}
                            </p>
                            <h2
                                class="mt-[var(--brand-space-8)] font-sans text-lg text-[var(--brand-title)]"
                            >
                                {{ template.name }}
                            </h2>
                        </div>
                        <span class="text-xs text-[var(--brand-muted)]"
                            >¥{{
                                (
                                    template.min_budget_cents / 100
                                ).toLocaleString('zh-CN')
                            }}
                            起</span
                        >
                    </div>
                    <p
                        class="mt-[var(--brand-space-16)] text-xs text-[var(--brand-muted)]"
                    >
                        {{
                            template.steps
                                .map(
                                    (step) =>
                                        roles.find(
                                            (role) =>
                                                role.code === step.role_code,
                                        )?.name ?? step.role_code,
                                )
                                .join(' → ')
                        }}
                    </p>
                </article>
            </section>
            <section
                class="h-fit rounded-[var(--brand-radius-md)] border border-[var(--brand-primary)] bg-[var(--brand-primary-light)]/60 p-[var(--brand-space-24)] lg:sticky lg:top-8"
            >
                <h2 class="font-sans text-xl text-[var(--brand-title)]">
                    新增流程版本
                </h2>
                <form
                    class="mt-[var(--brand-space-24)] space-y-[var(--brand-space-16)]"
                    @submit.prevent="save"
                >
                    <label class="block"
                        ><span class="field-label">流程名称</span
                        ><el-input
                            v-model="form.name"
                            placeholder="例如：大额设备采购审批"
                        /><span v-if="form.errors.name" class="field-error">{{
                            form.errors.name
                        }}</span></label
                    >
                    <label class="block"
                        ><span class="field-label">采购类型</span
                        ><el-select v-model="form.purchase_type" class="w-full"
                            ><el-option
                                label="部门自行采购"
                                value="self_purchase" /><el-option
                                label="统一招标"
                                value="central_tender" /></el-select
                    ></label>
                    <div class="grid grid-cols-2 gap-[var(--brand-space-16)]">
                        <label
                            ><span class="field-label">最低预算（元）</span
                            ><el-input
                                v-model="form.min_budget_yuan"
                                type="number" /></label
                        ><label
                            ><span class="field-label"
                                >最高预算（元，选填）</span
                            ><el-input
                                v-model="form.max_budget_yuan"
                                type="number"
                        /></label>
                    </div>
                    <p
                        v-if="
                            form.errors.max_budget_yuan ||
                            form.errors.min_budget_yuan
                        "
                        class="field-error"
                    >
                        {{
                            form.errors.max_budget_yuan ||
                            form.errors.min_budget_yuan
                        }}
                    </p>
                    <label class="block"
                        ><span class="field-label">申请部门（选填）</span
                        ><el-select
                            v-model="form.applicant_department_id"
                            clearable
                            filterable
                            placeholder="全校"
                            class="w-full"
                            ><el-option
                                v-for="department in departments"
                                :key="department.id"
                                :label="department.name"
                                :value="department.id" /></el-select
                    ></label>
                    <label class="block"
                        ><span class="field-label">归口部门（选填）</span
                        ><el-input
                            v-model="form.responsible_department"
                            placeholder="留空表示不限"
                    /></label>
                    <div
                        class="border-t border-[var(--brand-primary)] pt-[var(--brand-space-16)]"
                    >
                        <p class="field-label">审批步骤</p>
                        <div
                            v-for="(step, index) in form.steps"
                            :key="index"
                            class="mt-[var(--brand-space-16)] rounded-[var(--brand-radius-md)] bg-[var(--brand-card)] p-[var(--brand-space-16)]"
                        >
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-xs font-semibold text-[var(--brand-muted)]"
                                    >第 {{ index + 1 }} 步</span
                                ><button
                                    type="button"
                                    class="text-xs text-[var(--brand-danger)]"
                                    @click="removeStep(index)"
                                >
                                    移除
                                </button>
                            </div>
                            <el-select
                                v-model="step.role_code"
                                class="mt-[var(--brand-space-8)] w-full"
                                ><el-option
                                    v-for="role in roles"
                                    :key="role.code"
                                    :label="role.name"
                                    :value="role.code" /></el-select
                            ><el-select
                                v-model="step.assignee_id"
                                clearable
                                filterable
                                placeholder="按岗位自动匹配"
                                class="mt-[var(--brand-space-8)] w-full"
                                ><el-option
                                    v-for="user in users"
                                    :key="user.id"
                                    :label="user.name"
                                    :value="user.id"
                            /></el-select>
                        </div>
                        <button
                            type="button"
                            class="mt-[var(--brand-space-16)] text-sm font-medium text-[var(--brand-primary)]"
                            @click="addStep"
                        >
                            ＋ 增加步骤</button
                        ><span v-if="form.errors.steps" class="field-error">{{
                            form.errors.steps
                        }}</span>
                    </div>
                    <el-button
                        native-type="submit"
                        type="primary"
                        :loading="form.processing"
                        class="w-full"
                        >保存新流程</el-button
                    >
                </form>
            </section>
        </div>
    </main>
</template>

<style scoped>
.field-label {
    display: block;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--brand-body);
}
.field-error {
    display: block;
    margin-top: 0.35rem;
    font-size: 0.75rem;
    color: var(--brand-danger);
}
</style>
