<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

type Project = {
    id: number;
    title: string | null;
    project_number: string | null;
    project_name: string | null;
    construction_content: string | null;
    procurement_requirements: string | null;
    department_name: string | null;
    status: string;
    completed_on: string | null;
    actual_amount_cents: number | null;
    items: { id: number; name: string; specification: string | null }[];
};

defineProps<{
    supplier: { id: number; name: string; contact_name: string; contact_phone: string; is_active: boolean };
    projects: {
        data: Project[];
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();

const currency = new Intl.NumberFormat('zh-CN', { style: 'currency', currency: 'CNY' });
</script>

<template>
    <Head :title="`${supplier.name} · 成交项目`" />
    <main class="mx-auto w-full max-w-6xl px-4 py-8 sm:px-8">
        <Link href="/procurement/supplier-deals" class="text-sm text-[var(--brand-primary)] hover:underline">← 返回采购内容查询</Link>
        <header class="mt-6 border-b border-[var(--brand-border)] pb-6">
            <div class="flex flex-wrap items-baseline gap-3">
                <h1 class="break-words text-[28px] leading-[1.3] font-semibold text-[var(--brand-title)]">{{ supplier.name }}</h1>
                <span class="rounded-lg bg-[var(--brand-soft)] px-2 py-1 text-xs" :class="supplier.is_active ? 'text-[var(--brand-success)]' : 'text-[var(--brand-muted)]'">{{ supplier.is_active ? '已启用' : '已停用' }}</span>
            </div>
            <p class="mt-3 break-words text-sm text-[var(--brand-body)]">
                {{ supplier.contact_name }}
                <span class="ml-2 tabular-nums">{{ supplier.contact_phone }}</span>
            </p>
        </header>

        <section class="mt-8" aria-labelledby="deal-history">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="deal-history" class="text-xl font-semibold text-[var(--brand-title)]">全部成交项目</h2>
                <p class="text-sm text-[var(--brand-muted)]">累计 {{ projects.total }} 个部门自行采购项目</p>
            </div>
            <ul v-if="projects.data.length" class="mt-4 grid gap-4">
                <li v-for="project in projects.data" :key="project.id" class="min-w-0 rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-6 shadow-[var(--brand-shadow)]">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <Link :href="`/procurement/applications/${project.id}`" class="break-words text-base font-semibold text-[var(--brand-primary)] hover:underline">{{ project.title || project.project_number || '查看项目' }}</Link>
                        <span class="rounded-lg bg-[var(--brand-soft)] px-2 py-1 text-xs" :class="project.status === 'archived' ? 'text-[var(--brand-success)]' : 'text-[var(--brand-warning)]'">{{ project.status === 'archived' ? '已归档' : '待归档' }}</span>
                    </div>
                    <p v-if="project.project_number" class="mt-1 text-xs text-[var(--brand-muted)]">{{ project.project_number }}</p>
                    <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-3">
                        <div><dt class="text-xs text-[var(--brand-muted)]">申报部门</dt><dd class="mt-1 break-words text-[var(--brand-body)]">{{ project.department_name || '—' }}</dd></div>
                        <div><dt class="text-xs text-[var(--brand-muted)]">成交日期</dt><dd class="mt-1 tabular-nums text-[var(--brand-body)]">{{ project.completed_on || '—' }}</dd></div>
                        <div><dt class="text-xs text-[var(--brand-muted)]">成交金额</dt><dd class="mt-1 tabular-nums text-[var(--brand-body)]">{{ project.actual_amount_cents === null ? '—' : currency.format(project.actual_amount_cents / 100) }}</dd></div>
                    </dl>
                    <div class="mt-4 space-y-2 border-t border-[var(--brand-border)] pt-4 text-sm break-words text-[var(--brand-body)]">
                        <h3 class="text-xs font-semibold text-[var(--brand-muted)]">采购内容</h3>
                        <ul v-if="project.items.length" class="space-y-1">
                            <li v-for="item in project.items" :key="item.id">{{ item.name }}<span v-if="item.specification" class="ml-2 text-[var(--brand-muted)]">{{ item.specification }}</span></li>
                        </ul>
                        <p v-if="project.project_name">{{ project.project_name }}</p>
                        <p v-if="project.construction_content" class="whitespace-pre-line">{{ project.construction_content }}</p>
                        <p v-if="project.procurement_requirements" class="whitespace-pre-line">{{ project.procurement_requirements }}</p>
                        <p v-if="!project.items.length && !project.project_name && !project.construction_content && !project.procurement_requirements">{{ project.title || '—' }}</p>
                    </div>
                </li>
            </ul>
            <p v-else class="mt-4 rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-8 text-center text-sm text-[var(--brand-muted)]">暂无已登记的部门自行采购成交项目。</p>
            <nav v-if="projects.last_page > 1" aria-label="成交项目分页" class="mt-4 flex flex-wrap items-center gap-4 text-sm text-[var(--brand-muted)]">
                <Link v-if="projects.prev_page_url" :href="projects.prev_page_url" class="text-[var(--brand-primary)] hover:underline">上一页</Link>
                <span>{{ projects.current_page }} / {{ projects.last_page }}</span>
                <Link v-if="projects.next_page_url" :href="projects.next_page_url" class="text-[var(--brand-primary)] hover:underline">下一页</Link>
            </nav>
        </section>
    </main>
</template>
