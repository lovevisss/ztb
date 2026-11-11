<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { canPreviewAttachment } from '@/lib/procurement-attachment';

type Event = {
    id: number;
    event: string;
    details: string | null;
    created_at: string;
};
type Step = {
    id: number;
    attempt: number;
    position: number;
    role_code: string;
    status: string;
    opinion: string | null;
};
type Attachment = {
    id: number;
    kind: string;
    original_name: string;
    size: number;
    mime_type: string | null;
};
defineProps<{
    application: {
        id: number;
        title: string;
        project_number: string;
        budget_cents: number;
        application_form: string;
        status: string;
        reason: string | null;
        submitted_at: string | null;
    };
    archive: {
        archived_at: string;
        required_kinds_snapshot: string;
        attachment_ids_snapshot: string;
    };
    execution: {
        actual_amount_cents: number;
        supplier: string;
        completed_on: string;
        result_note: string;
    };
    approvalSteps: Step[];
    approvalEvents: Event[];
    executionEvents: Event[];
    attachments: Attachment[];
}>();
const kindLabels: Record<string, string> = {
    contract: '采购合同',
    result_announcement: '结果公告',
    acceptance: '验收材料',
    invoice: '发票',
    other_execution: '其他执行材料',
    requirement: '采购需求',
    budget_basis: '预算依据',
};
</script>

