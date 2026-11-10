<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import WorkflowDiagram from './WorkflowDiagram.vue';

type RecordRow = {
    source_table: string;
    source_id: string;
    number: string | null;
    title: string | null;
    status: string | null;
    amount_cents: number | null;
    occurred_at: string | null;
    details: string | Record<string, unknown>;
};
type Attachment = {
    owner_table: string;
    owner_source_id: string;
    name: string | null;
    migration_status: string;
};
type FlowNode = {
    key: string; label: string; state: 'done' | 'waiting' | 'stopped' | 'recorded';
    time: string | null; timeLabel: string | null; person: string | null;
    personLabel: string | null; result: string | null; note: string | null;
};
const props = withDefaults(
    defineProps<{
        project: RecordRow & {
            creator_name: string | null;
            department_code: string | null;
        };
        records: RecordRow[];
        attachments: Attachment[];
        workflowNodes: FlowNode[];
        backHref?: string;
    }>(),
    { backHref: '/procurement/legacy-history' },
);
const labels: Record<string, string> = {
    zcy_cgsq_qd: '申请清单',
    zcy_cgrw: '采购任务',
    zcy_cgb: '采购包',
    zcy_zbjg: '采购结果',
    zcy_ht: '合同',
    zcy_progress_history: '办理记录',
};
const visibleTables = [
    'zcy_cgsq_qd',
    'zcy_cgrw',
    'zcy_cgb',
    'zcy_zbjg',
    'zcy_ht',
];
function parseDetails(value: RecordRow['details']): Record<string, unknown> {
    if (typeof value !== 'string') return value;
    try {
        return JSON.parse(value) as Record<string, unknown>;
    } catch {
        return {};
    }
}
function field(data: Record<string, unknown>, key: string): string {
    const value = data[key];
    return value === null || value === undefined || value === ''
        ? '—'
        : String(value);
}
function money(cents: number | null) {
    return cents === null
        ? '—'
        : `¥${(cents / 100).toLocaleString('zh-CN', { minimumFractionDigits: 2 })}`;
}
const projectData = computed(() => parseDetails(props.project.details));
const sections = computed(() =>
    visibleTables
        .filter((table) => table !== 'zcy_cgsq_qd')
        .map((table) => ({
            table,
            rows: props.records.filter((row) => row.source_table === table),
        }))
        .filter((group) => group.rows.length > 0),
);
const timeline = computed(() =>
    props.records.filter((row) => row.source_table === 'zcy_progress_history'),
);
const hasMissingHandlers = computed(() => props.workflowNodes.some((node) => node.personLabel === '处理人' && node.person === '原系统未记录'));
const lineItems = computed(() => props.records.filter((row) => row.source_table === 'zcy_cgsq_qd'));
function itemField(row: RecordRow, keys: string[]) {
    const data = parseDetails(row.details);
    for (const key of keys) {
        if (data[key] !== null && data[key] !== undefined && data[key] !== '') return String(data[key]);
    }
    return '—';
}
</script>

