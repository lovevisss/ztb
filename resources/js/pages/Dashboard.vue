<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    IconArchive,
    IconArrowUpRight,
    IconBriefcase,
    IconChartBar,
    IconClockHour4,
    IconCurrencyYuan,
    IconFileDescription,
} from '@tabler/icons-vue';

type Trend = {
    period: string;
    count: number;
    budgetCents: number;
    actualCents: number;
};
type PurchaseType = { label: string; count: number };
type Project = {
    id: number;
    project_number: string | null;
    title: string | null;
    department_name: string | null;
    budget_cents: number | null;
    status: string;
    submitted_at: string | null;
};

const props = defineProps<{
    summary: {
        projectCount: number;
        budgetCents: number;
        pendingArchiveCount: number;
        averageApprovalHours: number | null;
    };
    monthlyTrend: Trend[];
    purchaseTypes: PurchaseType[];
    recentProjects: Project[];
}>();

const money = (cents: number | null) =>
    cents == null
        ? '—'
        : `¥${(cents / 100).toLocaleString('zh-CN', { maximumFractionDigits: 0 })}`;
const maxTrend = computed(() =>
    Math.max(1, ...props.monthlyTrend.map((item) => item.count)),
);
const totalTypes = computed(() =>
    props.purchaseTypes.reduce((sum, item) => sum + item.count, 0),
);
const selfPurchaseCount = computed(
    () =>
        props.purchaseTypes.find((item) => item.label === 'self_purchase')
            ?.count ?? 0,
);
const selfPurchaseRatio = computed(() =>
    totalTypes.value ? (selfPurchaseCount.value / totalTypes.value) * 100 : 0,
);
const statusNames: Record<string, string> = {
    submitted: '审批中',
    returned: '退回修改',
    rejected: '已驳回',
    approved: '审批通过',
    executing: '执行中',
    execution_completed: '待归档',
    archived: '已归档',
};
const statusTone: Record<string, string> = {
    submitted: 'warning',
    returned: 'danger',
    rejected: 'danger',
    approved: 'success',
    executing: 'primary',
    execution_completed: 'warning',
    archived: 'success',
};
</script>

