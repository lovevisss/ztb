<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

type Project = {
    id: number;
    project_number: string | null;
    title: string | null;
    applicant_name: string;
    status: string;
    submitted_at: string | null;
    attempt: number | null;
    current_role_code: string | null;
    current_assignee_name: string | null;
    current_step_status: string | null;
};
type PageLink = { url: string | null; label: string; active: boolean };
const props = defineProps<{
    projects: { data: Project[]; links: PageLink[]; total: number; current_page: number };
    filters: { search: string };
}>();
const search = ref(props.filters.search);
const roleLabel: Record<string, string> = {
    department_head: '部门负责人', usage_department_head: '使用部门负责人',
    procurement_manager: '采购管理部门', responsible_department: '归口部门',
    finance: '财务人员', school_leader: '校领导',
};
const statusLabel: Record<string, string> = {
    submitted: '审批中', returned: '退回修改', rejected: '已驳回',
    approved: '审批通过', executing: '执行中', execution_completed: '执行已完成',
    archived: '已归档',
};
function submitSearch() {
    router.get('/procurement/approval-progress', search.value.trim() ? { search: search.value.trim() } : {}, { preserveState: true });
}
function detailUrl(id: number) {
    const params = new URLSearchParams({ from: 'workflow' });
    if (props.filters.search) params.set('search', props.filters.search);
    if (props.projects.current_page > 1) params.set('page', String(props.projects.current_page));
    return `/procurement/applications/${id}?${params}`;
}
function paginationLabel(label: string) {
    return label.replace('&laquo;', '«').replace('&raquo;', '»');
}
</script>

<template>
    <Head title="流程进度" />
    <main class="mx-auto w-full max-w-6xl px-[var(--brand-space-24)] py-[var(--brand-space-32)] md:px-[var(--brand-space-32)]">
        <header class="border-b border-[var(--brand-border)] pb-[var(--brand-space-24)]">
            <h1 class="text-3xl font-semibold text-[var(--brand-title)]">流程进度</h1>
            <p class="mt-[var(--brand-space-8)] text-sm text-[var(--brand-muted)]">查询已发起的采购审批流程，查看节点进度并调整待处理审批人。</p>
        </header>
        <form class="mt-[var(--brand-space-24)] flex flex-wrap gap-[var(--brand-space-16)] rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)] shadow-sm" @submit.prevent="submitSearch">
            <label for="workflow-search" class="sr-only">搜索采购编号、名称或采购人</label>
            <input id="workflow-search" v-model="search" type="search" maxlength="255" placeholder="搜索采购编号、名称或采购人" class="min-h-10 min-w-0 flex-1 basis-64 rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] bg-[var(--brand-card)] px-[var(--brand-space-16)] text-sm text-[var(--brand-title)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--brand-primary)]" />
            <button type="submit" class="min-h-10 rounded-[var(--brand-radius-md)] bg-[var(--brand-primary)] px-[var(--brand-space-24)] text-sm font-semibold text-[var(--brand-on-primary)] hover:bg-[var(--brand-primary-hover)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--brand-primary)]">查询</button>
        </form>
        <p class="mt-[var(--brand-space-24)] text-sm text-[var(--brand-muted)]">共 {{ projects.total }} 个流程</p>
        <p v-if="!projects.data.length" class="mt-[var(--brand-space-16)] rounded-[var(--brand-radius-lg)] border border-dashed border-[var(--brand-border)] bg-[var(--brand-soft)] p-[var(--brand-space-32)] text-center text-sm text-[var(--brand-muted)]">没有符合条件的流程</p>
        <div v-else class="mt-[var(--brand-space-16)] overflow-x-auto rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] shadow-sm">
            <table class="w-full min-w-[820px] text-left text-sm">
                <thead class="bg-[var(--brand-soft)] text-xs text-[var(--brand-muted)]"><tr><th class="p-[var(--brand-space-16)]">采购项目</th><th class="p-[var(--brand-space-16)]">采购人</th><th class="p-[var(--brand-space-16)]">当前进度</th><th class="p-[var(--brand-space-16)]">项目状态</th><th class="p-[var(--brand-space-16)]">操作</th></tr></thead>
                <tbody class="divide-y divide-[var(--brand-border)] text-[var(--brand-body)]">
                    <tr v-for="project in projects.data" :key="project.id" class="hover:bg-[var(--brand-primary-light)]">
                        <td class="max-w-72 p-[var(--brand-space-16)]"><p class="break-words font-medium text-[var(--brand-title)]">{{ project.title || '未命名项目' }}</p><p class="mt-[var(--brand-space-8)] break-all font-mono text-xs text-[var(--brand-muted)]">{{ project.project_number || '暂无编号' }}</p></td>
                        <td class="p-[var(--brand-space-16)]">{{ project.applicant_name }}</td>
                        <td class="p-[var(--brand-space-16)]"><p>第 {{ project.attempt }} 轮 · {{ project.current_role_code ? (roleLabel[project.current_role_code] ?? project.current_role_code) : (statusLabel[project.status] ?? project.status) }}</p><p v-if="project.current_role_code" class="mt-[var(--brand-space-8)] text-xs text-[var(--brand-muted)]">{{ project.current_step_status === 'pending' ? '待审批' : '待流转' }} · {{ project.current_assignee_name || '未指定' }}</p></td>
                        <td class="p-[var(--brand-space-16)]">{{ statusLabel[project.status] ?? project.status }}</td>
                        <td class="p-[var(--brand-space-16)]"><Link :href="detailUrl(project.id)" class="font-medium text-[var(--brand-primary)] hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--brand-primary)]">查看流程</Link></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <nav v-if="projects.links.length > 3" aria-label="分页" class="mt-[var(--brand-space-24)] flex flex-wrap gap-[var(--brand-space-8)]">
            <Link v-for="(link, index) in projects.links" :key="index" v-show="link.url" :href="link.url ?? '#'" class="rounded-[var(--brand-radius-md)] border px-[var(--brand-space-16)] py-[var(--brand-space-8)] text-sm" :class="link.active ? 'border-[var(--brand-primary)] bg-[var(--brand-primary-light)] text-[var(--brand-primary)]' : 'border-[var(--brand-border)] text-[var(--brand-body)]'">{{ paginationLabel(link.label) }}</Link>
        </nav>
    </main>
</template>
