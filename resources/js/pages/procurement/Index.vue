<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Application = {
    id: number;
    project_number: string | null;
    title: string | null;
    budget_cents: number | null;
    purchase_type: string | null;
    application_form: string;
    status: string;
    updated_at: string;
};

type LegacyApplication = {
    source_id: string;
    number: string | null;
    title: string | null;
    amount_cents: number | null;
    status: string | null;
    occurred_at: string | null;
    submitted_at: string | null;
    department_name: string | null;
};

const props = defineProps<{ applications: Application[]; legacyApplications: LegacyApplication[] }>();
const activeTab = ref<'submitted' | 'draft'>('submitted');
const search = ref('');
const page = ref(1);
const pageSize = 15;
const draftCount = computed(() => props.legacyApplications.filter((item) => item.status === '未提交').length);
const submittedCount = computed(() => props.legacyApplications.length - draftCount.value);
const filteredLegacy = computed(() => {
    const term = search.value.trim().toLocaleLowerCase();
    return props.legacyApplications.filter((item) => {
        const matchesTab = activeTab.value === 'draft' ? item.status === '未提交' : item.status !== '未提交';
        return matchesTab && (!term || [item.number, item.title, item.department_name, item.status]
            .some((value) => value?.toLocaleLowerCase().includes(term)));
    });
});
const totalPages = computed(() => Math.max(1, Math.ceil(filteredLegacy.value.length / pageSize)));
const visibleLegacy = computed(() => filteredLegacy.value.slice((page.value - 1) * pageSize, page.value * pageSize));
function selectTab(tab: 'submitted' | 'draft') { activeTab.value = tab; page.value = 1; }
function resetSearch() { search.value = ''; page.value = 1; }

const currency = (cents: number | null) =>
    cents === null
        ? '—'
        : `¥${(cents / 100).toLocaleString('zh-CN', { minimumFractionDigits: 2 })}`;
const typeLabel = (type: string | null) =>
    type === 'central_tender'
        ? '统一招标'
        : type === 'self_purchase'
          ? '部门自行采购'
          : '待提交';
