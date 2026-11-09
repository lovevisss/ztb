<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

type Tab = 'draft' | 'submitted';
type Record = {
    key: string; id: number | null; source_id: string | null; source: string;
    application_form: string | null; number: string | null; title: string | null;
    department_name: string | null; amount_cents: number | null; submitted_at: string | null;
    applicant_name: string | null; contact_phone: string | null; status: string; tab: Tab;
};
type Filters = { tab?: Tab; number?: string; title?: string; department_name?: string; applicant_name?: string; status?: string };
const props = defineProps<{
    records: Record[]; total: number; page: number; lastPage: number;
    counts: { draft: number; submitted: number }; filters: Filters;
}>();
const fields = reactive({
    number: props.filters.number ?? '', title: props.filters.title ?? '',
    department_name: props.filters.department_name ?? '', applicant_name: props.filters.applicant_name ?? '',
    status: props.filters.status ?? '',
});
const tab = computed<Tab>(() => props.filters.tab ?? 'submitted');
const fieldLabels: { key: keyof typeof fields; label: string }[] = [
    { key: 'number', label: '项目编号' }, { key: 'title', label: '项目名称' },
    { key: 'department_name', label: '申报部门' }, { key: 'applicant_name', label: '申请人' },
    { key: 'status', label: '流程状态' },
];
function query(nextTab: Tab = tab.value, page = 1, reset = false) {
    const params = new URLSearchParams({ tab: nextTab });
    if (!reset) for (const field of fieldLabels) {
        const value = fields[field.key].trim();
        if (value) params.set(field.key, value);
    }
    if (page > 1) params.set('page', String(page));
    return params.toString();
}
function navigate(nextTab: Tab = tab.value, page = 1, reset = false) {
    if (reset) for (const field of fieldLabels) fields[field.key] = '';
    router.get(`/procurement/applications?${query(nextTab, page, reset)}`, {}, { preserveState: true, preserveScroll: true });
}
const exportHref = computed(() => {
    const params = new URLSearchParams({ tab: tab.value });
    for (const field of fieldLabels) {
        const value = props.filters[field.key]?.trim();
        if (value) params.set(field.key, value);
    }
    return `/procurement/applications/export?${params.toString()}`;
});
const currency = (cents: number | null) => cents === null ? '—' : `¥${(cents / 100).toLocaleString('zh-CN', { minimumFractionDigits: 2 })}`;
function actionHref(item: Record) {
    if (item.source_id !== null) return `/procurement/applications?legacy=${encodeURIComponent(item.source_id)}`;
    if (item.tab === 'draft') return item.application_form === 'central_tender'
        ? `/procurement/tenders/${item.id}/edit` : `/procurement/applications/${item.id}/edit`;
    return `/procurement/applications/${item.id}`;
}
function detailHref(item: Record) {
    return item.source_id !== null
        ? `/procurement/applications?legacy=${encodeURIComponent(item.source_id)}`
        : `/procurement/applications/${item.id}`;
}
function actionLabel(item: Record) {
    return item.source_id !== null ? '查看档案' : item.tab === 'draft'
        ? item.status === '退回修改' ? '修改申请' : '继续编辑' : '查看进度';
}
</script>

