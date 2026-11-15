<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';

type User = { id: number; name: string; school_account: string | null; department_name: string | null };
type Role = { code: string; name: string; default_user_id: number | null; default_user_name: string | null; default_user_active: boolean | null; role_users: string[]; effective_user_name: string | null; required_default: boolean };
type Department = { id: number; name: string };
type DepartmentApprover = { department_id: number; supervising_leader_id: number };
const props = defineProps<{
    roles: Role[];
    users: User[];
    departments: Department[];
    departmentApprovers: DepartmentApprover[];
    budgetIndicatorSummary: { total: number; unresolved: number };
    representativeNotice: string | null;
}>();
const page = usePage();
const selected = reactive<Record<string, number | null>>({});
const saving = reactive<Record<string, boolean>>({});
const errors = reactive<Record<string, string>>({});
const mapping = useForm({ department_id: null as number | null, supervising_leader_id: null as number | null });
function syncRoles() {
    for (const role of props.roles) selected[role.code] = role.default_user_id;
}
syncRoles();
watch(() => props.roles, syncRoles);
function userName(value: unknown): string {
    return props.users.find((user) => user.id === value)?.name
        ?? props.roles.find((role) => role.default_user_id === value)?.default_user_name
        ?? '';
}
function activeUserName(value: unknown): string {
    return props.users.find((user) => user.id === value)?.name ?? '';
}
function saveRole(role: Role) {
    if (saving[role.code] || selected[role.code] === role.default_user_id || (role.required_default && !selected[role.code])) return;
    saving[role.code] = true;
    delete errors[role.code];
    router.put(`/admin/approval-assignees/${role.code}`, { user_id: selected[role.code] }, {
        preserveScroll: true,
        onError: (response) => { errors[role.code] = response.user_id ?? '保存失败，请重试。'; },
        onFinish: () => { saving[role.code] = false; },
    });
}
function selectDepartment(id: number | null) {
    const saved = props.departmentApprovers.find((item) => item.department_id === id);
    mapping.supervising_leader_id = saved?.supervising_leader_id ?? null;
    mapping.clearErrors();
}
function mappedDepartment(departmentId: number): DepartmentApprover | undefined {
    return props.departmentApprovers.find((item) => item.department_id === departmentId);
}
function mappingValid(departmentId: number): boolean {
    const saved = mappedDepartment(departmentId);
    return !!saved && !!activeUserName(saved.supervising_leader_id);
}
function editDepartment(departmentId: number) {
    mapping.department_id = departmentId;
    selectDepartment(departmentId);
    document.getElementById('department-mapping')?.scrollIntoView({ behavior: 'smooth' });
}
function saveMapping() {
    mapping.post('/procurement/department-approvers', { preserveScroll: true });
}
</script>

