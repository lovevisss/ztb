<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

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
    <main class="mx-auto max-w-6xl px-5 py-8 md:px-10 md:py-12">
        <Link
            href="/procurement/history?status=archived"
            class="text-sm text-emerald-800 hover:underline"
            >← 返回已归档项目</Link
        >
        <header
            class="mt-7 flex flex-wrap justify-between gap-5 border-b border-slate-200 pb-7"
        >
            <div>
                <p class="font-mono text-xs text-emerald-700">
                    {{ application.project_number }}
                </p>
                <h1
                    class="mt-2 font-serif text-3xl font-semibold text-slate-900"
                >
                    {{ application.title }}
                </h1>
                <p class="mt-3 text-sm text-slate-500">
                    {{
                        application.application_form === 'central_tender'
                            ? '统一招标'
                            : '部门自行采购'
                    }}
                    · 已归档 · {{ archive.archived_at.slice(0, 10) }}
                </p>
            </div>
            <div class="text-right text-sm text-slate-600">
                申请预算
                <strong class="block font-serif text-2xl text-slate-900"
                    >¥{{
                        (application.budget_cents / 100).toLocaleString('zh-CN')
                    }}</strong
                >
            </div>
        </header>
        <div class="mt-8 grid gap-7 lg:grid-cols-2">
            <section
                class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm"
            >
                <h2 class="font-serif text-xl text-slate-900">申请与执行</h2>
                <dl class="mt-5 grid grid-cols-2 gap-5 text-sm">
                    <div>
                        <dt class="text-slate-500">申请提交</dt>
                        <dd class="mt-1 text-slate-900">
                            {{ application.submitted_at?.slice(0, 10) ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">完成日期</dt>
                        <dd class="mt-1 text-slate-900">
                            {{ execution.completed_on.slice(0, 10) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">实际金额</dt>
                        <dd class="mt-1 text-slate-900">
                            ¥{{
                                (
                                    execution.actual_amount_cents / 100
                                ).toLocaleString('zh-CN')
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">成交供应商</dt>
                        <dd class="mt-1 text-slate-900">
                            {{ execution.supplier }}
                        </dd>
                    </div>
                </dl>
                <p
                    v-if="application.reason"
                    class="mt-6 text-sm whitespace-pre-wrap text-slate-700"
                >
                    {{ application.reason }}
                </p>
                <p class="mt-5 text-sm whitespace-pre-wrap text-slate-700">
                    {{ execution.result_note }}
                </p>
                <Link
                    :href="`/procurement/applications/${application.id}`"
                    class="mt-5 inline-block text-sm text-emerald-800 underline"
                    >查看完整申请内容</Link
                >
            </section>
            <section
                class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm"
            >
                <h2 class="font-serif text-xl text-slate-900">归档附件</h2>
                <ul class="mt-5 divide-y divide-slate-100">
                    <li
                        v-for="file in attachments"
                        :key="file.id"
                        class="flex flex-wrap justify-between gap-2 py-3 text-sm"
                    >
                        <span class="text-slate-500">{{
                            kindLabels[file.kind] ?? file.kind
                        }}</span>
                        <a
                            :href="`/procurement/attachments/${file.id}/download`"
                            class="font-medium text-emerald-800 hover:underline"
                            >{{ file.original_name }} ↗</a
                        >
                    </li>
                </ul>
            </section>
            <section
                class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm"
            >
                <h2 class="font-serif text-xl text-slate-900">审批记录</h2>
                <ol
                    class="mt-5 space-y-4 border-l border-slate-200 pl-5 text-sm"
                >
                    <li v-for="step in approvalSteps" :key="step.id">
                        <strong
                            >第 {{ step.attempt }} 轮 · {{ step.role_code }} ·
                            {{ step.status }}</strong
                        >
                        <p
                            v-if="step.opinion"
                            class="mt-1 whitespace-pre-wrap text-slate-600"
                        >
                            {{ step.opinion }}
                        </p>
                    </li>
                </ol>
                <ul class="mt-5 space-y-2 text-xs text-slate-500">
                    <li v-for="event in approvalEvents" :key="event.id">
                        {{ event.created_at.slice(0, 16) }} ·
                        {{ event.event }} · {{ event.details ?? '无备注' }}
                    </li>
                </ul>
            </section>
            <section
                class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm"
            >
                <h2 class="font-serif text-xl text-slate-900">
                    执行与归档记录
                </h2>
                <ul class="mt-5 space-y-3 text-sm text-slate-600">
                    <li v-for="event in executionEvents" :key="event.id">
                        {{ event.created_at.slice(0, 16) }} ·
                        {{ event.event }} · {{ event.details ?? '无备注' }}
                    </li>
                </ul>
            </section>
        </div>
    </main>
</template>
