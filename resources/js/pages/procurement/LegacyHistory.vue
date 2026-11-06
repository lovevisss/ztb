<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

type Project = { source_id: string; number: string | null; title: string | null; status: string | null; amount_cents: number | null; occurred_at: string | null; creator_name: string | null; department_code: string | null };
type PageLink = { url: string | null; label: string; active: boolean };
const props = defineProps<{ projects: { data: Project[]; links: PageLink[]; total: number }; filters: { search?: string; year?: number }; summary: { projects_count: number; budget_cents: number }; byYear: { year: number; projects_count: number; budget_cents: number }[] }>();
const search = ref(props.filters.search ?? '');
const year = ref(props.filters.year?.toString() ?? '');
function submit() { router.get('/procurement/legacy-history', { search: search.value, year: year.value }, { preserveState: true }); }
function money(cents: number | null) { return cents === null ? '—' : `¥${(cents / 100).toLocaleString('zh-CN', { minimumFractionDigits: 2 })}`; }
</script>

<template>
    <Head title="旧系统采购历史" />
    <main class="mx-auto max-w-6xl px-5 py-8 md:px-10">
        <Link href="/procurement/history" class="text-sm text-emerald-800 underline">← 返回新系统项目</Link>
        <h1 class="mt-5 font-serif text-3xl font-semibold text-slate-900">旧系统采购历史</h1>
        <p class="mt-2 text-sm text-slate-500">仅供查询和统计；旧项目不会进入新系统审批流程。</p>
        <form class="mt-7 flex flex-wrap gap-3 rounded-lg border border-slate-200 bg-white p-5" @submit.prevent="submit">
            <el-input v-model="search" class="max-w-sm" placeholder="项目名称或原编号" clearable />
            <el-input v-model="year" class="max-w-36" type="number" placeholder="年份" clearable />
            <el-button native-type="submit" type="primary">查询</el-button>
        </form>
        <div class="mt-5 grid gap-3 sm:grid-cols-2">
            <div class="rounded-lg border border-slate-200 bg-white p-5"><p class="text-sm text-slate-500">申请数量</p><p class="mt-2 text-2xl font-semibold">{{ summary.projects_count }}</p></div>
            <div class="rounded-lg border border-slate-200 bg-white p-5"><p class="text-sm text-slate-500">申请预算合计</p><p class="mt-2 text-2xl font-semibold">{{ money(summary.budget_cents) }}</p></div>
        </div>
        <div class="mt-4 flex flex-wrap gap-2 text-sm text-slate-600"><span v-for="period in byYear" :key="period.year" class="rounded-full bg-slate-100 px-3 py-1">{{ period.year }}：{{ period.projects_count }} 项</span></div>
        <p class="mt-5 text-sm text-slate-500">共 {{ projects.total }} 条申请</p>
        <div class="mt-3 overflow-x-auto rounded-lg border border-slate-200 bg-white">
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead class="bg-slate-50 text-slate-500"><tr><th class="px-4 py-3">项目</th><th>原编号</th><th>预算</th><th>原状态</th><th>创建人</th><th>日期</th></tr></thead>
                <tbody class="divide-y divide-slate-100"><tr v-for="project in projects.data" :key="project.source_id">
                    <td class="px-4 py-3"><Link :href="`/procurement/legacy-history/${encodeURIComponent(project.source_id)}`" class="text-emerald-800 underline">{{ project.title || '未命名项目' }}</Link></td>
                    <td>{{ project.number || '—' }}</td><td>{{ money(project.amount_cents) }}</td><td>{{ project.status || '—' }}</td><td>{{ project.creator_name || '—' }}</td><td>{{ project.occurred_at?.slice(0, 10) || '—' }}</td>
                </tr></tbody>
            </table>
        </div>
        <nav class="mt-5 flex flex-wrap gap-2"><Link v-for="(link, index) in projects.links" :key="index" :href="link.url || '#'" :class="['rounded border px-3 py-1 text-sm', link.active ? 'border-emerald-700 bg-emerald-50 text-emerald-800' : 'border-slate-200', !link.url ? 'pointer-events-none opacity-40' : '']" v-html="link.label" /></nav>
    </main>
</template>
