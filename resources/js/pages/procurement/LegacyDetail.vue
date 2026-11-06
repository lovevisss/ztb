<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

type RecordRow = { source_table: string; source_id: string; number: string | null; title: string | null; status: string | null; amount_cents: number | null; occurred_at: string | null; details: string | Record<string, unknown> };
type Attachment = { owner_table: string; owner_source_id: string; name: string | null; migration_status: string };
const props = defineProps<{ project: RecordRow & { creator_name: string | null; department_code: string | null }; records: RecordRow[]; attachments: Attachment[] }>();
const labels: Record<string, string> = { zcy_cgsq_qd: '申请清单', zcy_cgrw: '采购任务', zcy_cgrw_qd: '任务清单关联', zcy_cgb: '采购包', zcy_cgb_qd: '采购包清单关联', zcy_zbjg: '采购结果', zcy_ht: '合同', zcy_progress_history: '办理记录' };
function money(cents: number | null) { return cents === null ? '—' : `¥${(cents / 100).toLocaleString('zh-CN', { minimumFractionDigits: 2 })}`; }
function details(value: RecordRow['details']) { return typeof value === 'string' ? JSON.parse(value) as Record<string, unknown> : value; }
const groups = Object.entries(Object.groupBy(props.records, (row) => row.source_table));
</script>

<template>
    <Head title="旧采购项目详情" />
    <main class="mx-auto max-w-6xl px-5 py-8 md:px-10">
        <Link href="/procurement/legacy-history" class="text-sm text-emerald-800 underline">← 返回旧系统历史</Link>
        <h1 class="mt-5 font-serif text-3xl font-semibold text-slate-900">{{ project.title || '未命名项目' }}</h1>
        <p class="mt-2 text-sm text-slate-500">旧系统只读档案 · {{ project.number || project.source_id }}</p>
        <div class="mt-6 grid gap-4 rounded-lg border border-slate-200 bg-white p-5 text-sm md:grid-cols-4">
            <p>预算：{{ money(project.amount_cents) }}</p><p>原状态：{{ project.status || '—' }}</p><p>创建人：{{ project.creator_name || '—' }}</p><p>院系编码：{{ project.department_code || '—' }}</p>
        </div>
        <section v-for="[table, rows] in groups" :key="table" class="mt-6 rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-semibold">{{ labels[table] || table }} <span class="text-sm font-normal text-slate-500">{{ rows?.length }} 条</span></h2>
            <div class="mt-3 space-y-3"><details v-for="row in rows" :key="row.source_id" class="rounded border border-slate-100 p-3 text-sm">
                <summary class="cursor-pointer">{{ row.title || row.number || row.source_id }} · {{ money(row.amount_cents) }} · {{ row.status || '—' }}</summary>
                <dl class="mt-3 grid gap-2 md:grid-cols-2"><div v-for="[key, value] in Object.entries(details(row.details))" :key="key" v-show="value !== null && value !== ''"><dt class="inline text-slate-500">{{ key }}：</dt><dd class="inline break-all">{{ String(value) }}</dd></div></dl>
            </details></div>
        </section>
        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-semibold">附件索引 <span class="text-sm font-normal text-slate-500">{{ attachments.length }} 条</span></h2>
            <p class="mt-1 text-sm text-amber-700">文件待迁移，暂不提供下载。</p>
            <ul class="mt-3 space-y-2 text-sm"><li v-for="(file, index) in attachments" :key="index">{{ file.name || '未命名附件' }} · {{ labels[file.owner_table] || file.owner_table }}</li></ul>
        </section>
    </main>
</template>
