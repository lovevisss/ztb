<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

type Group = {
    label: string;
    count: number;
    budgetCents: number;
    actualCents: number;
};
type Trend = {
    period: string;
    count: number;
    budgetCents: number;
    actualCents: number;
};
type RecordRow = {
    id: number;
    project_number: string;
    title: string;
    department_name: string;
    responsible_department: string;
    purchase_method: string;
    purchase_type: string;
    status: string;
    budget_cents: number;
    actual_amount_cents: number | null;
};
type Filters = Partial<
    Record<
        | 'year'
        | 'department_id'
        | 'responsible_department'
        | 'purchase_method'
        | 'organization_form'
        | 'funding_source'
        | 'purchase_type'
        | 'status'
        | 'execution_status'
        | 'archive_status',
        string | number
    >
>;
const props = defineProps<{
    summary: {
        projectCount: number;
        budgetCents: number;
        actualCents: number;
        selfPurchaseCount: number;
        centralTenderCount: number;
        pendingArchiveCount: number;
        averageApprovalHours: number | null;
    };
    byDepartment: Group[];
    departmentRanking: Group[];
    byResponsibleDepartment: Group[];
    byPurchaseMethod: Group[];
    byOrganizationForm: Group[];
    byFundingSource: Group[];
    byPurchaseType: Group[];
    byStatus: Group[];
    byExecutionStatus: Group[];
    byArchiveStatus: Group[];
    yearlyTrend: Trend[];
    monthlyTrend: Trend[];
    records: RecordRow[];
    filters: Filters;
    departments: { id: number; name: string }[];
}>();
const filters = useForm({
    year: String(props.filters.year ?? ''),
    department_id: String(props.filters.department_id ?? ''),
    responsible_department: String(props.filters.responsible_department ?? ''),
    purchase_method: String(props.filters.purchase_method ?? ''),
    organization_form: String(props.filters.organization_form ?? ''),
    funding_source: String(props.filters.funding_source ?? ''),
    purchase_type: String(props.filters.purchase_type ?? ''),
    status: String(props.filters.status ?? ''),
    execution_status: String(props.filters.execution_status ?? ''),
    archive_status: String(props.filters.archive_status ?? ''),
});
const money = (cents: number | null) =>
    cents == null
        ? '—'
        : `¥${(cents / 100).toLocaleString('zh-CN', { maximumFractionDigits: 2 })}`;
const statusLabels: Record<string, string> = {
    submitted: '审批中',
    returned: '退回',
    rejected: '驳回',
    approved: '审批通过',
    executing: '执行中',
    execution_completed: '待归档',
    archived: '已归档',
};
const typeLabels: Record<string, string> = {
    self_purchase: '自行采购',
    central_tender: '统一招标',
};

function search() {
    filters.get('/procurement/statistics', { preserveState: true });
}
const exportUrl = computed(() => {
    const params = new URLSearchParams();
    for (const [key, value] of Object.entries(filters.data()))
        if (value !== '') params.set(key, value);
    return `/procurement/statistics/export?${params.toString()}`;
});
const trendData = computed(() =>
    props.filters.year ? props.monthlyTrend : props.yearlyTrend,
);
const linePoints = computed(() => {
    const max = Math.max(1, ...trendData.value.map((item) => item.count));
    return trendData.value
        .map((item, index) => {
            const x =
                trendData.value.length === 1
                    ? 320
                    : 28 + (index * 584) / (trendData.value.length - 1);
            const y = 160 - (item.count * 125) / max;
            return `${x},${y}`;
        })
        .join(' ');
});
const pieStyle = computed(() => {
    if (props.summary.projectCount === 0) return { background: '#e2e8f0' };
    const ratio =
        props.summary.projectCount === 0
            ? 0
            : (props.summary.selfPurchaseCount / props.summary.projectCount) *
              100;
    return {
        background: `conic-gradient(#047857 0 ${ratio}%, #d6a649 ${ratio}% 100%)`,
    };
});
const rankingMax = computed(() =>
    Math.max(1, ...props.departmentRanking.map((item) => item.count)),
);
</script>

