<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';

type Department = {
    id: number; name: string; is_active: boolean;
    manual_user_id: number | null; manual_user_name: string | null; manual_user_active: boolean | null;
    default_user_id: number | null; default_user_name: string | null; effective_user_name: string | null;
};
type User = { id: number; name: string; school_account: string | null; department_name: string | null };
type Event = { department_name: string; actor_name: string; old_user_name: string | null; new_user_name: string | null; created_at: string };
const props = defineProps<{
    departments: Department[];
    users: User[];
    events: Event[];
    filters: { search: string };
}>();
const page = usePage();
const search = ref(props.filters.search);
const selected = reactive<Record<number, number | null>>({});
const saving = reactive<Record<number, boolean>>({});
const errors = reactive<Record<number, string>>({});
function syncSelections() {
    for (const department of props.departments) selected[department.id] = department.manual_user_id;
}
function selectedUserName(value: unknown): string {
    return props.users.find((user) => user.id === value)?.name
        ?? props.departments.find((department) => department.manual_user_id === value)?.manual_user_name
        ?? '';
}
syncSelections();
watch(() => props.departments, syncSelections);

function findDepartments() {
    router.get('/admin/department-heads', search.value.trim() ? { search: search.value.trim() } : {}, { preserveScroll: true });
}
function save(department: Department, userId: number | null = selected[department.id]) {
    if (saving[department.id]) return;
    saving[department.id] = true;
    delete errors[department.id];
    router.put(`/admin/department-heads/${department.id}`, { user_id: userId }, {
        preserveScroll: true,
        onError: (response) => { errors[department.id] = response.user_id ?? '保存失败，请重试。'; },
        onFinish: () => { saving[department.id] = false; },
    });
}
</script>

<template>
    <Head title="部门负责人设置" />
    <main class="mx-auto w-full max-w-7xl px-[var(--brand-space-24)] py-[var(--brand-space-32)]">
        <header class="border-b border-[var(--brand-border)] pb-[var(--brand-space-24)]">
            <h1 class="text-[28px] font-semibold text-[var(--brand-title)]">部门负责人设置</h1>
            <p class="mt-[var(--brand-space-8)] text-sm text-[var(--brand-muted)]">全局配置用于之后生成的采购审批。手动指定优先；未指定时按部门内 department_head 角色匹配。</p>
        </header>
        <nav aria-label="审批人配置分类" class="mt-[var(--brand-space-24)] flex flex-wrap gap-[var(--brand-space-8)] border-b border-[var(--brand-border)]">
            <span aria-current="page" class="rounded-t-[var(--brand-radius-md)] border-b-2 border-[var(--brand-primary)] px-4 py-3 text-sm font-semibold text-[var(--brand-primary)]">部门负责人</span>
            <Link href="/admin/approval-assignees" class="rounded-t-[var(--brand-radius-md)] px-4 py-3 text-sm text-[var(--brand-body)] hover:text-[var(--brand-primary)] focus-visible:outline-2 focus-visible:outline-[var(--brand-primary)]">其他审批人</Link>
        </nav>

        <div v-if="(page.props.flash as { status?: string } | undefined)?.status" role="status" class="mt-[var(--brand-space-24)] rounded-[var(--brand-radius-md)] border border-[var(--brand-success)] p-[var(--brand-space-16)] text-sm text-[var(--brand-success)]">
            {{ (page.props.flash as { status?: string }).status }}
        </div>

        <form class="mt-[var(--brand-space-24)] flex flex-wrap gap-[var(--brand-space-8)]" @submit.prevent="findDepartments">
            <input v-model="search" class="min-h-10 min-w-56 rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] bg-[var(--brand-card)] px-3 text-sm text-[var(--brand-body)] focus-visible:outline-2 focus-visible:outline-[var(--brand-primary)]" placeholder="搜索部门" aria-label="搜索部门" />
            <button type="submit" class="min-h-10 rounded-[var(--brand-radius-md)] bg-[var(--brand-primary)] px-5 text-sm text-[var(--brand-on-primary)]">搜索</button>
        </form>

        <div class="mt-[var(--brand-space-24)] overflow-x-auto rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)]">
            <table class="min-w-[780px] w-full text-left text-sm text-[var(--brand-body)]">
                <thead class="bg-[var(--brand-soft)] text-xs text-[var(--brand-muted)]"><tr><th class="p-4">部门</th><th class="p-4">角色匹配</th><th class="p-4">手动指定</th><th class="p-4">当前生效</th><th class="p-4">操作</th></tr></thead>
                <tbody class="divide-y divide-[var(--brand-border)]">
                    <tr v-for="department in departments" :key="department.id">
                        <td class="p-4 font-medium text-[var(--brand-title)]">{{ department.name }}<span v-if="!department.is_active" class="ml-2 text-xs text-[var(--brand-muted)]">已停用部门</span></td>
                        <td class="p-4">{{ department.default_user_name ?? '未配置' }}</td>
                        <td class="p-4">
                            <el-select v-model="selected[department.id]" filterable clearable placeholder="按姓名、工号搜索" class="w-64">
                                <template #label="{ value }">{{ selectedUserName(value) }}</template>
                                <el-option v-for="user in users" :key="user.id" :label="`${user.name} · ${user.school_account ?? '无工号'} · ${user.department_name ?? '无部门'}`" :value="user.id" />
                            </el-select>
                            <p v-if="department.manual_user_id && !department.manual_user_active" class="mt-1 text-xs text-[var(--brand-danger)]">当前指定账号已停用，请更换或清除。</p>
                            <p v-if="errors[department.id]" role="alert" class="mt-1 text-xs text-[var(--brand-danger)]">{{ errors[department.id] }}</p>
                        </td>
                        <td class="p-4"><span :class="department.effective_user_name ? 'text-[var(--brand-success)]' : 'text-[var(--brand-danger)]'">{{ department.effective_user_name ?? '无有效负责人' }}</span><span class="ml-2 text-xs text-[var(--brand-muted)]">{{ department.manual_user_id ? '手动' : '角色兜底' }}</span></td>
                        <td class="p-4"><div class="flex gap-2"><button type="button" :disabled="saving[department.id]" class="min-h-10 rounded-[var(--brand-radius-md)] bg-[var(--brand-primary)] px-4 text-sm text-[var(--brand-on-primary)] disabled:opacity-50" @click="save(department)">{{ saving[department.id] ? '保存中…' : '保存' }}</button><button v-if="department.manual_user_id" type="button" :disabled="saving[department.id]" class="min-h-10 rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] px-3 text-sm text-[var(--brand-primary)]" @click="selected[department.id] = null; save(department, null)">清除</button></div></td>
                    </tr>
                    <tr v-if="!departments.length"><td colspan="5" class="p-8 text-center text-[var(--brand-muted)]">没有匹配的部门</td></tr>
                </tbody>
            </table>
        </div>

        <section class="mt-[var(--brand-space-32)] rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)]">
            <h2 class="text-xl font-semibold text-[var(--brand-title)]">最近变更</h2>
            <ul class="mt-4 space-y-3 text-sm text-[var(--brand-body)]"><li v-for="(event, index) in events" :key="index" class="border-b border-[var(--brand-border)] pb-3">{{ event.department_name }}：{{ event.old_user_name ?? '未指定' }} → {{ event.new_user_name ?? '未指定' }} · {{ event.actor_name }} · {{ event.created_at }}</li><li v-if="!events.length" class="text-[var(--brand-muted)]">暂无变更记录</li></ul>
        </section>
    </main>
</template>
