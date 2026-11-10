<script setup lang="ts">
type FlowNode = {
    key: string;
    label: string;
    state: 'done' | 'active' | 'waiting' | 'stopped' | 'recorded';
    time?: string | null;
    person?: string | null;
    personLabel?: string | null;
    result?: string | null;
    timeLabel?: string | null;
    note?: string | null;
};

defineProps<{ title: string; nodes: FlowNode[] }>();

function displayTime(value?: string | null) {
    return value ? value.slice(0, 16).replace('T', ' ') : null;
}
</script>

<template>
    <section class="rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)] shadow-sm">
        <div class="mb-6 flex flex-wrap items-baseline justify-between gap-2 border-b border-[var(--brand-border)] pb-4">
            <h2 class="text-xl font-semibold text-[var(--brand-title)]">{{ title }}</h2>
            <span class="text-xs text-[var(--brand-muted)]">节点、处理人与时间</span>
        </div>
        <div class="overflow-x-auto pb-2" role="region" :aria-label="`${title}节点`" tabindex="0">
            <ol class="flex min-w-max items-start" aria-label="审批流程">
                <li v-for="(node, index) in nodes" :key="node.key" class="flex items-start">
                    <div class="w-44 shrink-0 text-center">
                        <div class="mx-auto flex size-10 items-center justify-center rounded-full border-2 text-sm font-semibold" :class="node.state === 'done' ? 'border-[var(--brand-primary)] bg-[var(--brand-primary)] text-[var(--brand-on-primary)]' : node.state === 'active' ? 'border-[var(--brand-primary)] bg-[var(--brand-primary-light)] text-[var(--brand-primary)]' : node.state === 'stopped' ? 'border-[var(--brand-warning)] bg-[var(--brand-soft)] text-[var(--brand-warning)]' : 'border-[var(--brand-border)] bg-[var(--brand-soft)] text-[var(--brand-muted)]'">
                            {{ node.state === 'done' ? '✓' : index + 1 }}
                        </div>
                        <p class="mt-3 text-sm font-semibold text-[var(--brand-title)]">{{ node.label }}</p>
                        <p v-if="node.personLabel" class="mt-2 text-xs text-[var(--brand-body)]">{{ node.personLabel }}：<span class="font-medium text-[var(--brand-title)]">{{ node.person || '未指定' }}</span></p>
                        <p v-if="node.result" class="mt-2 text-xs font-medium" :class="node.state === 'stopped' ? 'text-[var(--brand-danger)]' : node.state === 'done' ? 'text-[var(--brand-success)]' : node.state === 'active' ? 'text-[var(--brand-warning)]' : 'text-[var(--brand-muted)]'">结果：{{ node.result }}</p>
                        <p class="mt-2 text-xs tabular-nums text-[var(--brand-muted)]">{{ node.timeLabel || '时间' }}：{{ displayTime(node.time) || (node.state === 'waiting' ? '尚未开始' : node.state === 'active' ? '待处理' : '未记录时间') }}</p>
                        <p v-if="node.note" class="mx-auto mt-2 max-w-40 whitespace-pre-wrap break-words text-xs text-[var(--brand-body)]">意见：{{ node.note }}</p>
                    </div>
                    <div v-if="index < nodes.length - 1" class="mt-5 h-px w-8 shrink-0" :class="node.state === 'done' ? 'bg-[var(--brand-primary)]' : 'bg-[var(--brand-border)]'" aria-hidden="true" />
                </li>
            </ol>
        </div>
    </section>
</template>
