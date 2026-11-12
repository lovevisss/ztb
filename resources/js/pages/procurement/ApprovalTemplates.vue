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
type DepartmentApprover = { department_id: number; supervising_leader_id: number; tender_group_representative_id: number };

const props = defineProps<{
    templates: Template[];
    departments: Department[];
    users: User[];
    roles: Role[];
    departmentApprovers: DepartmentApprover[];
}>();

const form = useForm({
    name: '',
    purchase_type: 'self_purchase',
    min_budget_yuan: '0',
    max_budget_yuan: '',
    applicant_department_id: null as number | null,
    responsible_department: '',
    assignees: { procurement_head: null as number | null, procurement_manager: null as number | null, asset_head: null as number | null },
});
const mapping = useForm({ department_id: null as number | null, supervising_leader_id: null as number | null, tender_group_representative_id: null as number | null });
function selectDepartment(id: number | null) {
    const saved = props.departmentApprovers.find((item) => item.department_id === id);
    mapping.supervising_leader_id = saved?.supervising_leader_id ?? null;
    mapping.tender_group_representative_id = saved?.tender_group_representative_id ?? null;
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
                按采购类型、金额和部门配置流程适用条件。审批节点采用固定顺序，新版本仅影响之后提交的项目。
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
                        部门负责人 → 经费负责人 → 采购管理部门领导 → 采购管理人员 → 资产管理部领导 → 经费分管院领导 → 招投标领导小组代表
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
                    <p class="text-sm text-[var(--brand-muted)]">节点顺序固定。请在人员管理中分配采购管理部门领导和资产管理部领导角色，并在下方配置部门审批人。</p>
                    <label v-for="role in ['procurement_head', 'procurement_manager', 'asset_head'] as const" :key="role" class="block">
                        <span class="field-label">{{ roles.find((item) => item.code === role)?.name ?? role }}（选填）</span>
                        <el-select v-model="form.assignees[role]" clearable filterable placeholder="按角色自动匹配唯一人员" class="w-full">
                            <el-option v-for="user in users" :key="user.id" :label="user.name" :value="user.id" />
                        </el-select>
                    </label>
                    <el-button
                        native-type="submit"
                        type="primary"
                        :loading="form.processing"
                        class="w-full"
                        >保存新流程</el-button
                    >
                </form>
                <form class="mt-8 space-y-4 border-t border-[var(--brand-border)] pt-6" @submit.prevent="mapping.post('/procurement/department-approvers')">
                    <h2 class="text-lg font-semibold text-[var(--brand-title)]">部门审批人映射</h2>
                    <el-select v-model="mapping.department_id" filterable placeholder="选择申请人部门" class="w-full" @change="selectDepartment">
                        <el-option v-for="department in departments" :key="department.id" :label="department.name" :value="department.id" />
                    </el-select>
                    <el-select v-model="mapping.supervising_leader_id" filterable placeholder="经费分管院领导" class="w-full">
                        <el-option v-for="user in users" :key="user.id" :label="user.name" :value="user.id" />
                    </el-select>
                    <el-select v-model="mapping.tender_group_representative_id" filterable placeholder="招投标领导小组代表" class="w-full">
                        <el-option v-for="user in users" :key="user.id" :label="user.name" :value="user.id" />
                    </el-select>
                    <p v-for="error in mapping.errors" :key="error" class="field-error">{{ error }}</p>
                    <el-button native-type="submit" type="primary" :loading="mapping.processing" class="w-full">保存部门审批人</el-button>
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
