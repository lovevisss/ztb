<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';

type Project = {
    id: number;
    title: string;
    project_number: string;
    budget_cents: number;
    purchase_type: string;
    purchase_method: string;
    status: string;
    submitted_at: string;
};
type PaginationLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{
    projects: { data: Project[]; links: PaginationLink[]; total: number };
    filters: {
        search?: string;
        year?: number;
        purchase_method?: string;
        status?: string;
    };
    canManageArchiveRules: boolean;
}>();

const filters = useForm({
    search: props.filters.search ?? '',
    year: props.filters.year ? String(props.filters.year) : '',
    purchase_method: props.filters.purchase_method ?? '',
    status: props.filters.status ?? '',
});

function search() {
    filters.get('/procurement/history', { preserveState: true });
}

function copy(id: number) {
    router.post(`/procurement/history/${id}/copy`);
}

function paginationLabel(label: string) {
    return label.replace('&laquo;', '«').replace('&raquo;', '»');
}

const statusLabel: Record<string, string> = {
    submitted: '审批中',
    returned: '退回修改',
    rejected: '已驳回',
    approved: '审批通过',
    executing: '执行中',
    execution_completed: '执行已完成',
    archived: '已归档',
};
</script>

<template>
    <Head title="历史采购项目" />
    <main
        class="mx-auto w-full max-w-6xl px-[var(--brand-space-24)] py-[var(--brand-space-32)] md:px-[var(--brand-space-32)] md:py-[var(--brand-space-32)]"
    >
        <header
            class="border-b border-[var(--brand-border)] pb-[var(--brand-space-32)]"
        >
            <p
                class="mb-[var(--brand-space-8)] text-xs font-semibold tracking-[0.28em] text-[var(--brand-primary)]"
            >
                ARCHIVE / 项目检索
            </p>
            <h1
                class="font-sans text-3xl font-semibold text-[var(--brand-title)] md:text-4xl"
            >
                历史采购项目
            </h1>
            <p
                class="mt-[var(--brand-space-16)] text-sm text-[var(--brand-muted)]"
            >
                查阅授权范围内的项目，复制申请资料以发起新项目。
            </p>
        </header>
        <Link
            href="/procurement/statistics"
            class="mt-[var(--brand-space-16)] mr-[var(--brand-space-24)] inline-block text-sm text-[var(--brand-primary)] underline"
            >采购数据大屏 →</Link
        >
        <Link
            href="/procurement/legacy-history"
            class="mt-[var(--brand-space-16)] mr-[var(--brand-space-24)] inline-block text-sm text-[var(--brand-primary)] underline"
            >旧系统历史档案 →</Link
        >
        <Link
            v-if="canManageArchiveRules"
            href="/procurement/archive-rules"
            class="mt-[var(--brand-space-16)] inline-block text-sm text-[var(--brand-primary)] underline"
            >归档材料规则 →</Link
        >

        <form
            class="mt-[var(--brand-space-32)] grid gap-[var(--brand-space-16)] rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)] shadow-sm md:grid-cols-[2fr_1fr_1fr_1fr_auto]"
            @submit.prevent="search"
        >
            <el-input
                v-model="filters.search"
                placeholder="搜索项目名称"
                clearable
                aria-label="搜索项目名称"
            />
            <el-input
                v-model="filters.year"
                type="number"
                placeholder="年度"
                clearable
                aria-label="年度"
            />
            <el-input
                v-model="filters.purchase_method"
                placeholder="采购方式"
                clearable
                aria-label="采购方式"
            />
            <el-select
                v-model="filters.status"
                placeholder="全部状态"
                clearable
                aria-label="状态"
                ><el-option label="审批中" value="submitted" /><el-option
                    label="审批通过"
                    value="approved" /><el-option
                    label="退回修改"
                    value="returned" /><el-option
                    label="已驳回"
                    value="rejected" /><el-option
                    label="执行中"
                    value="executing" /><el-option
                    label="执行已完成"
                    value="execution_completed" /><el-option
                    label="已归档"
                    value="archived"
            /></el-select>
            <el-button native-type="submit" type="primary">查询</el-button>
        </form>

        <div
            class="mt-[var(--brand-space-24)] text-xs text-[var(--brand-muted)]"
        >
            共 {{ projects.total }} 个项目
        </div>
        <div
            v-if="projects.data.length === 0"
            class="mt-[var(--brand-space-16)] rounded-[var(--brand-radius-md)] border border-dashed border-[var(--brand-border)] bg-[var(--brand-page)] px-[var(--brand-space-24)] py-[var(--brand-space-32)] text-center text-[var(--brand-muted)]"
        >
            没有符合条件的项目
        </div>
        <div
            v-else
            class="mt-[var(--brand-space-16)] overflow-x-auto rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] bg-[var(--brand-card)] shadow-sm"
        >
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead
                    class="bg-[var(--brand-page)] text-xs font-semibold text-[var(--brand-muted)]"
                >
                    <tr>
                        <th
                            class="px-[var(--brand-space-24)] py-[var(--brand-space-16)]"
                        >
                            项目
                        </th>
                        <th
                            class="px-[var(--brand-space-24)] py-[var(--brand-space-16)]"
                        >
                            预算
                        </th>
                        <th
                            class="px-[var(--brand-space-24)] py-[var(--brand-space-16)]"
                        >
                            采购方式
                        </th>
                        <th
                            class="px-[var(--brand-space-24)] py-[var(--brand-space-16)]"
                        >
                            状态
                        </th>
                        <th
                            class="px-[var(--brand-space-24)] py-[var(--brand-space-16)]"
                        >
                            操作
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--brand-border)]">
                    <tr v-for="project in projects.data" :key="project.id">
                        <td
                            class="px-[var(--brand-space-24)] py-[var(--brand-space-24)]"
                        >
                            <Link
                                :href="
                                    project.status === 'archived'
                                        ? `/procurement/applications/${project.id}/archive`
                                        : `/procurement/applications/${project.id}`
                                "
                                class="font-medium text-[var(--brand-title)] hover:text-[var(--brand-primary)] hover:underline"
                                >{{ project.title }}</Link
                            >
                            <div
                                class="mt-[var(--brand-space-8)] font-mono text-xs text-[var(--brand-muted)]"
                            >
                                {{ project.project_number }}
                            </div>
                        </td>
                        <td
                            class="px-[var(--brand-space-24)] py-[var(--brand-space-24)] tabular-nums"
                        >
                            ¥{{
                                (project.budget_cents / 100).toLocaleString(
                                    'zh-CN',
                                )
                            }}
                        </td>
                        <td
                            class="px-[var(--brand-space-24)] py-[var(--brand-space-24)]"
                        >
                            {{ project.purchase_method }}
                        </td>
                        <td
                            class="px-[var(--brand-space-24)] py-[var(--brand-space-24)]"
                        >
                            {{ statusLabel[project.status] ?? project.status }}
                        </td>
                        <td
                            class="px-[var(--brand-space-24)] py-[var(--brand-space-24)]"
                        >
                            <el-button size="small" @click="copy(project.id)"
                                >复制为新申请</el-button
                            >
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <nav
            v-if="projects.links.length > 3"
            class="mt-[var(--brand-space-24)] flex flex-wrap gap-[var(--brand-space-8)]"
            aria-label="分页"
        >
            <Link
                v-for="(link, index) in projects.links"
                :key="index"
                v-show="link.url"
                :href="link.url ?? '#'"
                class="rounded border px-[var(--brand-space-16)] py-[var(--brand-space-8)] text-sm"
                :class="
                    link.active
                        ? 'border-[var(--brand-primary)] bg-[var(--brand-primary-light)] text-[var(--brand-primary)]'
                        : 'border-[var(--brand-border)] text-[var(--brand-body)]'
                "
                >{{ paginationLabel(link.label) }}</Link
            >
        </nav>
    </main>
</template>
