<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';

type Project = {
    source: 'new' | 'legacy';
    source_id: number | string;
    number: string | null;
    title: string | null;
    department_name: string | null;
    applicant_name: string | null;
    budget_cents: number | null;
    status: string | null;
    project_date: string | null;
};
type PageLink = { url: string | null; label: string; active: boolean };
type Department = { id: number; name: string };
const props = defineProps<{
    projects: { data: Project[]; links: PageLink[]; total: number; current_page: number; last_page: number };
    filters: { search?: string; source?: string; year?: number; department_id?: number };
    departments: Department[];
    totals: { new: number; legacy: number };
}>();

const filters = useForm({
    search: props.filters.search ?? '',
    source: props.filters.source ?? '',
    year: props.filters.year?.toString() ?? '',
    department_id: props.filters.department_id?.toString() ?? '',
});

function search() {
    filters.get('/procurement/projects', { preserveState: true });
}
function reset() {
    router.get('/procurement/projects');
}
function detailHref(project: Project): string {
    if (project.source === 'legacy')
        return `/procurement/legacy-history/${encodeURIComponent(project.source_id)}`;
    return project.status === 'archived'
        ? `/procurement/applications/${project.source_id}/archive`
        : `/procurement/applications/${project.source_id}`;
}
function money(cents: number | null): string {
    return cents === null ? '—' : `¥${(cents / 100).toLocaleString('zh-CN', { minimumFractionDigits: 2 })}`;
}
function pageLabel(label: string): string {
    return label.replace('&laquo;', '«').replace('&raquo;', '»');
}
const newStatus: Record<string, string> = {
    submitted: '审批中', returned: '退回修改', withdrawn: '已撤回待修改',
    rejected: '已驳回', approved: '审批通过', executing: '执行中',
    execution_completed: '执行已完成', archived: '已归档',
};
function statusLabel(project: Project): string {
    return project.source === 'new' ? newStatus[project.status ?? ''] ?? (project.status || '—') : project.status || '—';
}
</script>

