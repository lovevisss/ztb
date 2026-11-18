<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

type Rule = {
    id: number;
    threshold_cents: number;
    scope: 'global' | 'department';
    department_id: number | null;
    effective_at: string;
    is_active: boolean;
    is_scheduled: boolean;
};
type Department = { id: number; name: string };

const props = defineProps<{ rules: Rule[]; departments: Department[] }>();

function departmentName(id: number | null) {
    return id === null
        ? '全校'
        : (props.departments.find((department) => department.id === id)?.name ??
              `部门 #${id}`);
}
</script>

<template>
    <Head title="采购分类规则" />
    <main
        class="mx-auto w-full max-w-6xl px-[var(--brand-space-24)] py-[var(--brand-space-32)] md:px-[var(--brand-space-32)] md:py-[var(--brand-space-32)]"
    >
        <header
            class="border-b border-[var(--brand-border)] pb-[var(--brand-space-32)]"
        >
            <p
                class="mb-[var(--brand-space-8)] text-xs font-semibold tracking-[0.28em] text-[var(--brand-primary)]"
            >
                POLICY / 采购管理
            </p>
            <h1
                class="font-sans text-3xl font-semibold text-[var(--brand-title)] md:text-4xl"
            >
                采购分类规则
            </h1>
            <p
                class="mt-[var(--brand-space-16)] text-sm text-[var(--brand-muted)]"
            >
                新申请以10万元为固定分界金额。下列历史规则仅供查阅，已提交项目保留原分类结果。
            </p>
        </header>

        <div
            class="mt-[var(--brand-space-32)]"
        >
            <section
                class="overflow-hidden rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] shadow-sm"
            >
                <div
                    class="border-b border-[var(--brand-border)] px-[var(--brand-space-24)] py-[var(--brand-space-24)]"
                >
                    <h2
                        class="font-sans text-xl font-semibold text-[var(--brand-title)]"
                    >
                        规则记录
                    </h2>
                    <p
                        class="mt-[var(--brand-space-8)] text-xs text-[var(--brand-muted)]"
                    >
                        历史规则记录，不再用于新提交项目。
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[540px] text-left text-sm">
                        <thead
                            class="bg-[var(--brand-soft)] text-xs font-semibold text-[var(--brand-muted)]"
                        >
                            <tr>
                                <th
                                    class="px-[var(--brand-space-24)] py-[var(--brand-space-16)]"
                                >
                                    适用范围
                                </th>
                                <th
                                    class="px-[var(--brand-space-24)] py-[var(--brand-space-16)]"
                                >
                                    分界金额
                                </th>
                                <th
                                    class="px-[var(--brand-space-24)] py-[var(--brand-space-16)]"
                                >
                                    生效时间
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--brand-border)]">
                            <tr v-for="rule in rules" :key="rule.id">
                                <td
                                    class="px-[var(--brand-space-24)] py-[var(--brand-space-24)] font-medium text-[var(--brand-title)]"
                                >
                                    {{ departmentName(rule.department_id) }}
                                    <span
                                        v-if="rule.is_active"
                                        class="ml-[var(--brand-space-8)] rounded-full bg-[var(--brand-primary-light)] px-[var(--brand-space-8)] py-[var(--brand-space-8)] text-xs text-[var(--brand-primary)]"
                                        >历史生效</span
                                    >
                                    <span
                                        v-else-if="rule.is_scheduled"
                                        class="ml-[var(--brand-space-8)] rounded-full bg-[var(--brand-soft)] px-[var(--brand-space-8)] py-[var(--brand-space-8)] text-xs text-[var(--brand-warning)]"
                                        >待生效</span
                                    >
                                </td>
                                <td
                                    class="px-[var(--brand-space-24)] py-[var(--brand-space-24)] text-[var(--brand-title)] tabular-nums"
                                >
                                    ¥{{
                                        (
                                            rule.threshold_cents / 100
                                        ).toLocaleString('zh-CN')
                                    }}
                                </td>
                                <td
                                    class="px-[var(--brand-space-24)] py-[var(--brand-space-24)] text-[var(--brand-muted)]"
                                >
                                    {{
                                        rule.effective_at
                                            .slice(0, 16)
                                            .replace('T', ' ')
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>


        </div>
    </main>
</template>

<style scoped>
.field-label {
    display: block;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--brand-body);
}
.field-input {
    display: block;
    width: 100%;
    border: 1px solid var(--brand-border);
    border-radius: 10px;
    background: var(--brand-card);
    padding: 0.7rem 0.85rem;
    font-size: 0.875rem;
    color: var(--brand-title);
    outline: none;
}
.field-input:focus {
    border-color: var(--brand-primary);
    box-shadow: 0 0 0 3px
        color-mix(in srgb, var(--brand-primary) 20%, transparent);
}
.field-error {
    display: block;
    margin-top: 0.35rem;
    font-size: 0.75rem;
    color: var(--brand-danger);
}
</style>
