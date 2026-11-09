<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

type Step = {
    id: number;
    application_id: number;
    role_code: string;
    title: string;
    project_number: string;
    budget_cents: number;
};

defineProps<{ steps: Step[] }>();
</script>

<template>
    <Head title="待审批项目" />
    <main
        class="mx-auto w-full max-w-6xl px-[var(--brand-space-24)] py-[var(--brand-space-32)] md:px-[var(--brand-space-32)] md:py-[var(--brand-space-32)]"
    >
        <header
            class="border-b border-[var(--brand-border)] pb-[var(--brand-space-32)]"
        >
            <p
                class="mb-[var(--brand-space-8)] text-xs font-semibold tracking-[0.28em] text-[var(--brand-primary)]"
            >
                APPROVAL / 审批工作台
            </p>
            <h1
                class="font-sans text-3xl font-semibold text-[var(--brand-title)] md:text-4xl"
            >
                待我审批
            </h1>
            <p
                class="mt-[var(--brand-space-16)] text-sm text-[var(--brand-muted)]"
            >
                按顺序处理当前流转到你的采购申请。
            </p>
        </header>
        <div
            v-if="steps.length === 0"
            class="mt-[var(--brand-space-32)] rounded-[var(--brand-radius-md)] border border-dashed border-[var(--brand-border)] bg-[var(--brand-soft)] px-[var(--brand-space-24)] py-[var(--brand-space-32)] text-center text-[var(--brand-muted)]"
        >
            当前没有待审批项目
        </div>
        <div
            v-else
            class="mt-[var(--brand-space-32)] space-y-[var(--brand-space-16)]"
        >
            <article
                v-for="step in steps"
                :key="step.id"
                class="flex flex-wrap items-center justify-between gap-[var(--brand-space-16)] rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] px-[var(--brand-space-24)] py-[var(--brand-space-24)] shadow-sm"
            >
                <div>
                    <p class="font-mono text-xs text-[var(--brand-muted)]">
                        {{ step.project_number }}
                    </p>
                    <h2
                        class="mt-[var(--brand-space-8)] font-sans text-lg font-semibold text-[var(--brand-title)]"
                    >
                        {{ step.title }}
                    </h2>
                    <p
                        class="mt-[var(--brand-space-8)] text-xs text-[var(--brand-muted)]"
                    >
                        项目预算 ¥{{
                            (step.budget_cents / 100).toLocaleString('zh-CN')
                        }}
                    </p>
                </div>
                <Link :href="`/procurement/applications/${step.application_id}`"
                    ><el-button type="primary">查看并审批</el-button></Link
                >
            </article>
        </div>
    </main>
</template>