<template>
    <Head title="项目总览" />
    <main class="mx-auto w-full max-w-[1440px] px-[var(--brand-space-24)] py-[var(--brand-space-32)] md:px-[var(--brand-space-32)]">
        <header class="border-b border-[var(--brand-border)] pb-[var(--brand-space-24)]">
            <p class="text-xs font-semibold tracking-[0.24em] text-[var(--brand-primary)]">PROCUREMENT / 项目总览</p>
            <h1 class="mt-[var(--brand-space-8)] text-[28px] font-semibold leading-[1.3] text-[var(--brand-title)]">全校项目总览</h1>
            <p class="mt-[var(--brand-space-8)] text-sm text-[var(--brand-muted)]">查看全校已提交的新、旧系统项目及当前状态。未提交草稿仅在发起人的“采购申请”中显示。</p>
        </header>

        <div class="mt-[var(--brand-space-24)] grid gap-[var(--brand-space-16)] sm:grid-cols-3">
            <div class="rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)] shadow-[var(--brand-shadow)]">
                <p class="text-sm text-[var(--brand-muted)]">全校项目</p>
                <p class="mt-[var(--brand-space-8)] text-[28px] font-semibold tabular-nums text-[var(--brand-title)]">{{ (totals.new + totals.legacy).toLocaleString('zh-CN') }}</p>
            </div>
            <div class="rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)] shadow-[var(--brand-shadow)]">
                <p class="text-sm text-[var(--brand-muted)]">新系统</p>
                <p class="mt-[var(--brand-space-8)] text-[28px] font-semibold tabular-nums text-[var(--brand-title)]">{{ totals.new.toLocaleString('zh-CN') }}</p>
            </div>
            <div class="rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)] shadow-[var(--brand-shadow)]">
                <p class="text-sm text-[var(--brand-muted)]">旧系统</p>
                <p class="mt-[var(--brand-space-8)] text-[28px] font-semibold tabular-nums text-[var(--brand-title)]">{{ totals.legacy.toLocaleString('zh-CN') }}</p>
            </div>
        </div>

        <section class="mt-[var(--brand-space-24)] rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] shadow-[var(--brand-shadow)]">
            <form class="grid gap-[var(--brand-space-16)] border-b border-[var(--brand-border)] p-[var(--brand-space-24)] sm:grid-cols-2 xl:grid-cols-[minmax(220px,2fr)_minmax(120px,1fr)_minmax(120px,1fr)_minmax(180px,1.3fr)_auto] xl:items-end" @submit.prevent="search">
                <label class="block text-sm text-[var(--brand-body)]">项目名称、编号或申请人
                    <el-input v-model="filters.search" class="mt-[var(--brand-space-8)] w-full" clearable placeholder="输入关键词" />
                </label>
                <label class="block text-sm text-[var(--brand-body)]">来源
                    <select v-model="filters.source" class="mt-[var(--brand-space-8)] min-h-10 w-full rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] bg-[var(--brand-card)] px-3 text-sm text-[var(--brand-body)] focus-visible:outline-2 focus-visible:outline-[var(--brand-primary)]">
                        <option value="">全部来源</option><option value="new">新系统</option><option value="legacy">旧系统</option>
                    </select>
                </label>
                <label class="block text-sm text-[var(--brand-body)]">年度
                    <input v-model="filters.year" type="number" min="2000" max="2100" class="mt-[var(--brand-space-8)] min-h-10 w-full rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] bg-[var(--brand-card)] px-3 text-sm text-[var(--brand-body)] focus-visible:outline-2 focus-visible:outline-[var(--brand-primary)]" placeholder="全部年份" />
                </label>
                <label class="block text-sm text-[var(--brand-body)]">申报部门
                    <select v-model="filters.department_id" class="mt-[var(--brand-space-8)] min-h-10 w-full rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] bg-[var(--brand-card)] px-3 text-sm text-[var(--brand-body)] focus-visible:outline-2 focus-visible:outline-[var(--brand-primary)]">
                        <option value="">全部部门</option><option v-for="department in departments" :key="department.id" :value="String(department.id)">{{ department.name }}</option>
                    </select>
                </label>
                <div class="flex items-center gap-[var(--brand-space-8)] sm:col-span-2 xl:col-span-1">
                    <button type="submit" class="min-h-10 rounded-[var(--brand-radius-md)] bg-[var(--brand-primary)] px-5 text-sm font-semibold text-[var(--brand-on-primary)] hover:bg-[var(--brand-primary-hover)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--brand-primary)]">查询</button>
                    <button type="button" class="min-h-10 rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] px-4 text-sm text-[var(--brand-body)] hover:bg-[var(--brand-soft)] focus-visible:outline-2 focus-visible:outline-[var(--brand-primary)]" @click="reset">重置</button>
                </div>
            </form>

            <div class="flex items-center justify-between border-b border-[var(--brand-border)] px-[var(--brand-space-24)] py-[var(--brand-space-16)] text-sm text-[var(--brand-muted)]">
                <span>符合条件 {{ projects.total.toLocaleString('zh-CN') }} 个项目</span>
                <span>第 {{ projects.current_page }} / {{ projects.last_page }} 页</span>
            </div>
            <div class="overflow-x-auto" role="region" aria-label="全校项目列表" tabindex="0">
                <table class="w-full min-w-[1080px] text-left text-sm text-[var(--brand-body)]">
                    <thead class="bg-[var(--brand-soft)] text-xs text-[var(--brand-muted)]"><tr>
                        <th class="px-[var(--brand-space-24)] py-[var(--brand-space-16)]">项目</th>
                        <th class="px-[var(--brand-space-16)] py-[var(--brand-space-16)]">来源</th>
                        <th class="px-[var(--brand-space-16)] py-[var(--brand-space-16)]">申报部门</th>
                        <th class="px-[var(--brand-space-16)] py-[var(--brand-space-16)]">申请人</th>
                        <th class="px-[var(--brand-space-16)] py-[var(--brand-space-16)]">预算</th>
                        <th class="px-[var(--brand-space-16)] py-[var(--brand-space-16)]">状态</th>
                        <th class="px-[var(--brand-space-16)] py-[var(--brand-space-16)]">项目日期</th>
                    </tr></thead>
                    <tbody class="divide-y divide-[var(--brand-border)]">
                        <tr v-for="project in projects.data" :key="`${project.source}-${project.source_id}`" class="hover:bg-[var(--brand-primary-light)]">
                            <td class="max-w-80 px-[var(--brand-space-24)] py-[var(--brand-space-16)]">
                                <Link :href="detailHref(project)" class="font-medium text-[var(--brand-title)] hover:text-[var(--brand-primary)] hover:underline focus-visible:outline-2 focus-visible:outline-[var(--brand-primary)]">{{ project.title || '未命名项目' }}</Link>
                                <p class="mt-1 font-mono text-xs text-[var(--brand-muted)]">{{ project.number || '—' }}</p>
                            </td>
                            <td class="px-[var(--brand-space-16)]"><span class="rounded-[var(--brand-radius-sm)] bg-[var(--brand-primary-light)] px-2 py-1 text-xs text-[var(--brand-primary)]">{{ project.source === 'new' ? '新系统' : '旧系统' }}</span></td>
                            <td class="max-w-48 px-[var(--brand-space-16)]">{{ project.department_name || '—' }}</td>
                            <td class="px-[var(--brand-space-16)]">{{ project.applicant_name || '—' }}</td>
                            <td class="whitespace-nowrap px-[var(--brand-space-16)] tabular-nums">{{ money(project.budget_cents) }}</td>
                            <td class="px-[var(--brand-space-16)]">{{ statusLabel(project) }}</td>
                            <td class="whitespace-nowrap px-[var(--brand-space-16)]">{{ project.project_date?.slice(0, 10) || '—' }}</td>
                        </tr>
                        <tr v-if="!projects.data.length"><td colspan="7" class="px-[var(--brand-space-24)] py-[var(--brand-space-32)] text-center text-[var(--brand-muted)]">没有符合条件的项目</td></tr>
                    </tbody>
                </table>
            </div>
            <nav v-if="projects.links.length > 3" class="flex flex-wrap gap-[var(--brand-space-8)] border-t border-[var(--brand-border)] px-[var(--brand-space-24)] py-[var(--brand-space-16)]" aria-label="项目分页">
                <Link v-for="(link, index) in projects.links" :key="index" v-show="link.url" :href="link.url || '#'" class="rounded-[var(--brand-radius-md)] border px-3 py-2 text-sm" :class="link.active ? 'border-[var(--brand-primary)] bg-[var(--brand-primary-light)] text-[var(--brand-primary)]' : 'border-[var(--brand-border)] text-[var(--brand-body)] hover:bg-[var(--brand-soft)]'">{{ pageLabel(link.label) }}</Link>
            </nav>
        </section>
    </main>
</template>
