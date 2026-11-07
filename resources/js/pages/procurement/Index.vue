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

const props = defineProps<{
    applications: Application[];
    legacyApplications: LegacyApplication[];
}>();
const activeTab = ref<'submitted' | 'draft'>('submitted');
const search = ref('');
const page = ref(1);
const pageSize = 15;
const draftCount = computed(
    () =>
        props.legacyApplications.filter((item) => item.status === '未提交')
            .length,
);
const submittedCount = computed(
    () => props.legacyApplications.length - draftCount.value,
);
const filteredLegacy = computed(() => {
    const term = search.value.trim().toLocaleLowerCase();
    return props.legacyApplications.filter((item) => {
        const matchesTab =
            activeTab.value === 'draft'
                ? item.status === '未提交'
                : item.status !== '未提交';
        return (
            matchesTab &&
            (!term ||
                [
                    item.number,
                    item.title,
                    item.department_name,
                    item.status,
                ].some((value) => value?.toLocaleLowerCase().includes(term)))
        );
    });
});
const totalPages = computed(() =>
    Math.max(1, Math.ceil(filteredLegacy.value.length / pageSize)),
);
const visibleLegacy = computed(() =>
    filteredLegacy.value.slice(
        (page.value - 1) * pageSize,
        page.value * pageSize,
    ),
);
function selectTab(tab: 'submitted' | 'draft') {
    activeTab.value = tab;
    page.value = 1;
}
function resetSearch() {
    search.value = '';
    page.value = 1;
}

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
    <main
        class="mx-auto w-full max-w-6xl px-[var(--brand-space-24)] py-[var(--brand-space-32)] md:px-[var(--brand-space-32)] md:py-[var(--brand-space-32)]"
    >
        <div
            class="mb-[var(--brand-space-32)] flex flex-wrap items-end justify-between gap-[var(--brand-space-24)] border-b border-[var(--brand-border)] pb-[var(--brand-space-32)]"
        >
            <div>
                <p
                    class="mb-[var(--brand-space-8)] text-xs font-semibold tracking-[0.28em] text-[var(--brand-primary)]"
                >
                    PROCUREMENT / 采购管理
                </p>
                <h1
                    class="font-sans text-3xl font-semibold tracking-tight text-[var(--brand-title)] md:text-4xl"
                >
                    我的采购申请
                </h1>
                <p
                    class="mt-[var(--brand-space-16)] text-sm text-[var(--brand-muted)]"
                >
                    查看新系统申请和本人发起的旧系统申请。
                </p>
            </div>
            <div class="flex flex-wrap gap-[var(--brand-space-16)]">
                <Link
                    href="/procurement/applications/create"
                    class="rounded-[var(--brand-radius-md)] border border-[var(--brand-primary)] px-[var(--brand-space-24)] py-[var(--brand-space-16)] text-sm font-medium text-[var(--brand-primary)] transition hover:bg-[var(--brand-primary-light)]"
                    >部门自行采购</Link
                >
                <Link
                    href="/procurement/tenders/create"
                    class="rounded-[var(--brand-radius-md)] bg-[var(--brand-primary)] px-[var(--brand-space-24)] py-[var(--brand-space-16)] text-sm font-medium text-[var(--brand-card)] shadow-sm transition hover:bg-[var(--brand-primary)]"
                    >统一招标 ↗</Link
                >
            </div>
        </div>

        <div
            v-if="applications.length === 0 && legacyApplications.length === 0"
            class="rounded-[var(--brand-radius-md)] border border-dashed border-[var(--brand-border)] bg-[var(--brand-page)] px-[var(--brand-space-24)] py-[var(--brand-space-32)] text-center"
        >
            <p class="font-sans text-xl text-[var(--brand-title)]">
                尚无采购申请
            </p>
            <p
                class="mt-[var(--brand-space-8)] text-sm text-[var(--brand-muted)]"
            >
                从一份草稿开始，完善后再提交。
            </p>
        </div>
        <h2
            v-if="applications.length > 0"
            class="mb-[var(--brand-space-16)] font-sans text-xl font-semibold text-[var(--brand-title)]"
        >
            新系统申请
        </h2>
        <div
            v-if="applications.length > 0"
            class="overflow-x-auto rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] bg-[var(--brand-card)] shadow-sm"
        >
            <table class="w-full min-w-[680px] text-left text-sm">
                <thead
                    class="bg-[var(--brand-page)] text-xs font-semibold tracking-widest text-[var(--brand-muted)]"
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
                            类型
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
                    <tr
                        v-for="item in applications"
                        :key="item.id"
                        class="text-[var(--brand-body)]"
                    >
                        <td
                            class="px-[var(--brand-space-24)] py-[var(--brand-space-24)]"
                        >
                            <Link
                                v-if="item.status !== 'draft'"
                                :href="`/procurement/applications/${item.id}`"
                                class="font-medium text-[var(--brand-title)] hover:text-[var(--brand-primary)] hover:underline"
                                >{{ item.title || '未命名项目' }}</Link
                            >
                            <div
                                v-else
                                class="font-medium text-[var(--brand-title)]"
                            >
                                {{ item.title || '未命名草稿' }}
                            </div>
                            <div
                                class="mt-[var(--brand-space-8)] font-mono text-xs text-[var(--brand-muted)]"
                            >
                                {{ item.project_number || '尚未生成项目编号' }}
                            </div>
                        </td>
                        <td
                            class="px-[var(--brand-space-24)] py-[var(--brand-space-24)] tabular-nums"
                        >
                            {{ currency(item.budget_cents) }}
                        </td>
                        <td
                            class="px-[var(--brand-space-24)] py-[var(--brand-space-24)]"
                        >
                            {{ typeLabel(item.purchase_type) }}
                        </td>
                        <td
                            class="px-[var(--brand-space-24)] py-[var(--brand-space-24)]"
                        >
                            <span
                                :class="
                                    ['draft', 'returned'].includes(item.status)
                                        ? 'bg-[var(--brand-page)] text-[var(--brand-warning)]'
                                        : 'bg-[var(--brand-primary-light)] text-[var(--brand-primary)]'
                                "
                                class="rounded-full px-[var(--brand-space-16)] py-[var(--brand-space-8)] text-xs font-medium"
                                >{{
                                    statusLabel[item.status] ?? item.status
                                }}</span
                            >
                        </td>
                        <td
                            class="px-[var(--brand-space-24)] py-[var(--brand-space-24)]"
                        >
                            <Link
                                v-if="
                                    ['draft', 'returned'].includes(item.status)
                                "
                                :href="
                                    item.application_form === 'central_tender'
                                        ? `/procurement/tenders/${item.id}/edit`
                                        : `/procurement/applications/${item.id}/edit`
                                "
                                class="font-medium text-[var(--brand-primary)] hover:underline"
                                >{{
                                    item.status === 'returned'
                                        ? '修改申请'
                                        : '继续编辑'
                                }}</Link
                            ><Link
                                v-else
                                :href="`/procurement/applications/${item.id}`"
                                class="font-medium text-[var(--brand-primary)] hover:underline"
                                >查看进度</Link
                            >
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <section
            v-if="legacyApplications.length > 0"
            class="mt-[var(--brand-space-32)]"
        >
            <div
                class="flex flex-wrap items-end justify-between gap-[var(--brand-space-16)]"
            >
                <div>
                    <p
                        class="text-xs font-semibold tracking-[0.18em] text-[var(--brand-primary)]"
                    >
                        LEGACY RECORDS / 历史档案
                    </p>
                    <h2
                        class="mt-[var(--brand-space-8)] font-sans text-2xl font-semibold text-[var(--brand-title)]"
                    >
                        旧系统申请
                    </h2>
                    <p
                        class="mt-[var(--brand-space-8)] text-sm text-[var(--brand-muted)]"
                    >
                        共
                        {{ legacyApplications.length }}
                        条，只读查看，不进入新系统审批流程。
                    </p>
                </div>
                <span
                    class="rounded-full border border-[var(--brand-warning)] bg-[var(--brand-page)] px-[var(--brand-space-16)] py-[var(--brand-space-8)] text-xs text-[var(--brand-warning)]"
                    >历史数据 · 仅供查阅</span
                >
            </div>
            <div
                class="mt-[var(--brand-space-24)] rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] bg-[var(--brand-card)] shadow-sm"
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-[var(--brand-space-16)] border-b border-[var(--brand-border)] px-[var(--brand-space-24)] pt-[var(--brand-space-16)]"
                >
                    <div
                        class="flex gap-[var(--brand-space-24)] text-sm font-medium"
                    >
                        <button
                            type="button"
                            :class="[
                                'border-b-2 px-[var(--brand-space-8)] pt-[var(--brand-space-8)] pb-[var(--brand-space-16)]',
                                activeTab === 'draft'
                                    ? 'border-[var(--brand-primary)] text-[var(--brand-primary)]'
                                    : 'border-transparent text-[var(--brand-muted)] hover:text-[var(--brand-title)]',
                            ]"
                            @click="selectTab('draft')"
                        >
                            未提交
                            <span
                                class="ml-[var(--brand-space-8)] rounded-full bg-[var(--brand-page)] px-[var(--brand-space-8)] py-[var(--brand-space-8)] text-xs"
                                >{{ draftCount }}</span
                            >
                        </button>
                        <button
                            type="button"
                            :class="[
                                'border-b-2 px-[var(--brand-space-8)] pt-[var(--brand-space-8)] pb-[var(--brand-space-16)]',
                                activeTab === 'submitted'
                                    ? 'border-[var(--brand-primary)] text-[var(--brand-primary)]'
                                    : 'border-transparent text-[var(--brand-muted)] hover:text-[var(--brand-title)]',
                            ]"
                            @click="selectTab('submitted')"
                        >
                            已提交
                            <span
                                class="ml-[var(--brand-space-8)] rounded-full bg-[var(--brand-page)] px-[var(--brand-space-8)] py-[var(--brand-space-8)] text-xs"
                                >{{ submittedCount }}</span
                            >
                        </button>
                    </div>
                    <div
                        class="mb-[var(--brand-space-16)] flex items-center gap-[var(--brand-space-8)]"
                    >
                        <input
                            v-model="search"
                            type="search"
                            placeholder="搜索编号、名称、部门或状态"
                            aria-label="搜索旧系统申请"
                            class="w-60 rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] px-[var(--brand-space-16)] py-[var(--brand-space-8)] text-sm outline-none focus:border-[var(--brand-primary)]"
                            @input="page = 1"
                        /><button
                            type="button"
                            class="text-sm text-[var(--brand-muted)] hover:text-[var(--brand-primary)]"
                            @click="resetSearch"
                        >
                            重置
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-left text-sm">
                        <thead
                            class="bg-[var(--brand-page)] text-xs font-semibold tracking-widest text-[var(--brand-muted)]"
                        >
                            <tr>
                                <th
                                    class="px-[var(--brand-space-24)] py-[var(--brand-space-16)]"
                                >
                                    项目编号 / 名称
                                </th>
                                <th>申报部门</th>
                                <th>申请金额</th>
                                <th>提交日期</th>
                                <th>流程状态</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--brand-border)]">
                            <tr
                                v-for="item in visibleLegacy"
                                :key="item.source_id"
                                class="text-[var(--brand-body)] transition-colors hover:bg-[var(--brand-primary-light)]/40"
                            >
                                <td
                                    class="px-[var(--brand-space-24)] py-[var(--brand-space-16)]"
                                >
                                    <Link
                                        :href="`/procurement/applications?legacy=${encodeURIComponent(item.source_id)}`"
                                        class="font-medium text-[var(--brand-title)] hover:text-[var(--brand-primary)] hover:underline"
                                        >{{ item.title || '未命名项目' }}</Link
                                    >
                                    <div
                                        class="mt-[var(--brand-space-8)] font-mono text-xs text-[var(--brand-muted)]"
                                    >
                                        {{ item.number || '无原编号' }}
                                    </div>
                                </td>
                                <td
                                    class="px-[var(--brand-space-24)] py-[var(--brand-space-16)] text-[var(--brand-body)]"
                                >
                                    {{ item.department_name || '—' }}
                                </td>
                                <td
                                    class="px-[var(--brand-space-24)] py-[var(--brand-space-16)] tabular-nums"
                                >
                                    {{ currency(item.amount_cents) }}
                                </td>
                                <td
                                    class="px-[var(--brand-space-24)] py-[var(--brand-space-16)]"
                                >
                                    {{
                                        (
                                            item.submitted_at ||
                                            item.occurred_at
                                        )?.slice(0, 10) || '—'
                                    }}
                                </td>
                                <td
                                    class="px-[var(--brand-space-24)] py-[var(--brand-space-16)]"
                                >
                                    <span
                                        :class="[
                                            'rounded-full px-[var(--brand-space-16)] py-[var(--brand-space-8)] text-xs font-medium',
                                            item.status === '未提交'
                                                ? 'bg-[var(--brand-page)] text-[var(--brand-warning)]'
                                                : 'bg-[var(--brand-primary-light)] text-[var(--brand-primary)]',
                                        ]"
                                        >{{ item.status || '—' }}</span
                                    >
                                </td>
                                <td
                                    class="px-[var(--brand-space-24)] py-[var(--brand-space-16)]"
                                >
                                    <Link
                                        :href="`/procurement/applications?legacy=${encodeURIComponent(item.source_id)}`"
                                        class="font-medium text-[var(--brand-primary)] hover:underline"
                                        >查看档案 →</Link
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p
                        v-if="filteredLegacy.length === 0"
                        class="px-[var(--brand-space-24)] py-[var(--brand-space-32)] text-center text-sm text-[var(--brand-muted)]"
                    >
                        当前条件下没有申请
                    </p>
                </div>
                <div
                    class="flex items-center justify-between border-t border-[var(--brand-border)] px-[var(--brand-space-24)] py-[var(--brand-space-16)] text-xs text-[var(--brand-muted)]"
                >
                    <span
                        >显示
                        {{
                            filteredLegacy.length === 0
                                ? 0
                                : (page - 1) * pageSize + 1
                        }}–{{
                            Math.min(page * pageSize, filteredLegacy.length)
                        }}
                        / {{ filteredLegacy.length }} 条</span
                    >
                    <div class="flex gap-[var(--brand-space-8)]">
                        <button
                            type="button"
                            class="rounded border border-[var(--brand-border)] px-[var(--brand-space-16)] py-[var(--brand-space-8)] disabled:opacity-40"
                            :disabled="page === 1"
                            @click="page--"
                        >
                            上一页</button
                        ><span
                            class="px-[var(--brand-space-8)] py-[var(--brand-space-8)]"
                            >{{ page }} / {{ totalPages }}</span
                        ><button
                            type="button"
                            class="rounded border border-[var(--brand-border)] px-[var(--brand-space-16)] py-[var(--brand-space-8)] disabled:opacity-40"
                            :disabled="page >= totalPages"
                            @click="page++"
                        >
                            下一页
                        </button>
                    </div>
                </div>
            </div>
        </section>
    </main>
</template>
