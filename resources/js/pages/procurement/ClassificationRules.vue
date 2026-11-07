<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

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

const form = useForm({
    threshold_yuan: '100000',
    scope: 'global',
    department_id: '',
    effective_at: '',
});

function save() {
    form.post('/procurement/classification-rules', {
        onSuccess: () => form.reset(),
    });
}

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
                设置预算分界金额与生效时间。已经提交的项目保留原分类结果。
            </p>
        </header>

        <div
            class="mt-[var(--brand-space-32)] grid gap-[var(--brand-space-32)] lg:grid-cols-[minmax(0,1fr)_340px]"
        >
            <section
                class="overflow-hidden rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] bg-[var(--brand-card)] shadow-sm"
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
                        到达生效时间后，新提交项目自动采用适用规则。
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[540px] text-left text-sm">
                        <thead
                            class="bg-[var(--brand-page)] text-xs font-semibold text-[var(--brand-muted)]"
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
                                        >当前</span
                                    >
                                    <span
                                        v-else-if="rule.is_scheduled"
                                        class="ml-[var(--brand-space-8)] rounded-full bg-[var(--brand-page)] px-[var(--brand-space-8)] py-[var(--brand-space-8)] text-xs text-[var(--brand-warning)]"
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

            <section
                class="h-fit rounded-[var(--brand-radius-md)] border border-[var(--brand-primary)] bg-[var(--brand-primary-light)]/60 p-[var(--brand-space-24)] lg:sticky lg:top-8"
            >
                <p
                    class="text-xs font-semibold tracking-widest text-[var(--brand-primary)]"
                >
                    新增规则
                </p>
                <h2
                    class="mt-[var(--brand-space-8)] font-sans text-xl text-[var(--brand-title)]"
                >
                    安排下一版分类标准
                </h2>
                <form
                    class="mt-[var(--brand-space-24)] space-y-[var(--brand-space-24)]"
                    @submit.prevent="save"
                >
                    <label class="block">
                        <span class="field-label">预算分界金额（元）</span>
                        <input
                            v-model="form.threshold_yuan"
                            type="number"
                            min="0.01"
                            step="0.01"
                            class="field-input"
                        />
                        <span
                            v-if="form.errors.threshold_yuan"
                            class="field-error"
                            >{{ form.errors.threshold_yuan }}</span
                        >
                    </label>
                    <label class="block">
                        <span class="field-label">适用范围</span>
                        <select
                            v-model="form.scope"
                            class="field-input"
                            @change="form.department_id = ''"
                        >
                            <option value="global">全校</option>
                            <option value="department">指定部门</option>
                        </select>
                        <span v-if="form.errors.scope" class="field-error">{{
                            form.errors.scope
                        }}</span>
                    </label>
                    <label v-if="form.scope === 'department'" class="block">
                        <span class="field-label">部门</span>
                        <select
                            v-model="form.department_id"
                            class="field-input"
                        >
                            <option value="">请选择部门</option>
                            <option
                                v-for="department in departments"
                                :key="department.id"
                                :value="department.id"
                            >
                                {{ department.name }}
                            </option>
                        </select>
                        <span
                            v-if="form.errors.department_id"
                            class="field-error"
                            >{{ form.errors.department_id }}</span
                        >
                    </label>
                    <label class="block">
                        <span class="field-label">生效时间</span>
                        <input
                            v-model="form.effective_at"
                            type="datetime-local"
                            class="field-input"
                        />
                        <span
                            v-if="form.errors.effective_at"
                            class="field-error"
                            >{{ form.errors.effective_at }}</span
                        >
                    </label>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full rounded-[var(--brand-radius-md)] bg-[var(--brand-primary)] px-[var(--brand-space-24)] py-[var(--brand-space-16)] text-sm font-medium text-[var(--brand-card)] hover:bg-[var(--brand-primary)] disabled:opacity-50"
                    >
                        保存规则
                    </button>
                    <p class="text-xs leading-5 text-[var(--brand-body)]">
                        部门规则优先于全校规则。调整后只影响生效时间之后提交的申请。
                    </p>
                </form>
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
    border-radius: 0.375rem;
    background: white;
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
