<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

type Supplier = {
    id: number;
    name: string;
    contact_name: string;
    contact_phone: string;
    is_active: boolean;
    matched_count: number;
    project_count: number;
    contents: string[];
};

const props = defineProps<{
    suppliers: {
        data: Supplier[];
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: { keyword: string; supplier: string; status: string };
}>();
const form = reactive({ ...props.filters });

function search() {
    router.get('/procurement/supplier-deals', {
        keyword: form.keyword.trim() || undefined,
        supplier: form.supplier.trim() || undefined,
        status: form.status || undefined,
    });
}
</script>

<template>
    <Head title="按采购内容查供应商" />
    <main class="mx-auto w-full max-w-6xl px-4 py-8 sm:px-8">
        <Link href="/procurement/suppliers" class="text-sm text-[var(--brand-primary)] hover:underline">← 返回供应商库</Link>
        <header class="mt-6">
            <h1 class="text-[28px] leading-[1.3] font-semibold text-[var(--brand-title)]">按采购内容查供应商</h1>
            <p class="mt-2 text-sm text-[var(--brand-muted)]">查询部门自行采购已登记的成交供应商及联系方式，点击名称可查看全部成交项目。</p>
        </header>

        <form class="mt-6 grid min-w-0 gap-4 rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-6 shadow-[var(--brand-shadow)] sm:grid-cols-2 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_8rem_auto]" @submit.prevent="search">
            <label class="text-sm text-[var(--brand-body)]">
                采购内容
                <el-input v-model="form.keyword" class="mt-2" maxlength="200" placeholder="输入商品、规格、服务或工程关键词" />
            </label>
            <label class="text-sm text-[var(--brand-body)]">
                供应商名称
                <el-input v-model="form.supplier" class="mt-2" maxlength="200" placeholder="输入供应商名称" />
            </label>
            <label class="text-sm text-[var(--brand-body)]">
                供应商状态
                <select v-model="form.status" class="mt-2 block min-h-10 w-full border px-3 focus-visible:outline-2 focus-visible:outline-[var(--brand-primary)]">
                    <option value="">全部状态</option>
                    <option value="active">已启用</option>
                    <option value="inactive">已停用</option>
                </select>
            </label>
            <div class="flex items-end gap-3">
                <el-button type="primary" native-type="submit" class="min-h-10">查询</el-button>
                <Link href="/procurement/supplier-deals" class="inline-flex min-h-10 items-center px-3 text-sm text-[var(--brand-primary)] hover:underline">重置</Link>
            </div>
        </form>
        <p v-if="$page.props.errors.keyword || $page.props.errors.supplier || $page.props.errors.status" role="alert" class="mt-3 text-sm text-[var(--brand-danger)]">
            {{ $page.props.errors.keyword || $page.props.errors.supplier || $page.props.errors.status }}
        </p>

        <section class="mt-8" aria-labelledby="supplier-results">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="supplier-results" class="text-xl font-semibold text-[var(--brand-title)]">查询结果</h2>
                <p class="text-sm text-[var(--brand-muted)]">共 {{ suppliers.total }} 家供应商</p>
            </div>
            <ul v-if="suppliers.data.length" class="mt-4 divide-y divide-[var(--brand-border)] rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] shadow-[var(--brand-shadow)]">
                <li v-for="supplier in suppliers.data" :key="supplier.id" class="grid min-w-0 gap-4 p-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,0.75fr)]">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-baseline gap-2">
                            <Link :href="`/procurement/supplier-deals/${supplier.id}`" class="break-words text-base font-semibold text-[var(--brand-primary)] hover:underline">{{ supplier.name }}</Link>
                            <span class="rounded-lg bg-[var(--brand-soft)] px-2 py-1 text-xs" :class="supplier.is_active ? 'text-[var(--brand-success)]' : 'text-[var(--brand-muted)]'">{{ supplier.is_active ? '已启用' : '已停用' }}</span>
                        </div>
                        <p class="mt-2 break-words text-sm text-[var(--brand-body)]">
                            {{ supplier.contact_name }}
                            <span class="ml-2 tabular-nums">{{ supplier.contact_phone }}</span>
                        </p>
                        <p class="mt-2 text-xs text-[var(--brand-muted)]">
                            <template v-if="filters.keyword">匹配 {{ supplier.matched_count }} 个项目 · </template>累计成交 {{ supplier.project_count }} 个项目
                        </p>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-[var(--brand-muted)]">{{ filters.keyword ? '匹配的采购内容' : '采购内容示例' }}</p>
                        <ul v-if="supplier.contents.length" class="mt-2 space-y-1 text-sm break-words text-[var(--brand-body)]">
                            <li v-for="content in supplier.contents" :key="content">{{ content }}</li>
                        </ul>
                        <p v-else class="mt-2 text-sm text-[var(--brand-muted)]">暂无采购内容</p>
                    </div>
                </li>
            </ul>
            <p v-else class="mt-4 rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-8 text-center text-sm text-[var(--brand-muted)]">没有符合条件的成交供应商。</p>
            <nav v-if="suppliers.last_page > 1" aria-label="供应商查询分页" class="mt-4 flex flex-wrap items-center gap-4 text-sm text-[var(--brand-muted)]">
                <Link v-if="suppliers.prev_page_url" :href="suppliers.prev_page_url" class="text-[var(--brand-primary)] hover:underline">上一页</Link>
                <span>{{ suppliers.current_page }} / {{ suppliers.last_page }}</span>
                <Link v-if="suppliers.next_page_url" :href="suppliers.next_page_url" class="text-[var(--brand-primary)] hover:underline">下一页</Link>
            </nav>
        </section>
    </main>
</template>
