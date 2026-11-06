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
    <main class="mx-auto w-full max-w-6xl px-5 py-8 md:px-10 md:py-12">
        <header class="border-b border-slate-200 pb-7">
            <p
                class="mb-2 text-xs font-semibold tracking-[0.28em] text-emerald-700"
            >
                ARCHIVE / 项目检索
            </p>
            <h1
                class="font-serif text-3xl font-semibold text-slate-900 md:text-4xl"
            >
                历史采购项目
            </h1>
            <p class="mt-3 text-sm text-slate-500">
                查阅授权范围内的项目，复制申请资料以发起新项目。
            </p>
        </header>
        <Link
            href="/procurement/statistics"
            class="mt-4 mr-5 inline-block text-sm text-emerald-800 underline"
            >采购数据大屏 →</Link
        >
        <Link href="/procurement/legacy-history" class="mt-4 mr-5 inline-block text-sm text-emerald-800 underline">旧系统历史档案 →</Link>
        <Link
            v-if="canManageArchiveRules"
            href="/procurement/archive-rules"
            class="mt-4 inline-block text-sm text-emerald-800 underline"
            >归档材料规则 →</Link
        >

        <form
            class="mt-8 grid gap-3 rounded-lg border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-[2fr_1fr_1fr_1fr_auto]"
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

        <div class="mt-5 text-xs text-slate-500">
            共 {{ projects.total }} 个项目
        </div>
        <div
            v-if="projects.data.length === 0"
            class="mt-4 rounded-lg border border-dashed border-slate-300 bg-stone-50 px-6 py-16 text-center text-slate-500"
        >
            没有符合条件的项目
        </div>
        <div
            v-else
            class="mt-4 overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm"
        >
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
                    <tr>
                        <th class="px-5 py-4">项目</th>
                        <th class="px-5 py-4">预算</th>
                        <th class="px-5 py-4">采购方式</th>
                        <th class="px-5 py-4">状态</th>
                        <th class="px-5 py-4">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="project in projects.data" :key="project.id">
                        <td class="px-5 py-5">
                            <Link
                                :href="
                                    project.status === 'archived'
                                        ? `/procurement/applications/${project.id}/archive`
                                        : `/procurement/applications/${project.id}`
                                "
                                class="font-medium text-slate-900 hover:text-emerald-800 hover:underline"
                                >{{ project.title }}</Link
                            >
                            <div class="mt-1 font-mono text-xs text-slate-400">
                                {{ project.project_number }}
                            </div>
                        </td>
                        <td class="px-5 py-5 tabular-nums">
                            ¥{{
                                (project.budget_cents / 100).toLocaleString(
                                    'zh-CN',
                                )
                            }}
                        </td>
                        <td class="px-5 py-5">{{ project.purchase_method }}</td>
                        <td class="px-5 py-5">
                            {{ statusLabel[project.status] ?? project.status }}
                        </td>
                        <td class="px-5 py-5">
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
            class="mt-6 flex flex-wrap gap-2"
            aria-label="分页"
        >
            <Link
                v-for="(link, index) in projects.links"
                :key="index"
                v-show="link.url"
                :href="link.url ?? '#'"
                class="rounded border px-3 py-1 text-sm"
                :class="
                    link.active
                        ? 'border-emerald-700 bg-emerald-50 text-emerald-800'
                        : 'border-slate-200 text-slate-600'
                "
                >{{ paginationLabel(link.label) }}</Link
            >
        </nav>
    </main>
</template>