<template>
    <Head title="工作台" />
    <div class="admin-page">
        <!-- Page heading -->
        <header class="admin-page-header">
            <div>
                <p class="admin-eyebrow">采购管理 / 数据概览</p>
                <h1>工作台</h1>
                <p class="admin-description">
                    查看权限范围内的采购项目、预算与执行情况。
                </p>
            </div>
            <Link
                href="/procurement/applications/create"
                class="admin-button admin-button-primary"
                >新建申请 <IconArrowUpRight
            /></Link>
        </header>

        <!-- Summary cards -->
        <section class="admin-metrics" aria-label="采购概况">
            <article class="admin-card admin-metric">
                <span class="admin-metric-icon"><IconBriefcase /></span
                ><span class="admin-muted">项目总数</span
                ><strong>{{
                    summary.projectCount.toLocaleString('zh-CN')
                }}</strong
                ><span class="admin-metric-note">权限范围内项目</span>
            </article>
            <article class="admin-card admin-metric">
                <span class="admin-metric-icon"><IconCurrencyYuan /></span
                ><span class="admin-muted">预算总额</span
                ><strong>{{ money(summary.budgetCents) }}</strong
                ><span class="admin-metric-note">已提交项目预算</span>
            </article>
            <article class="admin-card admin-metric">
                <span class="admin-metric-icon"><IconArchive /></span
                ><span class="admin-muted">待归档</span
                ><strong>{{
                    summary.pendingArchiveCount.toLocaleString('zh-CN')
                }}</strong
                ><span class="admin-metric-note">执行完成待归档</span>
            </article>
            <article class="admin-card admin-metric">
                <span class="admin-metric-icon"><IconClockHour4 /></span
                ><span class="admin-muted">平均审批时长</span
                ><strong>{{
                    summary.averageApprovalHours == null
                        ? '—'
                        : `${summary.averageApprovalHours} 小时`
                }}</strong
                ><span class="admin-metric-note">已完成审批项目</span>
            </article>
        </section>

        <!-- Charts -->
        <div class="admin-dashboard-charts">
            <section class="admin-card admin-chart-card">
                <div class="admin-section-heading">
                    <div>
                        <IconChartBar />
                        <h2>采购项目趋势</h2>
                    </div>
                    <span class="admin-muted">按提交月份统计</span>
                </div>
                <div
                    v-if="monthlyTrend.length"
                    class="admin-trend-chart"
                    role="img"
                    aria-label="月度采购项目数量柱状图"
                >
                    <div
                        v-for="item in monthlyTrend"
                        :key="item.period"
                        class="admin-trend-column"
                    >
                        <span class="admin-trend-value">{{ item.count }}</span>
                        <div class="admin-trend-track">
                            <span
                                :style="{
                                    height: `${Math.max(5, (item.count / maxTrend) * 100)}%`,
                                }"
                            />
                        </div>
                        <span class="admin-trend-label">{{
                            item.period.slice(5) || item.period
                        }}</span>
                    </div>
                </div>
                <div v-else class="admin-empty">暂无采购趋势数据</div>
            </section>
            <section class="admin-card admin-chart-card">
                <div class="admin-section-heading">
                    <div>
                        <IconFileDescription />
                        <h2>采购方式占比</h2>
                    </div>
                </div>
                <div v-if="totalTypes" class="admin-donut-wrap">
                    <div
                        class="admin-donut"
                        :style="{ '--donut-ratio': `${selfPurchaseRatio}%` }"
                        role="img"
                        :aria-label="`部门自行采购 ${selfPurchaseCount} 项，统一招标 ${totalTypes - selfPurchaseCount} 项`"
                    >
                        <div>
                            <strong>{{ totalTypes }}</strong
                            ><span>个项目</span>
                        </div>
                    </div>
                    <div class="admin-donut-legend">
                        <div>
                            <span class="admin-legend-dot" />部门自行采购
                            <strong>{{ selfPurchaseCount }}</strong>
                        </div>
                        <div>
                            <span
                                class="admin-legend-dot admin-legend-dot-light"
                            />统一招标
                            <strong>{{
                                totalTypes - selfPurchaseCount
                            }}</strong>
                        </div>
                    </div>
                </div>
                <div v-else class="admin-empty">暂无采购方式数据</div>
            </section>
        </div>

        <!-- Recent projects -->
        <section class="admin-card admin-table-card">
            <div class="admin-section-heading">
                <div>
                    <IconBriefcase />
                    <h2>最近项目</h2>
                </div>
                <Link href="/procurement/history" class="admin-text-link"
                    >查看全部 <IconArrowUpRight
                /></Link>
            </div>
            <div v-if="recentProjects.length" class="admin-table-scroll">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>项目名称</th>
                            <th>项目编号</th>
                            <th>申请部门</th>
                            <th>预算金额</th>
                            <th>提交时间</th>
                            <th>状态</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in recentProjects" :key="item.id">
                            <td>
                                <Link
                                    :href="`/procurement/applications/${item.id}`"
                                    class="admin-table-link"
                                    >{{ item.title ?? '未命名项目' }}</Link
                                >
                            </td>
                            <td>{{ item.project_number ?? '—' }}</td>
                            <td>{{ item.department_name ?? '—' }}</td>
                            <td>{{ money(item.budget_cents) }}</td>
                            <td>
                                {{ item.submitted_at?.slice(0, 10) ?? '—' }}
                            </td>
                            <td>
                                <span
                                    class="admin-status"
                                    :data-tone="
                                        statusTone[item.status] ?? 'muted'
                                    "
                                    >{{
                                        statusNames[item.status] ?? item.status
                                    }}</span
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="admin-empty">暂无可查看的采购项目</div>
        </section>
    </div>
</template>
