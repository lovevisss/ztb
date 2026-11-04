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
    <main class="mx-auto w-full max-w-6xl px-5 py-8 md:px-10 md:py-12">
        <header class="border-b border-slate-200 pb-7">
            <p
                class="mb-2 text-xs font-semibold tracking-[0.28em] text-emerald-700"
            >
                POLICY / 采购管理
            </p>
            <h1
                class="font-serif text-3xl font-semibold text-slate-900 md:text-4xl"
            >
                采购分类规则
            </h1>
            <p class="mt-3 text-sm text-slate-500">
                设置预算分界金额与生效时间。已经提交的项目保留原分类结果。
            </p>
        </header>

        <div class="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_340px]">
            <section
                class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm"
            >
                <div class="border-b border-slate-100 px-6 py-5">
                    <h2 class="font-serif text-xl font-semibold text-slate-900">
                        规则记录
                    </h2>
                    <p class="mt-1 text-xs text-slate-500">
                        到达生效时间后，新提交项目自动采用适用规则。
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[540px] text-left text-sm">
                        <thead
                            class="bg-slate-50 text-xs font-semibold text-slate-500"
                        >
                            <tr>
                                <th class="px-6 py-4">适用范围</th>
                                <th class="px-6 py-4">分界金额</th>
                                <th class="px-6 py-4">生效时间</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="rule in rules" :key="rule.id">
                                <td
                                    class="px-6 py-5 font-medium text-slate-800"
                                >
                                    {{ departmentName(rule.department_id) }}
                                    <span
                                        v-if="rule.is_active"
                                        class="ml-2 rounded-full bg-emerald-50 px-2 py-1 text-xs text-emerald-800"
                                        >当前</span
                                    >
                                    <span
                                        v-else-if="rule.is_scheduled"
                                        class="ml-2 rounded-full bg-amber-50 px-2 py-1 text-xs text-amber-800"
                                        >待生效</span
                                    >
                                </td>
                                <td
                                    class="px-6 py-5 text-slate-800 tabular-nums"
                                >
                                    ¥{{
                                        (
                                            rule.threshold_cents / 100
                                        ).toLocaleString('zh-CN')
                                    }}
                                </td>
                                <td class="px-6 py-5 text-slate-500">
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
                class="h-fit rounded-lg border border-emerald-200 bg-emerald-50/60 p-6 lg:sticky lg:top-8"
            >
                <p
                    class="text-xs font-semibold tracking-widest text-emerald-800"
                >
                    新增规则
                </p>
                <h2 class="mt-2 font-serif text-xl text-slate-900">
                    安排下一版分类标准
                </h2>
                <form class="mt-6 space-y-5" @submit.prevent="save">
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
                        class="w-full rounded-md bg-emerald-800 px-5 py-3 text-sm font-medium text-white hover:bg-emerald-900 disabled:opacity-50"
                    >
                        保存规则
                    </button>
                    <p class="text-xs leading-5 text-slate-600">
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
    color: #334155;
}
.field-input {
    display: block;
    width: 100%;
    border: 1px solid #cbd5e1;
    border-radius: 0.375rem;
    background: white;
    padding: 0.7rem 0.85rem;
    font-size: 0.875rem;
    color: #0f172a;
    outline: none;
}
.field-input:focus {
    border-color: #047857;
    box-shadow: 0 0 0 3px #04785720;
}
.field-error {
    display: block;
    margin-top: 0.35rem;
    font-size: 0.75rem;
    color: #b91c1c;
}
</style>
