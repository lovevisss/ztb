<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

type Project = {
    source_id: string;
    number: string | null;
    title: string | null;
    status: string | null;
    amount_cents: number | null;
    occurred_at: string | null;
    creator_name: string | null;
    department_code: string | null;
};
type PageLink = { url: string | null; label: string; active: boolean };
const props = defineProps<{
    projects: { data: Project[]; links: PageLink[]; total: number };
    filters: { search?: string; year?: number };
    summary: { projects_count: number; budget_cents: number };
    byYear: { year: number; projects_count: number; budget_cents: number }[];
}>();
const search = ref(props.filters.search ?? '');
const year = ref(props.filters.year?.toString() ?? '');
function submit() {
    router.get(
        '/procurement/legacy-history',
        { search: search.value, year: year.value },
        { preserveState: true },
    );
}
function money(cents: number | null) {
    return cents === null
        ? '—'
        : `¥${(cents / 100).toLocaleString('zh-CN', { minimumFractionDigits: 2 })}`;
}
</script>

<template>
    <Head title="旧系统采购历史" />
    <main
        class="mx-auto max-w-6xl px-[var(--brand-space-24)] py-[var(--brand-space-32)] md:px-[var(--brand-space-32)]"
    >
        <Link
            href="/procurement/history"
            class="text-sm text-[var(--brand-primary)] underline"
            >← 返回新系统项目</Link
        >
        <h1
            class="mt-[var(--brand-space-24)] font-sans text-3xl font-semibold text-[var(--brand-title)]"
        >
            旧系统采购历史
        </h1>
        <p class="mt-[var(--brand-space-8)] text-sm text-[var(--brand-muted)]">
            仅供查询和统计；旧项目不会进入新系统审批流程。
        </p>
        <form
            class="mt-[var(--brand-space-32)] flex flex-wrap gap-[var(--brand-space-16)] rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)]"
            @submit.prevent="submit"
        >
            <el-input
                v-model="search"
                class="max-w-sm"
                placeholder="项目名称或原编号"
                clearable
            />
            <el-input
                v-model="year"
                class="max-w-36"
                type="number"
                placeholder="年份"
                clearable
            />
            <el-button native-type="submit" type="primary">查询</el-button>
        </form>
        <div
            class="mt-[var(--brand-space-24)] grid gap-[var(--brand-space-16)] sm:grid-cols-2"
        >
            <div
                class="rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)]"
            >
                <p class="text-sm text-[var(--brand-muted)]">申请数量</p>
                <p class="mt-[var(--brand-space-8)] text-2xl font-semibold">
                    {{ summary.projects_count }}
                </p>
            </div>
            <div
                class="rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)]"
            >
                <p class="text-sm text-[var(--brand-muted)]">申请预算合计</p>
                <p class="mt-[var(--brand-space-8)] text-2xl font-semibold">
                    {{ money(summary.budget_cents) }}
                </p>
            </div>
        </div>
        <div
            class="mt-[var(--brand-space-16)] flex flex-wrap gap-[var(--brand-space-8)] text-sm text-[var(--brand-body)]"
        >
            <span
                v-for="period in byYear"
                :key="period.year"
                class="rounded-full bg-[var(--brand-page)] px-[var(--brand-space-16)] py-[var(--brand-space-8)]"
                >{{ period.year }}：{{ period.projects_count }} 项</span
            >
        </div>
        <p class="mt-[var(--brand-space-24)] text-sm text-[var(--brand-muted)]">
            共 {{ projects.total }} 条申请
        </p>
        <div
            class="mt-[var(--brand-space-16)] overflow-x-auto rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] bg-[var(--brand-card)]"
        >
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead class="bg-[var(--brand-page)] text-[var(--brand-muted)]">
                    <tr>
                        <th
                            class="px-[var(--brand-space-16)] py-[var(--brand-space-16)]"
                        >
                            项目
                        </th>
                        <th>原编号</th>
                        <th>预算</th>
                        <th>原状态</th>
                        <th>创建人</th>
                        <th>日期</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--brand-border)]">
                    <tr
                        v-for="project in projects.data"
                        :key="project.source_id"
                    >
                        <td
                            class="px-[var(--brand-space-16)] py-[var(--brand-space-16)]"
                        >
                            <Link
                                :href="`/procurement/legacy-history/${encodeURIComponent(project.source_id)}`"
                                class="text-[var(--brand-primary)] underline"
                                >{{ project.title || '未命名项目' }}</Link
                            >
                        </td>
                        <td>{{ project.number || '—' }}</td>
                        <td>{{ money(project.amount_cents) }}</td>
                        <td>{{ project.status || '—' }}</td>
                        <td>{{ project.creator_name || '—' }}</td>
                        <td>{{ project.occurred_at?.slice(0, 10) || '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <nav
            class="mt-[var(--brand-space-24)] flex flex-wrap gap-[var(--brand-space-8)]"
        >
            <Link
                v-for="(link, index) in projects.links"
                :key="index"
                :href="link.url || '#'"
                :class="[
                    'rounded border px-[var(--brand-space-16)] py-[var(--brand-space-8)] text-sm',
                    link.active
                        ? 'border-[var(--brand-primary)] bg-[var(--brand-primary-light)] text-[var(--brand-primary)]'
                        : 'border-[var(--brand-border)]',
                    !link.url ? 'pointer-events-none opacity-40' : '',
                ]"
                v-html="link.label"
            />
        </nav>
    </main>
</template>