<template>
    <Head title="审批人配置" />
    <main class="mx-auto w-full max-w-7xl px-[var(--brand-space-24)] py-[var(--brand-space-32)]">
        <header class="border-b border-[var(--brand-border)] pb-[var(--brand-space-24)]">
            <h1 class="text-[28px] font-semibold text-[var(--brand-title)]">审批人配置</h1>
            <p class="mt-[var(--brand-space-8)] text-sm text-[var(--brand-muted)]">为之后提交的采购申请配置审批人。招投标领导小组代表全校统一；其他岗位的流程模板指定人优先。</p>
        </header>
        <nav aria-label="审批人配置分类" class="mt-[var(--brand-space-24)] flex flex-wrap gap-[var(--brand-space-8)] border-b border-[var(--brand-border)]">
            <Link href="/admin/department-heads" class="rounded-t-[var(--brand-radius-md)] px-4 py-3 text-sm text-[var(--brand-body)] hover:text-[var(--brand-primary)] focus-visible:outline-2 focus-visible:outline-[var(--brand-primary)]">部门负责人</Link>
            <a href="#role-defaults" aria-current="page" class="rounded-t-[var(--brand-radius-md)] border-b-2 border-[var(--brand-primary)] px-4 py-3 text-sm font-semibold text-[var(--brand-primary)] focus-visible:outline-2 focus-visible:outline-[var(--brand-primary)]">岗位默认审批人</a>
            <a href="#department-mapping" class="rounded-t-[var(--brand-radius-md)] px-4 py-3 text-sm text-[var(--brand-body)] hover:text-[var(--brand-primary)] focus-visible:outline-2 focus-visible:outline-[var(--brand-primary)]">部门审批人映射</a>
            <a href="#budget-indicators" class="rounded-t-[var(--brand-radius-md)] px-4 py-3 text-sm text-[var(--brand-body)] hover:text-[var(--brand-primary)] focus-visible:outline-2 focus-visible:outline-[var(--brand-primary)]">经费负责人来源</a>
        </nav>
        <div v-if="(page.props.flash as { status?: string } | undefined)?.status" role="status" class="mt-[var(--brand-space-24)] rounded-[var(--brand-radius-md)] border border-[var(--brand-success)] p-[var(--brand-space-16)] text-sm text-[var(--brand-success)]">{{ (page.props.flash as { status?: string }).status }}</div>

        <section id="role-defaults" class="mt-[var(--brand-space-32)] scroll-mt-6">
            <h2 class="text-xl font-semibold text-[var(--brand-title)]">岗位默认审批人</h2>
            <p class="mt-2 text-sm text-[var(--brand-muted)]">招投标领导小组代表必须明确指定；其他岗位未指定时按角色匹配一名有效人员。此处设置不改变人员角色或访问权限。</p>
            <p v-if="representativeNotice" role="status" class="mt-4 rounded-[var(--brand-radius-md)] border border-[var(--brand-warning)] p-3 text-sm text-[var(--brand-warning)]">{{ representativeNotice }}</p>
            <div class="mt-[var(--brand-space-16)] grid gap-[var(--brand-space-16)] lg:grid-cols-2 xl:grid-cols-4">
                <article v-for="role in roles" :key="role.code" class="min-w-0 rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)] shadow-sm">
                    <h3 class="text-base font-semibold text-[var(--brand-title)]">{{ role.name }}</h3>
                    <p class="mt-2 text-sm" :class="role.effective_user_name ? 'text-[var(--brand-success)]' : 'text-[var(--brand-danger)]'">{{ role.effective_user_name ? `当前生效：${role.effective_user_name}` : role.default_user_id ? '指定账号已停用，请更换' : role.required_default ? '尚未指定全局代表' : role.role_users.length ? `匹配 ${role.role_users.length} 人，请指定默认审批人` : '尚无有效人员，请指定默认审批人' }}</p>
                    <p class="mt-1 min-h-10 break-words text-xs text-[var(--brand-muted)]">{{ role.required_default ? '来源：全校统一指定' : role.default_user_id ? '来源：岗位默认审批人' : `来源：角色匹配${role.role_users.length ? `（${role.role_users.join('、')}）` : ''}` }}</p>
                    <label :for="`role-${role.code}`" class="mt-4 block text-sm font-medium text-[var(--brand-body)]">{{ role.required_default ? '全局代表' : '默认审批人' }}</label>
                    <el-select :id="`role-${role.code}`" v-model="selected[role.code]" filterable :clearable="!role.required_default" placeholder="按姓名、工号搜索" class="mt-2 w-full">
                        <template #label="{ value }">{{ userName(value) }}</template>
                        <el-option v-for="user in users" :key="user.id" :value="user.id" :label="`${user.name} · ${user.school_account ?? '无工号'} · ${user.department_name ?? '无部门'}`" />
                    </el-select>
                    <p v-if="errors[role.code]" role="alert" class="mt-2 text-xs text-[var(--brand-danger)]">{{ errors[role.code] }}</p>
                    <button type="button" :disabled="saving[role.code] || selected[role.code] === role.default_user_id || (role.required_default && !selected[role.code])" class="mt-4 min-h-10 rounded-[var(--brand-radius-md)] bg-[var(--brand-primary)] px-5 text-sm text-[var(--brand-on-primary)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--brand-primary)] disabled:opacity-50" @click="saveRole(role)">{{ saving[role.code] ? '保存中…' : '保存此岗位' }}</button>
                </article>
            </div>
        </section>

        <section id="department-mapping" class="mt-[var(--brand-space-32)] scroll-mt-6 rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)] shadow-sm">
            <h2 class="text-xl font-semibold text-[var(--brand-title)]">部门审批人映射</h2>
            <p class="mt-2 text-sm text-[var(--brand-muted)]">按申请部门设置经费分管院领导；招投标领导小组代表在上方全校统一设置。</p>
            <form class="mt-[var(--brand-space-16)] grid gap-[var(--brand-space-16)] md:grid-cols-2" @submit.prevent="saveMapping">
                <label class="block text-sm text-[var(--brand-body)]">申请部门
                    <el-select v-model="mapping.department_id" filterable placeholder="选择部门" class="mt-2 w-full" @change="selectDepartment">
                        <el-option v-for="department in departments" :key="department.id" :value="department.id" :label="department.name" />
                    </el-select>
                </label>
                <div class="hidden md:block" />
                <label class="block text-sm text-[var(--brand-body)]">经费分管院领导
                    <el-select v-model="mapping.supervising_leader_id" filterable placeholder="选择人员" class="mt-2 w-full">
                        <template #label="{ value }">{{ userName(value) }}</template>
                        <el-option v-for="user in users" :key="user.id" :value="user.id" :label="`${user.name} · ${user.school_account ?? '无工号'} · ${user.department_name ?? '无部门'}`" />
                    </el-select>
                </label>
                <p v-for="error in mapping.errors" :key="error" role="alert" class="text-xs text-[var(--brand-danger)]">{{ error }}</p>
                <div class="md:col-span-2"><button type="submit" :disabled="mapping.processing || !mapping.department_id" class="min-h-10 rounded-[var(--brand-radius-md)] bg-[var(--brand-primary)] px-5 text-sm text-[var(--brand-on-primary)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--brand-primary)] disabled:opacity-50">{{ mapping.processing ? '保存中…' : '保存部门审批人' }}</button></div>
            </form>
            <div class="mt-[var(--brand-space-24)] overflow-x-auto border-t border-[var(--brand-border)] pt-[var(--brand-space-16)]">
                <table class="w-full min-w-[480px] text-left text-sm text-[var(--brand-body)]">
                    <thead class="bg-[var(--brand-soft)] text-xs text-[var(--brand-muted)]"><tr><th class="p-3">部门</th><th class="p-3">经费分管院领导</th><th class="p-3">状态</th><th class="p-3">操作</th></tr></thead>
                    <tbody class="divide-y divide-[var(--brand-border)]"><tr v-for="department in departments" :key="department.id"><td class="p-3 font-medium text-[var(--brand-title)]">{{ department.name }}</td><td class="p-3">{{ activeUserName(mappedDepartment(department.id)?.supervising_leader_id) || '未配置或账号停用' }}</td><td class="p-3" :class="mappingValid(department.id) ? 'text-[var(--brand-success)]' : 'text-[var(--brand-danger)]'">{{ mappingValid(department.id) ? '有效' : '待配置' }}</td><td class="p-3"><button type="button" class="text-[var(--brand-primary)] hover:underline focus-visible:outline-2 focus-visible:outline-[var(--brand-primary)]" @click="editDepartment(department.id)">设置</button></td></tr><tr v-if="!departments.length"><td colspan="4" class="p-6 text-center text-[var(--brand-muted)]">暂无部门</td></tr></tbody>
                </table>
            </div>
        </section>

        <section id="budget-indicators" class="mt-[var(--brand-space-32)] rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)] shadow-sm">
            <h2 class="text-xl font-semibold text-[var(--brand-title)]">经费负责人来源</h2>
            <p class="mt-2 text-sm text-[var(--brand-body)]">经费负责人按申请所选预算指标的负责人工号匹配，无全局指定人。当前同步预算指标 {{ budgetIndicatorSummary.total }} 条，其中 {{ budgetIndicatorSummary.unresolved }} 条未匹配到有效系统账号。</p>
            <p class="mt-2 text-sm text-[var(--brand-muted)]">如提交时提示经费负责人无效，请核对该预算指标的负责人工号并重新同步预算数据。</p>
        </section>
    </main>
</template>