const statusLabel: Record<string, string> = {
    draft: '草稿',
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
    <Head title="采购申请" />
    <main class="mx-auto w-full max-w-6xl px-5 py-8 md:px-10 md:py-12">
        <div
            class="mb-9 flex flex-wrap items-end justify-between gap-5 border-b border-slate-200 pb-7"
        >
            <div>
                <p
                    class="mb-2 text-xs font-semibold tracking-[0.28em] text-emerald-700"
                >
                    PROCUREMENT / 采购管理
                </p>
                <h1
                    class="font-serif text-3xl font-semibold tracking-tight text-slate-900 md:text-4xl"
                >
                    我的采购申请
                </h1>
                <p class="mt-3 text-sm text-slate-500">
                    查看新系统申请和本人发起的旧系统申请。
                </p>
            </div>
            <div class="flex flex-wrap gap-3">
                <Link
                    href="/procurement/applications/create"
                    class="rounded-md border border-emerald-800 px-5 py-3 text-sm font-medium text-emerald-900 transition hover:bg-emerald-50"
                    >部门自行采购</Link
                >
                <Link
                    href="/procurement/tenders/create"
                    class="rounded-md bg-emerald-800 px-5 py-3 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-900"
                    >统一招标 ↗</Link
                >
            </div>
        </div>

        <div
            v-if="applications.length === 0 && legacyApplications.length === 0"
            class="rounded-lg border border-dashed border-slate-300 bg-stone-50 px-6 py-16 text-center"
        >
            <p class="font-serif text-xl text-slate-800">尚无采购申请</p>
            <p class="mt-2 text-sm text-slate-500">
                从一份草稿开始，完善后再提交。
            </p>
        </div>
        <h2 v-if="applications.length > 0" class="mb-4 font-serif text-xl font-semibold text-slate-900">新系统申请</h2>
        <div
            v-if="applications.length > 0"
            class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm"
        >
            <table class="w-full min-w-[680px] text-left text-sm">
                <thead
                    class="bg-slate-50 text-xs font-semibold tracking-widest text-slate-500"
                >
                    <tr>
                        <th class="px-5 py-4">项目</th>
                        <th class="px-5 py-4">预算</th>
                        <th class="px-5 py-4">类型</th>
                        <th class="px-5 py-4">状态</th>
                        <th class="px-5 py-4">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr
                        v-for="item in applications"
                        :key="item.id"
                        class="text-slate-700"
                    >
                        <td class="px-5 py-5">
                            <Link
                                v-if="item.status !== 'draft'"
                                :href="`/procurement/applications/${item.id}`"
                                class="font-medium text-slate-900 hover:text-emerald-800 hover:underline"
                                >{{ item.title || '未命名项目' }}</Link
                            >
                            <div v-else class="font-medium text-slate-900">
                                {{ item.title || '未命名草稿' }}
                            </div>
                            <div class="mt-1 font-mono text-xs text-slate-400">
                                {{ item.project_number || '尚未生成项目编号' }}
                            </div>
                        </td>
                        <td class="px-5 py-5 tabular-nums">
                            {{ currency(item.budget_cents) }}
                        </td>
                        <td class="px-5 py-5">
                            {{ typeLabel(item.purchase_type) }}
                        </td>
                        <td class="px-5 py-5">
                            <span
                                :class="
                                    ['draft', 'returned'].includes(item.status)
                                        ? 'bg-amber-50 text-amber-700'
                                        : 'bg-emerald-50 text-emerald-800'
                                "
                                class="rounded-full px-3 py-1 text-xs font-medium"
                                >{{
                                    statusLabel[item.status] ?? item.status
                                }}</span
                            >
                        </td>
                        <td class="px-5 py-5">
                            <Link
                                v-if="
                                    ['draft', 'returned'].includes(item.status)
                                "
                                :href="
                                    item.application_form === 'central_tender'
                                        ? `/procurement/tenders/${item.id}/edit`
                                        : `/procurement/applications/${item.id}/edit`
                                "
                                class="font-medium text-emerald-800 hover:underline"
                                >{{
                                    item.status === 'returned'
                                        ? '修改申请'
                                        : '继续编辑'
                                }}</Link
                            ><Link
                                v-else
                                :href="`/procurement/applications/${item.id}`"
                                class="font-medium text-emerald-800 hover:underline"
                                >查看进度</Link
                            >
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <section v-if="legacyApplications.length > 0" class="mt-9">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div><p class="text-xs font-semibold tracking-[0.18em] text-emerald-700">LEGACY RECORDS / 历史档案</p><h2 class="mt-2 font-serif text-2xl font-semibold text-slate-900">旧系统申请</h2><p class="mt-1 text-sm text-slate-500">共 {{ legacyApplications.length }} 条，只读查看，不进入新系统审批流程。</p></div>
                <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs text-amber-800">历史数据 · 仅供查阅</span>
            </div>
            <div class="mt-5 rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 px-5 pt-3">
                    <div class="flex gap-6 text-sm font-medium">
                        <button type="button" :class="['border-b-2 px-1 pb-3 pt-2', activeTab === 'draft' ? 'border-emerald-700 text-emerald-800' : 'border-transparent text-slate-500 hover:text-slate-800']" @click="selectTab('draft')">未提交 <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs">{{ draftCount }}</span></button>
                        <button type="button" :class="['border-b-2 px-1 pb-3 pt-2', activeTab === 'submitted' ? 'border-emerald-700 text-emerald-800' : 'border-transparent text-slate-500 hover:text-slate-800']" @click="selectTab('submitted')">已提交 <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs">{{ submittedCount }}</span></button>
                    </div>
                    <div class="mb-3 flex items-center gap-2"><input v-model="search" type="search" placeholder="搜索编号、名称、部门或状态" aria-label="搜索旧系统申请" class="w-60 rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-emerald-700" @input="page = 1" /><button type="button" class="text-sm text-slate-500 hover:text-emerald-800" @click="resetSearch">重置</button></div>
                </div>
                <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold tracking-widest text-slate-500">
                        <tr><th class="px-5 py-4">项目编号 / 名称</th><th>申报部门</th><th>申请金额</th><th>提交日期</th><th>流程状态</th><th>操作</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="item in visibleLegacy" :key="item.source_id" class="text-slate-700 transition-colors hover:bg-emerald-50/40">
                            <td class="px-5 py-4">
                                <Link :href="`/procurement/applications?legacy=${encodeURIComponent(item.source_id)}`" class="font-medium text-slate-900 hover:text-emerald-800 hover:underline">{{ item.title || '未命名项目' }}</Link>
                                <div class="mt-1 font-mono text-xs text-slate-400">{{ item.number || '无原编号' }}</div>
                            </td>
                            <td class="px-5 py-4 text-slate-600">{{ item.department_name || '—' }}</td>
                            <td class="px-5 py-4 tabular-nums">{{ currency(item.amount_cents) }}</td>
                            <td class="px-5 py-4">{{ (item.submitted_at || item.occurred_at)?.slice(0, 10) || '—' }}</td>
                            <td class="px-5 py-4"><span :class="['rounded-full px-2.5 py-1 text-xs font-medium', item.status === '未提交' ? 'bg-amber-50 text-amber-800' : 'bg-emerald-50 text-emerald-800']">{{ item.status || '—' }}</span></td>
                            <td class="px-5 py-4"><Link :href="`/procurement/applications?legacy=${encodeURIComponent(item.source_id)}`" class="font-medium text-emerald-800 hover:underline">查看档案 →</Link></td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="filteredLegacy.length === 0" class="px-5 py-12 text-center text-sm text-slate-500">当前条件下没有申请</p>
                </div>
                <div class="flex items-center justify-between border-t border-slate-100 px-5 py-3 text-xs text-slate-500"><span>显示 {{ filteredLegacy.length === 0 ? 0 : (page - 1) * pageSize + 1 }}–{{ Math.min(page * pageSize, filteredLegacy.length) }} / {{ filteredLegacy.length }} 条</span><div class="flex gap-2"><button type="button" class="rounded border border-slate-200 px-3 py-1 disabled:opacity-40" :disabled="page === 1" @click="page--">上一页</button><span class="px-2 py-1">{{ page }} / {{ totalPages }}</span><button type="button" class="rounded border border-slate-200 px-3 py-1 disabled:opacity-40" :disabled="page >= totalPages" @click="page++">下一页</button></div></div>
            </div>
        </section>
    </main>
</template>