<template>
    <Head title="采购统计" />
    <main class="mx-auto max-w-7xl px-5 py-8 md:px-10 md:py-12">
        <header
            class="flex flex-wrap items-end justify-between gap-5 border-b border-slate-200 pb-7"
        >
            <div>
                <p
                    class="text-xs font-semibold tracking-[0.28em] text-emerald-700"
                >
                    PROCUREMENT / 数据分析
                </p>
                <h1
                    class="mt-2 font-serif text-3xl font-semibold text-slate-900 md:text-4xl"
                >
                    采购数据大屏
                </h1>
                <p class="mt-3 text-sm text-slate-500">
                    按授权范围查看项目、预算、执行与归档进度。
                </p>
            </div>
            <div class="flex gap-3">
                <Link
                    href="/procurement/history"
                    class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-700"
                    >项目检索</Link
                ><a
                    :href="exportUrl"
                    class="rounded-md bg-emerald-800 px-4 py-2 text-sm font-medium text-white"
                    >导出 Excel</a
                >
            </div>
        </header>

        <form
            class="mt-7 grid gap-3 rounded-lg border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2 xl:grid-cols-4"
            @submit.prevent="search"
        >
            <el-input
                v-model="filters.year"
                type="number"
                placeholder="年度"
                clearable
                aria-label="年度"
            />
            <el-select
                v-model="filters.department_id"
                placeholder="全部部门"
                clearable
                filterable
                aria-label="部门"
                ><el-option
                    v-for="item in departments"
                    :key="item.id"
                    :label="item.name"
                    :value="String(item.id)"
            /></el-select>
            <el-input
                v-model="filters.responsible_department"
                placeholder="归口部门"
                clearable
                aria-label="归口部门"
            />
            <el-input
                v-model="filters.purchase_method"
                placeholder="采购方式"
                clearable
                aria-label="采购方式"
            />
            <el-input
                v-model="filters.organization_form"
                placeholder="组织形式"
                clearable
                aria-label="组织形式"
            />
            <el-input
                v-model="filters.funding_source"
                placeholder="经费列支"
                clearable
                aria-label="经费列支"
            />
            <el-select
                v-model="filters.purchase_type"
                placeholder="全部采购类型"
                clearable
                aria-label="采购类型"
                ><el-option label="自行采购" value="self_purchase" /><el-option
                    label="统一招标"
                    value="central_tender"
            /></el-select>
            <el-select
                v-model="filters.status"
                placeholder="全部状态"
                clearable
                aria-label="项目状态"
                ><el-option
                    v-for="(label, value) in statusLabels"
                    :key="value"
                    :label="label"
                    :value="value"
            /></el-select>
            <el-select
                v-model="filters.execution_status"
                placeholder="全部执行状态"
                clearable
                aria-label="执行状态"
                ><el-option label="未开始" value="not_started" /><el-option
                    label="执行中"
                    value="draft" /><el-option
                    label="执行完成"
                    value="completed"
            /></el-select>
            <el-select
                v-model="filters.archive_status"
                placeholder="全部归档状态"
                clearable
                aria-label="归档状态"
                ><el-option label="已归档" value="archived" /><el-option
                    label="未归档"
                    value="not_archived"
            /></el-select>
            <p v-if="filters.errors.year" class="text-xs text-red-700">
                {{ filters.errors.year }}
            </p>
            <el-button
                native-type="submit"
                type="primary"
                class="xl:col-start-4"
                >应用筛选</el-button
            >
        </form>

        <div class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div
                v-for="card in [
                    {
                        label: '采购项目',
                        value: String(summary.projectCount),
                        unit: '项',
                    },
                    {
                        label: '预算总额',
                        value: money(summary.budgetCents),
                        unit: '',
                    },
                    {
                        label: '实际采购',
                        value: money(summary.actualCents),
                        unit: '',
                    },
                    {
                        label: '待归档',
                        value: String(summary.pendingArchiveCount),
                        unit: '项',
                    },
                ]"
                :key="card.label"
                class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"
            >
                <p class="text-xs tracking-wide text-slate-500">
                    {{ card.label }}
                </p>
                <p class="mt-3 font-serif text-3xl text-slate-900">
                    {{ card.value }}
                    <span class="text-base text-slate-500">{{
                        card.unit
                    }}</span>
                </p>
            </div>
        </div>
        <div
            class="mt-4 flex flex-wrap gap-6 rounded-lg bg-emerald-950 px-6 py-4 text-sm text-emerald-50"
        >
            <span
                >自行采购
                <strong>{{ summary.selfPurchaseCount }}</strong> 项</span
            ><span
                >统一招标
                <strong>{{ summary.centralTenderCount }}</strong> 项</span
            >
            <span
                >平均审批耗时
                <strong>{{ summary.averageApprovalHours ?? '—' }}</strong>
                小时</span
            >
        </div>

        <div class="mt-7 grid gap-6 lg:grid-cols-2">
            <section
                class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm"
            >
                <h2 class="font-serif text-xl text-slate-900">
                    {{ props.filters.year ? '月度趋势' : '年度趋势' }}
                </h2>
                <div v-if="trendData.length" class="mt-5">
                    <svg
                        viewBox="0 0 640 180"
                        role="img"
                        aria-label="采购项目数量折线图"
                        class="w-full"
                    >
                        <path d="M28 160H612" stroke="#cbd5e1" />
                        <polyline
                            :points="linePoints"
                            fill="none"
                            stroke="#047857"
                            stroke-width="3"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                        <circle
                            v-for="(item, index) in trendData"
                            :key="item.period"
                            :cx="
                                trendData.length === 1
                                    ? 320
                                    : 28 +
                                      (index * 584) / (trendData.length - 1)
                            "
                            :cy="
                                160 -
                                (item.count * 125) /
                                    Math.max(
                                        1,
                                        ...trendData.map(
                                            (point) => point.count,
                                        ),
                                    )
                            "
                            r="5"
                            fill="#047857"
                        />
                    </svg>
                    <div class="flex justify-between text-xs text-slate-500">
                        <span v-for="item in trendData" :key="item.period"
                            >{{ item.period }} · {{ item.count }} 项</span
                        >
                    </div>
                </div>
                <p v-else class="mt-8 text-sm text-slate-400">暂无趋势数据</p>
            </section>
            <section
                class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm"
            >
                <h2 class="font-serif text-xl text-slate-900">采购类型分布</h2>
                <div class="mt-7 flex items-center gap-8">
                    <div
                        class="size-36 shrink-0 rounded-full"
                        :style="pieStyle"
                        role="img"
                        aria-label="自行采购与统一招标比例饼图"
                    />
                    <div class="space-y-4 text-sm">
                        <p>
                            <span
                                class="mr-2 inline-block size-3 rounded-sm bg-emerald-700"
                            />自行采购 {{ summary.selfPurchaseCount }}
                        </p>
                        <p>
                            <span
                                class="mr-2 inline-block size-3 rounded-sm bg-amber-500"
                            />统一招标 {{ summary.centralTenderCount }}
                        </p>
                    </div>
                </div>
            </section>
            <section
                class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm"
            >
                <h2 class="font-serif text-xl text-slate-900">部门采购排行</h2>
                <ol class="mt-5 space-y-4">
                    <li v-for="item in departmentRanking" :key="item.label">
                        <div class="flex justify-between text-sm">
                            <span>{{ item.label }}</span
                            ><strong>{{ item.count }} 项</strong>
                        </div>
                        <div class="mt-2 h-2 rounded-full bg-slate-100">
                            <div
                                class="h-2 rounded-full bg-emerald-700"
                                :style="{
                                    width: `${(item.count / rankingMax) * 100}%`,
                                }"
                            />
                        </div>
                    </li>
                </ol>
            </section>
            <section
                class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm"
            >
                <h2 class="font-serif text-xl text-slate-900">采购方式分布</h2>
                <div class="mt-5 space-y-3">
                    <div
                        v-for="item in byPurchaseMethod"
                        :key="item.label"
                        class="flex justify-between border-b border-slate-100 pb-3 text-sm"
                    >
                        <span>{{ item.label }}</span
                        ><strong>{{ item.count }} 项</strong>
                    </div>
                </div>
            </section>
        </div>

        <section
            class="mt-7 rounded-lg border border-slate-200 bg-white p-6 shadow-sm"
        >
            <h2 class="font-serif text-xl text-slate-900">统计明细</h2>
            <div class="mt-5 overflow-x-auto">
                <table class="w-full min-w-[850px] text-left text-sm">
                    <thead
                        class="border-b border-slate-200 text-xs text-slate-500"
                    >
                        <tr>
                            <th class="py-3">项目</th>
                            <th>部门 / 归口</th>
                            <th>采购方式</th>
                            <th>状态</th>
                            <th class="text-right">预算</th>
                            <th class="text-right">实际</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="item in records" :key="item.id">
                            <td class="py-4">
                                <Link
                                    :href="`/procurement/applications/${item.id}`"
                                    class="font-medium text-emerald-800 hover:underline"
                                    >{{ item.title }}</Link
                                >
                                <p class="font-mono text-xs text-slate-400">
                                    {{ item.project_number }}
                                </p>
                            </td>
                            <td>
                                {{ item.department_name }} /
                                {{ item.responsible_department }}
                            </td>
                            <td>{{ item.purchase_method }}</td>
                            <td>
                                {{ statusLabels[item.status] ?? item.status }}
                            </td>
                            <td class="text-right">
                                {{ money(item.budget_cents) }}
                            </td>
                            <td class="text-right">
                                {{ money(item.actual_amount_cents) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p
                    v-if="records.length === 0"
                    class="py-12 text-center text-sm text-slate-400"
                >
                    没有符合条件的项目
                </p>
            </div>
        </section>
        <section class="mt-7 grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="block in [
                    { title: '归口部门', data: byResponsibleDepartment },
                    { title: '组织形式', data: byOrganizationForm },
                    { title: '经费列支', data: byFundingSource },
                    { title: '审批与执行状态', data: byStatus },
                    { title: '执行状态', data: byExecutionStatus },
                    { title: '归档状态', data: byArchiveStatus },
                ]"
                :key="block.title"
                class="rounded-lg border border-slate-200 bg-white p-5"
            >
                <h3 class="font-serif text-lg text-slate-900">
                    {{ block.title }}
                </h3>
                <p
                    v-for="item in block.data"
                    :key="item.label"
                    class="mt-3 flex justify-between text-slate-600"
                >
                    <span>{{
                        statusLabels[item.label] ??
                        typeLabels[item.label] ??
                        item.label
                    }}</span
                    ><strong>{{ item.count }}</strong>
                </p>
            </div>
        </section>
    </main>
</template>
