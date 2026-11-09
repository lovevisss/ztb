<script setup lang="ts">
type FlowNode = {
    key: string;
    label: string;
    state: 'done' | 'active' | 'waiting' | 'stopped';
    time?: string | null;
    person?: string | null;
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
            <span class="text-xs text-[var(--brand-muted)]">按实际办理记录显示</span>
        </div>
        <div class="overflow-x-auto pb-2">
            <ol class="flex min-w-max items-start" aria-label="审批流程">
                <li v-for="(node, index) in nodes" :key="node.key" class="flex items-start">
                    <div class="w-36 shrink-0 text-center">
                        <div class="mx-auto flex size-10 items-center justify-center rounded-full border-2 text-sm font-semibold" :class="node.state === 'done' ? 'border-[var(--brand-primary)] bg-[var(--brand-primary)] text-[var(--brand-on-primary)]' : node.state === 'active' ? 'border-[var(--brand-primary)] bg-[var(--brand-primary-light)] text-[var(--brand-primary)]' : node.state === 'stopped' ? 'border-[var(--brand-warning)] bg-[var(--brand-soft)] text-[var(--brand-warning)]' : 'border-[var(--brand-border)] bg-[var(--brand-soft)] text-[var(--brand-muted)]'">
                            {{ node.state === 'done' ? '✓' : index + 1 }}
                        </div>
                        <p class="mt-3 text-sm font-semibold text-[var(--brand-title)]">{{ node.label }}</p>
                        <p v-if="node.person" class="mt-1 text-xs text-[var(--brand-body)]">{{ node.person }}</p>
                        <p class="mt-1 text-xs tabular-nums text-[var(--brand-muted)]">{{ displayTime(node.time) || (node.state === 'waiting' ? '待处理' : '尚未完成') }}</p>
                        <p v-if="node.note" class="mt-2 max-w-36 whitespace-pre-wrap break-words text-xs text-[var(--brand-muted)]">{{ node.note }}</p>
                    </div>
                    <div v-if="index < nodes.length - 1" class="mt-5 h-px w-8 shrink-0" :class="node.state === 'done' ? 'bg-[var(--brand-primary)]' : 'bg-[var(--brand-border)]'" aria-hidden="true" />
                </li>
            </ol>
        </div>
    </section>
</template>
