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
    <main class="mx-auto w-full max-w-6xl px-5 py-8 md:px-10 md:py-12">
        <header class="border-b border-slate-200 pb-7">
            <p
                class="mb-2 text-xs font-semibold tracking-[0.28em] text-emerald-700"
            >
                APPROVAL / 审批工作台
            </p>
            <h1
                class="font-serif text-3xl font-semibold text-slate-900 md:text-4xl"
            >
                待我审批
            </h1>
            <p class="mt-3 text-sm text-slate-500">
                按顺序处理当前流转到你的采购申请。
            </p>
        </header>
        <div
            v-if="steps.length === 0"
            class="mt-8 rounded-lg border border-dashed border-slate-300 bg-stone-50 px-6 py-16 text-center text-slate-500"
        >
            当前没有待审批项目
        </div>
        <div v-else class="mt-8 space-y-3">
            <article
                v-for="step in steps"
                :key="step.id"
                class="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-slate-200 bg-white px-6 py-5 shadow-sm"
            >
                <div>
                    <p class="font-mono text-xs text-slate-400">
                        {{ step.project_number }}
                    </p>
                    <h2
                        class="mt-1 font-serif text-lg font-semibold text-slate-900"
                    >
                        {{ step.title }}
                    </h2>
                    <p class="mt-2 text-xs text-slate-500">
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
