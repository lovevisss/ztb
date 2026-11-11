<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

type RecordRow = {
    id: number;
    actor_name: string | null;
    category: string;
    summary: string;
    subject_label: string | null;
    subject_id: string | null;
    outcome: string;
    created_at: string;
};
type PageLink = { url: string | null; label: string; active: boolean };
const props = defineProps<{
    records: { data: RecordRow[]; links: PageLink[]; total: number };
    filters: { search?: string; category?: string; from?: string; to?: string };
}>();
const form = reactive({
    search: props.filters.search ?? '',
    category: props.filters.category ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});
const categories: Record<string, string> = {
    login: '登录', procurement: '采购业务', approval: '审批', execution: '执行与归档',
    configuration: '配置', account: '账号与角色',
};
function search() {
    router.get('/admin/operation-records', Object.fromEntries(Object.entries(form).filter(([, value]) => value.trim() !== '')), { preserveState: true });
}
function displayTime(value: string) {
    return value.slice(0, 19).replace('T', ' ');
}
function pageLabel(label: string) {
    return label.replace('&laquo;', '«').replace('&raquo;', '»');
}
</script>

<template>
    <Head title="操作记录" />
    <main class="mx-auto w-full max-w-6xl px-[var(--brand-space-24)] py-[var(--brand-space-32)] md:px-[var(--brand-space-32)]">
        <header class="border-b border-[var(--brand-border)] pb-[var(--brand-space-24)]">
            <h1 class="text-3xl font-semibold text-[var(--brand-title)]">操作记录</h1>
            <p class="mt-[var(--brand-space-8)] text-sm text-[var(--brand-muted)]">查看登录和重要业务操作的摘要。</p>
        </header>
        <form class="mt-[var(--brand-space-24)] grid gap-[var(--brand-space-16)] rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)] shadow-sm sm:grid-cols-2 xl:grid-cols-[2fr_1fr_1fr_1fr_auto]" @submit.prevent="search">
            <label class="text-sm text-[var(--brand-body)]">操作人或对象<input v-model="form.search" type="search" maxlength="100" placeholder="姓名、项目编号或对象 ID" class="mt-[var(--brand-space-8)] min-h-10 w-full rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] bg-[var(--brand-card)] px-[var(--brand-space-16)] text-[var(--brand-title)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--brand-primary)]" /></label>
            <label class="text-sm text-[var(--brand-body)]">类别<select v-model="form.category" class="mt-[var(--brand-space-8)] min-h-10 w-full rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] bg-[var(--brand-card)] px-[var(--brand-space-16)] text-[var(--brand-title)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--brand-primary)]"><option value="">全部类别</option><option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option></select></label>
            <label class="text-sm text-[var(--brand-body)]">开始日期<input v-model="form.from" type="date" class="mt-[var(--brand-space-8)] min-h-10 w-full rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] bg-[var(--brand-card)] px-[var(--brand-space-16)] text-[var(--brand-title)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--brand-primary)]" /></label>
            <label class="text-sm text-[var(--brand-body)]">结束日期<input v-model="form.to" type="date" class="mt-[var(--brand-space-8)] min-h-10 w-full rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] bg-[var(--brand-card)] px-[var(--brand-space-16)] text-[var(--brand-title)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--brand-primary)]" /></label>
            <button type="submit" class="min-h-10 self-end rounded-[var(--brand-radius-md)] bg-[var(--brand-primary)] px-[var(--brand-space-24)] text-sm font-semibold text-[var(--brand-on-primary)] hover:bg-[var(--brand-primary-hover)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--brand-primary)]">查询</button>
        </form>
        <p class="mt-[var(--brand-space-24)] text-sm text-[var(--brand-muted)]">共 {{ records.total }} 条记录</p>
        <p v-if="!records.data.length" class="mt-[var(--brand-space-16)] rounded-[var(--brand-radius-lg)] border border-dashed border-[var(--brand-border)] bg-[var(--brand-soft)] p-[var(--brand-space-32)] text-center text-sm text-[var(--brand-muted)]">没有符合条件的操作记录</p>
        <div v-else class="mt-[var(--brand-space-16)] overflow-x-auto rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] shadow-sm">
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead class="bg-[var(--brand-soft)] text-xs text-[var(--brand-muted)]"><tr><th class="p-[var(--brand-space-16)]">时间</th><th class="p-[var(--brand-space-16)]">操作人</th><th class="p-[var(--brand-space-16)]">类别</th><th class="p-[var(--brand-space-16)]">操作</th><th class="p-[var(--brand-space-16)]">对象</th><th class="p-[var(--brand-space-16)]">结果</th></tr></thead>
                <tbody class="divide-y divide-[var(--brand-border)] text-[var(--brand-body)]"><tr v-for="record in records.data" :key="record.id" class="hover:bg-[var(--brand-primary-light)]"><td class="whitespace-nowrap p-[var(--brand-space-16)] tabular-nums">{{ displayTime(record.created_at) }}</td><td class="p-[var(--brand-space-16)]">{{ record.actor_name || '未识别' }}</td><td class="p-[var(--brand-space-16)]">{{ categories[record.category] ?? record.category }}</td><td class="p-[var(--brand-space-16)]">{{ record.summary }}</td><td class="max-w-64 break-all p-[var(--brand-space-16)]">{{ record.subject_label || record.subject_id || '—' }}</td><td class="p-[var(--brand-space-16)]" :class="record.outcome === 'failure' ? 'text-[var(--brand-danger)]' : 'text-[var(--brand-success)]'">{{ record.outcome === 'failure' ? '失败' : '成功' }}</td></tr></tbody>
            </table>
        </div>
        <nav v-if="records.links.length > 3" class="mt-[var(--brand-space-24)] flex flex-wrap gap-[var(--brand-space-8)]" aria-label="分页"><Link v-for="(link, index) in records.links" :key="index" v-show="link.url" :href="link.url ?? '#'" class="rounded-[var(--brand-radius-md)] border px-[var(--brand-space-16)] py-[var(--brand-space-8)] text-sm" :class="link.active ? 'border-[var(--brand-primary)] bg-[var(--brand-primary-light)] text-[var(--brand-primary)]' : 'border-[var(--brand-border)] text-[var(--brand-body)]'">{{ pageLabel(link.label) }}</Link></nav>
    </main>
</template>
