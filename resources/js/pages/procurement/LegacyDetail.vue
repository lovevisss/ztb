<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

type RecordRow = { source_table: string; source_id: string; number: string | null; title: string | null; status: string | null; amount_cents: number | null; occurred_at: string | null; details: string | Record<string, unknown> };
type Attachment = { owner_table: string; owner_source_id: string; name: string | null; migration_status: string };
const props = withDefaults(defineProps<{ project: RecordRow & { creator_name: string | null; department_code: string | null }; records: RecordRow[]; attachments: Attachment[]; backHref?: string }>(), { backHref: '/procurement/legacy-history' });
const labels: Record<string, string> = { zcy_cgsq_qd: '申请清单', zcy_cgrw: '采购任务', zcy_cgb: '采购包', zcy_zbjg: '采购结果', zcy_ht: '合同', zcy_progress_history: '办理记录' };
const visibleTables = ['zcy_cgsq_qd', 'zcy_cgrw', 'zcy_cgb', 'zcy_zbjg', 'zcy_ht'];
function parseDetails(value: RecordRow['details']): Record<string, unknown> {
    if (typeof value !== 'string') return value;
    try { return JSON.parse(value) as Record<string, unknown>; } catch { return {}; }
}
function field(data: Record<string, unknown>, key: string): string { const value = data[key]; return value === null || value === undefined || value === '' ? '—' : String(value); }
function money(cents: number | null) { return cents === null ? '—' : `¥${(cents / 100).toLocaleString('zh-CN', { minimumFractionDigits: 2 })}`; }
const projectData = computed(() => parseDetails(props.project.details));
const sections = computed(() => visibleTables.map((table) => ({ table, rows: props.records.filter((row) => row.source_table === table) })).filter((group) => group.rows.length > 0));
const timeline = computed(() => props.records.filter((row) => row.source_table === 'zcy_progress_history'));
</script>

<template>
    <Head title="旧采购项目档案" />
    <main class="mx-auto max-w-6xl px-5 py-8 md:px-10 md:py-12">
        <Link :href="backHref" class="text-sm font-medium text-emerald-800 hover:underline">← 返回申请列表</Link>
        <header class="mt-6 border-b border-slate-200 pb-7">
            <p class="text-xs font-semibold tracking-[0.2em] text-emerald-700">ARCHIVE / 旧系统采购档案</p>
            <div class="mt-3 flex flex-wrap items-start justify-between gap-4"><div><h1 class="font-serif text-3xl font-semibold text-slate-900">{{ project.title || '未命名项目' }}</h1><p class="mt-2 font-mono text-sm text-slate-500">{{ project.number || '原系统未生成编号' }}</p></div><span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs text-amber-800">历史记录 · 只读</span></div>
        </header>

        <div class="mt-7 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg border border-slate-200 bg-white p-5"><p class="text-xs text-slate-500">申请金额</p><p class="mt-2 text-xl font-semibold tabular-nums text-slate-900">{{ money(project.amount_cents) }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-5"><p class="text-xs text-slate-500">原流程状态</p><p class="mt-2 text-lg font-medium text-emerald-800">{{ project.status || '—' }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-5"><p class="text-xs text-slate-500">申报部门</p><p class="mt-2 text-sm font-medium text-slate-900">{{ field(projectData, 'sbbm') }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-5"><p class="text-xs text-slate-500">申报日期</p><p class="mt-2 text-lg font-medium text-slate-900">{{ field(projectData, 'sbrq').slice(0, 10) }}</p></div>
        </div>

        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-6">
            <h2 class="font-serif text-xl font-semibold text-slate-900">申请信息</h2>
            <dl class="mt-5 grid gap-x-8 gap-y-4 text-sm md:grid-cols-2"><div v-for="[label, key] in [['申请人', 'create_name'], ['采购类型', 'xmlx'], ['组织形式', 'zzxs'], ['采购方式', 'cgfs'], ['经费来源', 'jfly'], ['申购理由', 'sgly'], ['项目负责人', 'xmfzr'], ['使用地点', 'sydd']]" :key="key"><dt class="text-xs text-slate-500">{{ label }}</dt><dd class="mt-1 whitespace-pre-wrap text-slate-900">{{ field(projectData, key) }}</dd></div></dl>
            <details class="mt-6 border-t border-slate-100 pt-4 text-sm"><summary class="cursor-pointer text-emerald-800">查看旧系统原始字段</summary><dl class="mt-4 grid gap-2 md:grid-cols-2"><div v-for="[key, value] in Object.entries(projectData)" :key="key" v-show="value !== null && value !== ''" class="break-all"><dt class="inline text-slate-500">{{ key }}：</dt><dd class="inline">{{ String(value) }}</dd></div></dl></details>
        </section>

        <section v-for="group in sections" :key="group.table" class="mt-6 rounded-lg border border-slate-200 bg-white p-6">
            <h2 class="font-serif text-xl font-semibold text-slate-900">{{ labels[group.table] }} <span class="ml-2 text-sm font-normal text-slate-500">{{ group.rows.length }} 条</span></h2>
            <div class="mt-4 space-y-3"><details v-for="row in group.rows" :key="row.source_id" class="rounded-md border border-slate-100 bg-slate-50/50 px-4 py-3 text-sm"><summary class="flex cursor-pointer flex-wrap items-center justify-between gap-2"><span class="font-medium text-slate-900">{{ row.title || field(parseDetails(row.details), 'mc') || row.number || '记录' }}</span><span class="text-slate-600">{{ money(row.amount_cents) }} · {{ row.status || '查看详情' }}</span></summary><dl class="mt-4 grid gap-2 border-t border-slate-200 pt-4 md:grid-cols-2"><div v-for="[key, value] in Object.entries(parseDetails(row.details))" :key="key" v-show="value !== null && value !== ''" class="break-all"><dt class="inline text-slate-500">{{ key }}：</dt><dd class="inline">{{ String(value) }}</dd></div></dl></details></div>
        </section>

        <section v-if="timeline.length" class="mt-6 rounded-lg border border-slate-200 bg-white p-6"><h2 class="font-serif text-xl font-semibold text-slate-900">办理轨迹</h2><ol class="mt-5 border-l border-emerald-200 pl-5"><li v-for="row in timeline" :key="row.source_id" class="relative pb-5 text-sm before:absolute before:-left-[25px] before:top-1 before:size-2 before:rounded-full before:bg-emerald-700"><p class="font-medium text-slate-900">{{ field(parseDetails(row.details), 'lcsm') }}</p><p class="mt-1 text-xs text-slate-500">{{ row.occurred_at?.slice(0, 16) || '—' }} · {{ field(parseDetails(row.details), 'zxr') }}</p></li></ol></section>

        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-6"><h2 class="font-serif text-xl font-semibold text-slate-900">附件索引 <span class="ml-2 text-sm font-normal text-slate-500">{{ attachments.length }} 条</span></h2><p class="mt-2 text-sm text-amber-700">文件待迁移，暂不提供下载。</p><ul v-if="attachments.length" class="mt-4 divide-y divide-slate-100 text-sm"><li v-for="(file, index) in attachments" :key="index" class="flex justify-between gap-4 py-2"><span>{{ file.name || '未命名附件' }}</span><span class="text-slate-500">{{ labels[file.owner_table] || file.owner_table }}</span></li></ul></section>
    </main>
</template>