<template>
    <Head title="旧采购项目档案" />
    <main
        class="mx-auto max-w-6xl px-[var(--brand-space-24)] py-[var(--brand-space-32)] md:px-[var(--brand-space-32)] md:py-[var(--brand-space-32)]"
    >
        <Link
            :href="backHref"
            class="text-sm font-medium text-[var(--brand-primary)] hover:underline"
            >← 返回申请列表</Link
        >
        <header
            class="mt-[var(--brand-space-24)] border-b border-[var(--brand-border)] pb-[var(--brand-space-32)]"
        >
            <p
                class="text-xs font-semibold tracking-[0.2em] text-[var(--brand-primary)]"
            >
                ARCHIVE / 旧系统采购档案
            </p>
            <div
                class="mt-[var(--brand-space-16)] flex flex-wrap items-start justify-between gap-[var(--brand-space-16)]"
            >
                <div>
                    <h1
                        class="font-sans text-3xl font-semibold text-[var(--brand-title)]"
                    >
                        {{ project.title || '未命名项目' }}
                    </h1>
                    <p
                        class="mt-[var(--brand-space-8)] font-mono text-sm text-[var(--brand-muted)]"
                    >
                        {{ project.number || '原系统未生成编号' }}
                    </p>
                </div>
                <span
                    class="rounded-full border border-[var(--brand-warning)] bg-[var(--brand-soft)] px-[var(--brand-space-16)] py-[var(--brand-space-8)] text-xs text-[var(--brand-warning)]"
                    >历史记录 · 只读</span
                >
            </div>
        </header>

        <div class="mt-[var(--brand-space-24)]">
            <div v-if="hasMissingHandlers" class="mb-[var(--brand-space-16)] rounded-[var(--brand-radius-md)] border border-[var(--brand-warning)] bg-[var(--brand-soft)] p-[var(--brand-space-16)] text-sm text-[var(--brand-warning)]">
                部分原系统办理记录没有保存处理人或节点说明，以下仅展示已导入的记录，不代表完整审批轨迹。
            </div>
            <WorkflowDiagram title="原系统可用办理记录" :nodes="workflowNodes" />
        </div>

        <div
            class="mt-[var(--brand-space-32)] grid gap-[var(--brand-space-16)] sm:grid-cols-2 lg:grid-cols-4"
        >
            <div
                class="rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)]"
            >
                <p class="text-xs text-[var(--brand-muted)]">申请金额</p>
                <p
                    class="mt-[var(--brand-space-8)] text-xl font-semibold text-[var(--brand-title)] tabular-nums"
                >
                    {{ money(project.amount_cents) }}
                </p>
            </div>
            <div
                class="rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)]"
            >
                <p class="text-xs text-[var(--brand-muted)]">原流程状态</p>
                <p
                    class="mt-[var(--brand-space-8)] text-lg font-medium text-[var(--brand-primary)]"
                >
                    {{ project.status || '—' }}
                </p>
            </div>
            <div
                class="rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)]"
            >
                <p class="text-xs text-[var(--brand-muted)]">申报部门</p>
                <p
                    class="mt-[var(--brand-space-8)] text-sm font-medium text-[var(--brand-title)]"
                >
                    {{ field(projectData, 'sbbm') }}
                </p>
            </div>
            <div
                class="rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)]"
            >
                <p class="text-xs text-[var(--brand-muted)]">申报日期</p>
                <p
                    class="mt-[var(--brand-space-8)] text-lg font-medium text-[var(--brand-title)]"
                >
                    {{ field(projectData, 'sbrq').slice(0, 10) }}
                </p>
            </div>
        </div>

        <section
            class="mt-[var(--brand-space-24)] rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)]"
        >
            <h2
                class="font-sans text-xl font-semibold text-[var(--brand-title)]"
            >
                申请信息
            </h2>
            <dl
                class="mt-[var(--brand-space-24)] grid gap-x-[var(--brand-space-32)] gap-y-[var(--brand-space-16)] text-sm md:grid-cols-2"
            >
                <div
                    v-for="[label, key] in [
                        ['申请人', 'create_name'],
                        ['采购类型', 'xmlx'],
                        ['组织形式', 'zzxs'],
                        ['采购方式', 'cgfs'],
                        ['经费来源', 'jfly'],
                        ['申购理由', 'sgly'],
                        ['项目负责人', 'xmfzr'],
                        ['使用地点', 'sydd'],
                    ]"
                    :key="key"
                >
                    <dt class="text-xs text-[var(--brand-muted)]">
                        {{ label }}
                    </dt>
                    <dd
                        class="mt-[var(--brand-space-8)] whitespace-pre-wrap text-[var(--brand-title)]"
                    >
                        {{ field(projectData, key) }}
                    </dd>
                </div>
            </dl>
            <details
                class="mt-[var(--brand-space-24)] border-t border-[var(--brand-border)] pt-[var(--brand-space-16)] text-sm"
            >
                <summary class="cursor-pointer text-[var(--brand-primary)]">
                    查看旧系统原始字段
                </summary>
                <dl
                    class="mt-[var(--brand-space-16)] grid gap-[var(--brand-space-8)] md:grid-cols-2"
                >
                    <div
                        v-for="[key, value] in Object.entries(projectData)"
                        :key="key"
                        v-show="value !== null && value !== ''"
                        class="break-all"
                    >
                        <dt class="inline text-[var(--brand-muted)]">
                            {{ key }}：
                        </dt>
                        <dd class="inline">{{ String(value) }}</dd>
                    </div>
                </dl>
            </details>
        </section>

        <section class="mt-[var(--brand-space-24)] rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)]">
            <h2 class="text-xl font-semibold text-[var(--brand-title)]">采购清单 <span class="text-sm font-normal text-[var(--brand-muted)]">{{ lineItems.length }} 项</span></h2>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="border-b border-[var(--brand-border)] bg-[var(--brand-soft)] text-[var(--brand-muted)]"><tr><th class="p-3">编号</th><th class="p-3">名称</th><th class="p-3">规格参数</th><th class="p-3">数量</th><th class="p-3">单位</th><th class="p-3">单价（元）</th><th class="p-3">金额</th><th class="p-3">备注</th></tr></thead>
                    <tbody class="divide-y divide-[var(--brand-border)]"><tr v-for="row in lineItems" :key="row.source_id"><td class="p-3">{{ row.number || '—' }}</td><td class="p-3">{{ row.title || itemField(row, ['mc']) }}</td><td class="p-3">{{ itemField(row, ['ggxh', 'xhgg', 'jscc']) }}</td><td class="p-3">{{ itemField(row, ['sl', 'cgsl']) }}</td><td class="p-3">{{ itemField(row, ['dw']) }}</td><td class="p-3">{{ itemField(row, ['dj']) }}</td><td class="p-3">{{ money(row.amount_cents) }}</td><td class="p-3">{{ itemField(row, ['bz']) }}</td></tr></tbody>
                </table>
                <p v-if="!lineItems.length" class="py-6 text-center text-sm text-[var(--brand-muted)]">原系统无采购清单记录</p>
            </div>
        </section>

        <section
            v-for="group in sections"
            :key="group.table"
            class="mt-[var(--brand-space-24)] rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)]"
        >
            <h2
                class="font-sans text-xl font-semibold text-[var(--brand-title)]"
            >
                {{ labels[group.table] }}
                <span
                    class="ml-[var(--brand-space-8)] text-sm font-normal text-[var(--brand-muted)]"
                    >{{ group.rows.length }} 条</span
                >
            </h2>
            <div
                class="mt-[var(--brand-space-16)] space-y-[var(--brand-space-16)]"
            >
                <details
                    v-for="row in group.rows"
                    :key="row.source_id"
                    class="rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] bg-[var(--brand-soft)]/50 px-[var(--brand-space-16)] py-[var(--brand-space-16)] text-sm"
                >
                    <summary
                        class="flex cursor-pointer flex-wrap items-center justify-between gap-[var(--brand-space-8)]"
                    >
                        <span class="font-medium text-[var(--brand-title)]">{{
                            row.title ||
                            field(parseDetails(row.details), 'mc') ||
                            row.number ||
                            '记录'
                        }}</span
                        ><span class="text-[var(--brand-body)]"
                            >{{ money(row.amount_cents) }} ·
                            {{ row.status || '查看详情' }}</span
                        >
                    </summary>
                    <dl
                        class="mt-[var(--brand-space-16)] grid gap-[var(--brand-space-8)] border-t border-[var(--brand-border)] pt-[var(--brand-space-16)] md:grid-cols-2"
                    >
                        <div
                            v-for="[key, value] in Object.entries(
                                parseDetails(row.details),
                            )"
                            :key="key"
                            v-show="value !== null && value !== ''"
                            class="break-all"
                        >
                            <dt class="inline text-[var(--brand-muted)]">
                                {{ key }}：
                            </dt>
                            <dd class="inline">{{ String(value) }}</dd>
                        </div>
                    </dl>
                </details>
            </div>
        </section>

        <section
            v-if="timeline.length"
            class="mt-[var(--brand-space-24)] rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)]"
        >
            <h2
                class="font-sans text-xl font-semibold text-[var(--brand-title)]"
            >
                办理轨迹
            </h2>
            <ol
                class="mt-[var(--brand-space-24)] border-l border-[var(--brand-primary)] pl-[var(--brand-space-24)]"
            >
                <li
                    v-for="row in timeline"
                    :key="row.source_id"
                    class="relative pb-[var(--brand-space-24)] text-sm before:absolute before:top-1 before:-left-[25px] before:size-2 before:rounded-full before:bg-[var(--brand-primary)]"
                >
                    <p class="font-medium text-[var(--brand-title)]">
                        {{ field(parseDetails(row.details), 'lcsm') }}
                    </p>
                    <p
                        class="mt-[var(--brand-space-8)] text-xs text-[var(--brand-muted)]"
                    >
                        处理人：{{ field(parseDetails(row.details), 'zxr') === '—' ? '原系统未记录' : field(parseDetails(row.details), 'zxr') }}
                        · 处理时间：{{ row.occurred_at?.slice(0, 16) || '原系统未记录' }}
                    </p>
                    <p class="mt-[var(--brand-space-8)] text-xs text-[var(--brand-body)]">结果：{{ row.status || '原系统未记录' }}</p>
                </li>
            </ol>
        </section>

        <section
            class="mt-[var(--brand-space-24)] rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)]"
        >
            <h2
                class="font-sans text-xl font-semibold text-[var(--brand-title)]"
            >
                附件索引
                <span
                    class="ml-[var(--brand-space-8)] text-sm font-normal text-[var(--brand-muted)]"
                    >{{ attachments.length }} 条</span
                >
            </h2>
            <p
                class="mt-[var(--brand-space-8)] text-sm text-[var(--brand-warning)]"
            >
                文件待迁移，暂不提供下载。
            </p>
            <ul
                v-if="attachments.length"
                class="mt-[var(--brand-space-16)] divide-y divide-[var(--brand-border)] text-sm"
            >
                <li
                    v-for="(file, index) in attachments"
                    :key="index"
                    class="flex justify-between gap-[var(--brand-space-16)] py-[var(--brand-space-8)]"
                >
                    <span>{{ file.name || '未命名附件' }}</span
                    ><span class="text-[var(--brand-muted)]">{{
                        labels[file.owner_table] || file.owner_table
                    }} · 文件待迁移</span>
                </li>
            </ul>
        </section>
    </main>
</template>