<template>
    <Head :title="`项目档案 · ${application.title}`" />
    <main
        class="mx-auto max-w-6xl px-[var(--brand-space-24)] py-[var(--brand-space-32)] md:px-[var(--brand-space-32)] md:py-[var(--brand-space-32)]"
    >
        <Link
            href="/procurement/history?status=archived"
            class="text-sm text-[var(--brand-primary)] hover:underline"
            >← 返回已归档项目</Link
        >
        <header
            class="mt-[var(--brand-space-32)] flex flex-wrap justify-between gap-[var(--brand-space-24)] border-b border-[var(--brand-border)] pb-[var(--brand-space-32)]"
        >
            <div>
                <p class="font-mono text-xs text-[var(--brand-primary)]">
                    {{ application.project_number }}
                </p>
                <h1
                    class="mt-[var(--brand-space-8)] font-sans text-3xl font-semibold text-[var(--brand-title)]"
                >
                    {{ application.title }}
                </h1>
                <p
                    class="mt-[var(--brand-space-16)] text-sm text-[var(--brand-muted)]"
                >
                    {{
                        application.application_form === 'central_tender'
                            ? '统一招标'
                            : '部门自行采购'
                    }}
                    · 已归档 · {{ archive.archived_at.slice(0, 10) }}
                </p>
            </div>
            <div class="text-right text-sm text-[var(--brand-body)]">
                申请预算
                <strong
                    class="block font-sans text-2xl text-[var(--brand-title)]"
                    >¥{{
                        (application.budget_cents / 100).toLocaleString('zh-CN')
                    }}</strong
                >
            </div>
        </header>
        <div
            class="mt-[var(--brand-space-32)] grid gap-[var(--brand-space-32)] lg:grid-cols-2"
        >
            <section
                class="rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)] shadow-sm"
            >
                <h2 class="font-sans text-xl text-[var(--brand-title)]">
                    申请与执行
                </h2>
                <dl
                    class="mt-[var(--brand-space-24)] grid grid-cols-2 gap-[var(--brand-space-24)] text-sm"
                >
                    <div>
                        <dt class="text-[var(--brand-muted)]">申请提交</dt>
                        <dd
                            class="mt-[var(--brand-space-8)] text-[var(--brand-title)]"
                        >
                            {{ application.submitted_at?.slice(0, 10) ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[var(--brand-muted)]">完成日期</dt>
                        <dd
                            class="mt-[var(--brand-space-8)] text-[var(--brand-title)]"
                        >
                            {{ execution.completed_on.slice(0, 10) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[var(--brand-muted)]">实际金额</dt>
                        <dd
                            class="mt-[var(--brand-space-8)] text-[var(--brand-title)]"
                        >
                            ¥{{
                                (
                                    execution.actual_amount_cents / 100
                                ).toLocaleString('zh-CN')
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[var(--brand-muted)]">成交供应商</dt>
                        <dd
                            class="mt-[var(--brand-space-8)] text-[var(--brand-title)]"
                        >
                            {{ execution.supplier }}
                        </dd>
                    </div>
                </dl>
                <p
                    v-if="application.reason"
                    class="mt-[var(--brand-space-24)] text-sm whitespace-pre-wrap text-[var(--brand-body)]"
                >
                    {{ application.reason }}
                </p>
                <p
                    class="mt-[var(--brand-space-24)] text-sm whitespace-pre-wrap text-[var(--brand-body)]"
                >
                    {{ execution.result_note }}
                </p>
                <Link
                    :href="`/procurement/applications/${application.id}`"
                    class="mt-[var(--brand-space-24)] inline-block text-sm text-[var(--brand-primary)] underline"
                    >查看完整申请内容</Link
                >
            </section>
            <section
                class="rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)] shadow-sm"
            >
                <h2 class="font-sans text-xl text-[var(--brand-title)]">
                    归档附件
                </h2>
                <ul
                    class="mt-[var(--brand-space-24)] divide-y divide-[var(--brand-border)]"
                >
                    <li
                        v-for="file in attachments"
                        :key="file.id"
                        class="flex flex-wrap justify-between gap-[var(--brand-space-8)] py-[var(--brand-space-16)] text-sm"
                    >
                        <span class="text-[var(--brand-muted)]">{{
                            kindLabels[file.kind] ?? file.kind
                        }}</span>
                        <span class="flex flex-wrap items-center gap-[var(--brand-space-16)]">
                            <span class="break-all text-[var(--brand-body)]">{{ file.original_name }}</span>
                            <a v-if="canPreviewAttachment(file.mime_type)" :href="`/procurement/attachments/${file.id}/preview`" target="_blank" rel="noopener noreferrer" class="font-medium text-[var(--brand-primary)] hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--brand-primary)]">预览</a>
                            <a :href="`/procurement/attachments/${file.id}/download`" class="font-medium text-[var(--brand-primary)] hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--brand-primary)]">下载</a>
                        </span>
                    </li>
                </ul>
            </section>
            <section
                class="rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)] shadow-sm"
            >
                <h2 class="font-sans text-xl text-[var(--brand-title)]">
                    审批记录
                </h2>
                <ol
                    class="mt-[var(--brand-space-24)] space-y-[var(--brand-space-16)] border-l border-[var(--brand-border)] pl-[var(--brand-space-24)] text-sm"
                >
                    <li v-for="step in approvalSteps" :key="step.id">
                        <strong
                            >第 {{ step.attempt }} 轮 · {{ step.role_code }} ·
                            {{ step.status }}</strong
                        >
                        <p
                            v-if="step.opinion"
                            class="mt-[var(--brand-space-8)] whitespace-pre-wrap text-[var(--brand-body)]"
                        >
                            {{ step.opinion }}
                        </p>
                    </li>
                </ol>
                <ul
                    class="mt-[var(--brand-space-24)] space-y-[var(--brand-space-8)] text-xs text-[var(--brand-muted)]"
                >
                    <li v-for="event in approvalEvents" :key="event.id">
                        {{ event.created_at.slice(0, 16) }} ·
                        {{ event.event }} · {{ event.details ?? '无备注' }}
                    </li>
                </ul>
            </section>
            <section
                class="rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)] shadow-sm"
            >
                <h2 class="font-sans text-xl text-[var(--brand-title)]">
                    执行与归档记录
                </h2>
                <ul
                    class="mt-[var(--brand-space-24)] space-y-[var(--brand-space-16)] text-sm text-[var(--brand-body)]"
                >
                    <li v-for="event in executionEvents" :key="event.id">
                        {{ event.created_at.slice(0, 16) }} ·
                        {{ event.event }} · {{ event.details ?? '无备注' }}
                    </li>
                </ul>
            </section>
        </div>
    </main>
</template>
