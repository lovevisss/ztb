<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

type Tab = 'draft' | 'submitted';
type RegularFilter = 'all' | 'regular' | 'other';
type Record = {
    key: string; id: number | null; source_id: string | null; source: string;
    application_form: string | null; number: string | null; title: string | null;
    department_name: string | null; amount_cents: number | null; submitted_at: string | null;
    applicant_name: string | null; contact_phone: string | null; status: string; tab: Tab;
    year: number | null; is_regular: boolean; matching_years: number[];
};
type Filters = { tab?: Tab; number?: string; title?: string; department_name?: string; applicant_name?: string; status?: string; year?: number; regular?: RegularFilter };
const props = defineProps<{
    records: Record[]; total: number; page: number; lastPage: number;
    counts: { draft: number; submitted: number }; years: number[]; filters: Filters;
}>();
const fields = reactive({
    number: props.filters.number ?? '', title: props.filters.title ?? '',
    department_name: props.filters.department_name ?? '', applicant_name: props.filters.applicant_name ?? '',
    status: props.filters.status ?? '',
    year: props.filters.year === undefined ? '' : String(props.filters.year),
    regular: props.filters.regular ?? 'all' as RegularFilter,
});
const tab = computed<Tab>(() => props.filters.tab ?? 'submitted');
const fieldLabels: { key: 'number' | 'title' | 'department_name' | 'applicant_name' | 'status'; label: string }[] = [
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
    if (!reset && fields.year) params.set('year', fields.year);
    if (!reset && fields.regular !== 'all') params.set('regular', fields.regular);
    if (page > 1) params.set('page', String(page));
    return params.toString();
}
function navigate(nextTab: Tab = tab.value, page = 1, reset = false) {
    if (reset) {
        for (const field of fieldLabels) fields[field.key] = '';
        fields.year = '';
        fields.regular = 'all';
    }
    router.get(`/procurement/applications?${query(nextTab, page, reset)}`, {}, { preserveState: true, preserveScroll: true });
}
const exportHref = computed(() => {
    const params = new URLSearchParams({ tab: tab.value });
    for (const field of fieldLabels) {
        const value = props.filters[field.key]?.trim();
        if (value) params.set(field.key, value);
    }
    if (props.filters.year) params.set('year', String(props.filters.year));
    if (props.filters.regular && props.filters.regular !== 'all') params.set('regular', props.filters.regular);
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
    <main class="my-applications-page mx-auto w-full px-[var(--brand-space-24)] py-[var(--brand-space-32)]">
        <header class="mb-8 flex flex-wrap items-end justify-between gap-6 border-b border-[var(--brand-border)] pb-6">
            <div>
                <p class="mb-2 text-xs font-semibold tracking-[0.28em] text-[var(--brand-primary)]">PROCUREMENT / 采购管理</p>
                <h1 class="text-3xl font-semibold text-[var(--brand-title)]">我的采购申请</h1>
                <p class="mt-2 text-sm text-[var(--brand-muted)]">查看新系统申请和本人发起的旧系统申请。</p>
            </div>
            <div class="flex flex-wrap gap-4">
                <Link href="/procurement/applications/create" class="rounded-[var(--brand-radius-md)] border border-[var(--brand-primary)] px-5 py-3 text-sm text-[var(--brand-primary)]">部门自行采购</Link>
                <Link href="/procurement/tenders/create" class="rounded-[var(--brand-radius-md)] bg-[var(--brand-primary)] px-5 py-3 text-sm text-[var(--brand-on-primary)]">统一招标 ↗</Link>
            </div>
        </header>
        <section class="min-w-0 rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] shadow-[var(--brand-shadow)]">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--brand-border)] px-6 pt-4">
                <div class="flex gap-6 text-sm font-medium">
                    <button v-for="item in ([{ key: 'draft', name: '未提交' }, { key: 'submitted', name: '已提交' }] as const)" :key="item.key" type="button" class="border-b-2 px-2 pb-4" :class="tab === item.key ? 'border-[var(--brand-primary)] text-[var(--brand-primary)]' : 'border-transparent text-[var(--brand-muted)]'" @click="navigate(item.key)">{{ item.name }} <span class="ml-1 rounded-full bg-[var(--brand-soft)] px-2 py-1 text-xs">{{ counts[item.key] }}</span></button>
                </div>
                <a :href="exportHref" class="mb-3 rounded-[var(--brand-radius-md)] border border-[var(--brand-primary)] px-4 py-2 text-sm text-[var(--brand-primary)] hover:bg-[var(--brand-primary-light)]">导出当前筛选结果 Excel</a>
            </div>
            <form class="grid gap-3 border-b border-[var(--brand-border)] px-6 py-5 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7" @submit.prevent="navigate()">
                <label v-for="field in fieldLabels" :key="field.key" class="text-xs font-medium text-[var(--brand-muted)]">{{ field.label }}
                    <input v-model="fields[field.key]" type="search" :placeholder="`搜索${field.label}`" class="mt-2 w-full rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] px-3 py-2 text-sm text-[var(--brand-body)] outline-none focus:border-[var(--brand-primary)]" />
                </label>
                <label class="text-xs font-medium text-[var(--brand-muted)]">所属年度
                    <select v-model="fields.year" class="mt-2 w-full rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] px-3 py-2 text-sm text-[var(--brand-body)] outline-none focus:border-[var(--brand-primary)]">
                        <option value="">全部年份</option><option v-for="year in years" :key="year" :value="String(year)">{{ year }} 年</option>
                    </select>
                </label>
                <label class="text-xs font-medium text-[var(--brand-muted)]">常规项目
                    <select v-model="fields.regular" class="mt-2 w-full rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] px-3 py-2 text-sm text-[var(--brand-body)] outline-none focus:border-[var(--brand-primary)]">
                        <option value="all">全部</option><option value="regular">仅常规</option><option value="other">非常规</option>
                    </select>
                </label>
                <div class="flex gap-2 sm:col-span-2 lg:col-span-4 xl:col-span-7">
                    <button type="submit" class="rounded-[var(--brand-radius-md)] bg-[var(--brand-primary)] px-5 py-2 text-sm text-[var(--brand-on-primary)]">查询</button>
                    <button type="button" class="rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] px-5 py-2 text-sm text-[var(--brand-body)]" @click="navigate(tab, 1, true)">重置</button>
                </div>
            </form>
            <div class="overflow-x-auto" role="region" aria-label="采购申请列表" tabindex="0">
                <table class="applications-table w-full text-left text-sm">
                    <colgroup>
                        <col style="width: 175px" /><col style="width: 280px" /><col style="width: 235px" />
                        <col style="width: 140px" /><col style="width: 120px" /><col style="width: 90px" />
                        <col style="width: 130px" /><col style="width: 105px" /><col style="width: 85px" />
                        <col style="width: 85px" /><col style="width: 100px" />
                    </colgroup>
                    <thead class="bg-[var(--brand-soft)] text-xs text-[var(--brand-muted)]"><tr>
                        <th v-for="heading in ['项目编号', '项目名称', '申报部门', '申请金额', '提交日期', '申请人', '申请人电话', '流程状态', '来源', '年度', '操作']" :key="heading" class="px-4 py-3">{{ heading }}</th>
                    </tr></thead>
                    <tbody class="divide-y divide-[var(--brand-border)]">
                        <tr v-for="item in records" :key="item.key" class="text-[var(--brand-body)] hover:bg-[var(--brand-primary-light)]" :class="{ 'regular-project': item.is_regular }">
                            <td class="px-4 py-4 font-mono text-xs">{{ item.number || '—' }}</td>
                            <td class="px-4 py-4 font-medium"><Link :href="detailHref(item)" class="text-[var(--brand-primary)] hover:underline">{{ item.title || '未命名项目' }}</Link>
                                <div v-if="item.is_regular" class="mt-2 flex flex-wrap items-center gap-1.5 text-xs"><span class="rounded-[var(--brand-radius-sm)] bg-[var(--brand-primary-light)] px-2 py-1 font-semibold text-[var(--brand-primary)]">常规项目</span><span class="text-[var(--brand-muted)]">匹配 {{ item.matching_years.join('、') }} 年</span></div>
                            </td>
                            <td class="px-4 py-4">{{ item.department_name || '—' }}</td>
                            <td class="px-4 py-4 tabular-nums">{{ currency(item.amount_cents) }}</td>
                            <td class="px-4 py-4">{{ item.submitted_at?.slice(0, 10) || '—' }}</td>
                            <td class="px-4 py-4">{{ item.applicant_name || '—' }}</td>
                            <td class="px-4 py-4">{{ item.contact_phone || '—' }}</td>
                            <td class="px-4 py-4"><span class="rounded-full bg-[var(--brand-primary-light)] px-2 py-1 text-xs text-[var(--brand-primary)]">{{ item.status }}</span></td>
                            <td class="px-4 py-4">{{ item.source }}</td>
                            <td class="px-4 py-4 tabular-nums">{{ item.year || '—' }}</td>
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

<style scoped>
.my-applications-page {
    max-width: none;
}

.applications-table {
    min-width: 1550px;
    table-layout: fixed;
}

.applications-table th,
.applications-table td {
    white-space: nowrap;
}

.applications-table td:nth-child(2),
.applications-table td:nth-child(3) {
    white-space: normal;
    overflow-wrap: anywhere;
}

.applications-table tr.regular-project td {
    background: var(--brand-primary-light);
}
</style>