<template>
    <Head title="我的采购申请" />
    <main class="mx-auto w-full max-w-7xl px-[var(--brand-space-24)] py-[var(--brand-space-32)]">
        <header class="mb-8 flex flex-wrap items-end justify-between gap-6 border-b border-[var(--brand-border)] pb-6">
            <div>
                <p class="mb-2 text-xs font-semibold tracking-[0.28em] text-[var(--brand-primary)]">PROCUREMENT / 采购管理</p>
                <h1 class="text-3xl font-semibold text-[var(--brand-title)]">我的采购申请</h1>
                <p class="mt-2 text-sm text-[var(--brand-muted)]">查看新系统申请和本人发起的旧系统申请。</p>
            </div>
            <div class="flex flex-wrap gap-4">
                <Link href="/procurement/applications/create" class="rounded-[var(--brand-radius-md)] border border-[var(--brand-primary)] px-5 py-3 text-sm text-[var(--brand-primary)]">部门自行采购</Link>
                <Link href="/procurement/tenders/create" class="rounded-[var(--brand-radius-md)] bg-[var(--brand-primary)] px-5 py-3 text-sm text-white">统一招标 ↗</Link>
            </div>
        </header>
        <section class="rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] bg-[var(--brand-card)] shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--brand-border)] px-6 pt-4">
                <div class="flex gap-6 text-sm font-medium">
                    <button v-for="item in ([{ key: 'draft', name: '未提交' }, { key: 'submitted', name: '已提交' }] as const)" :key="item.key" type="button" class="border-b-2 px-2 pb-4" :class="tab === item.key ? 'border-[var(--brand-primary)] text-[var(--brand-primary)]' : 'border-transparent text-[var(--brand-muted)]'" @click="navigate(item.key)">{{ item.name }} <span class="ml-1 rounded-full bg-[var(--brand-page)] px-2 py-1 text-xs">{{ counts[item.key] }}</span></button>
                </div>
                <a :href="exportHref" class="mb-3 rounded-[var(--brand-radius-md)] border border-[var(--brand-primary)] px-4 py-2 text-sm text-[var(--brand-primary)] hover:bg-[var(--brand-primary-light)]">导出当前筛选结果 Excel</a>
            </div>
            <form class="grid gap-3 border-b border-[var(--brand-border)] px-6 py-5 sm:grid-cols-2 lg:grid-cols-5" @submit.prevent="navigate()">
                <label v-for="field in fieldLabels" :key="field.key" class="text-xs font-medium text-[var(--brand-muted)]">{{ field.label }}
                    <input v-model="fields[field.key]" type="search" :placeholder="`搜索${field.label}`" class="mt-2 w-full rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] bg-white px-3 py-2 text-sm text-[var(--brand-body)] outline-none focus:border-[var(--brand-primary)]" />
                </label>
                <div class="flex gap-2 sm:col-span-2 lg:col-span-5">
                    <button type="submit" class="rounded-[var(--brand-radius-md)] bg-[var(--brand-primary)] px-5 py-2 text-sm text-white">查询</button>
                    <button type="button" class="rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] px-5 py-2 text-sm text-[var(--brand-body)]" @click="navigate(tab, 1, true)">重置</button>
                </div>
            </form>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1100px] text-left text-sm">
                    <thead class="bg-[var(--brand-page)] text-xs text-[var(--brand-muted)]"><tr>
                        <th v-for="heading in ['项目编号', '项目名称', '申报部门', '申请金额', '提交日期', '申请人', '申请人电话', '流程状态', '来源', '操作']" :key="heading" class="px-4 py-3">{{ heading }}</th>
                    </tr></thead>
                    <tbody class="divide-y divide-[var(--brand-border)]">
                        <tr v-for="item in records" :key="item.key" class="text-[var(--brand-body)] hover:bg-[var(--brand-page)]">
                            <td class="px-4 py-4 font-mono text-xs">{{ item.number || '—' }}</td>
                            <td class="px-4 py-4 font-medium"><Link :href="detailHref(item)" class="text-[var(--brand-primary)] hover:underline">{{ item.title || '未命名项目' }}</Link></td>
                            <td class="px-4 py-4">{{ item.department_name || '—' }}</td>
                            <td class="px-4 py-4 tabular-nums">{{ currency(item.amount_cents) }}</td>
                            <td class="px-4 py-4">{{ item.submitted_at?.slice(0, 10) || '—' }}</td>
                            <td class="px-4 py-4">{{ item.applicant_name || '—' }}</td>
                            <td class="px-4 py-4">{{ item.contact_phone || '—' }}</td>
                            <td class="px-4 py-4"><span class="rounded-full bg-[var(--brand-primary-light)] px-2 py-1 text-xs text-[var(--brand-primary)]">{{ item.status }}</span></td>
                            <td class="px-4 py-4">{{ item.source }}</td>
                            <td class="px-4 py-4"><Link :href="actionHref(item)" class="whitespace-nowrap text-[var(--brand-primary)] hover:underline">{{ actionLabel(item) }}</Link></td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="records.length === 0" class="px-6 py-10 text-center text-sm text-[var(--brand-muted)]">当前条件下没有申请</p>
            </div>
            <div class="flex items-center justify-between gap-3 border-t border-[var(--brand-border)] px-6 py-4 text-sm text-[var(--brand-muted)]">
                <span>共 {{ total }} 条 · 第 {{ page }} / {{ lastPage }} 页</span>
                <div class="flex gap-2"><button type="button" :disabled="page <= 1" class="rounded border border-[var(--brand-border)] px-3 py-1 disabled:opacity-40" @click="navigate(tab, page - 1)">上一页</button><button type="button" :disabled="page >= lastPage" class="rounded border border-[var(--brand-border)] px-3 py-1 disabled:opacity-40" @click="navigate(tab, page + 1)">下一页</button></div>
            </div>
        </section>
    </main>
</template>
