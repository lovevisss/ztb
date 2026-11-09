<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

type Rule = {
    id: number;
    application_form: string;
    kind: string;
    required: boolean;
};
const props = defineProps<{
    rules: Rule[];
    kinds: string[];
    canEdit: boolean;
}>();
const labels: Record<string, string> = {
    contract: '采购合同',
    result_announcement: '结果公告',
    acceptance: '验收材料',
    invoice: '发票',
    other_execution: '其他执行材料',
};
const forms = [
    { code: 'self_purchase', label: '部门自行采购' },
    { code: 'central_tender', label: '统一招标' },
];
const selections: Record<string, string[]> = {
    self_purchase: props.rules
        .filter(
            (rule) =>
                rule.application_form === 'self_purchase' && rule.required,
        )
        .map((rule) => rule.kind),
    central_tender: props.rules
        .filter(
            (rule) =>
                rule.application_form === 'central_tender' && rule.required,
        )
        .map((rule) => rule.kind),
};
const form = useForm({
    application_form: 'self_purchase',
    required_kinds: [] as string[],
});

function save(applicationForm: string) {
    form.application_form = applicationForm;
    form.required_kinds = selections[applicationForm] ?? [];
    form.put('/procurement/archive-rules');
}
</script>

<template>
    <Head title="归档材料规则" />
    <main
        class="mx-auto max-w-5xl px-[var(--brand-space-24)] py-[var(--brand-space-32)] md:px-[var(--brand-space-32)] md:py-[var(--brand-space-32)]"
    >
        <Link
            href="/procurement/history"
            class="text-sm text-[var(--brand-primary)] hover:underline"
            >← 返回项目检索</Link
        >
        <header
            class="mt-[var(--brand-space-32)] border-b border-[var(--brand-border)] pb-[var(--brand-space-32)]"
        >
            <h1
                class="font-sans text-3xl font-semibold text-[var(--brand-title)]"
            >
                归档材料规则
            </h1>
            <p
                class="mt-[var(--brand-space-16)] text-sm text-[var(--brand-muted)]"
            >
                按采购表单类型设置必需材料。新规则只影响尚未归档的项目。
            </p>
        </header>
        <div
            class="mt-[var(--brand-space-32)] grid gap-[var(--brand-space-24)] md:grid-cols-2"
        >
            <section
                v-for="item in forms"
                :key="item.code"
                class="rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)] shadow-sm"
            >
                <h2 class="font-sans text-xl text-[var(--brand-title)]">
                    {{ item.label }}
                </h2>
                <el-checkbox-group
                    v-model="selections[item.code]"
                    :disabled="!canEdit"
                    class="mt-[var(--brand-space-24)] flex flex-col gap-[var(--brand-space-16)]"
                >
                    <el-checkbox
                        v-for="kind in kinds"
                        :key="kind"
                        :label="kind"
                        :value="kind"
                        >{{ labels[kind] ?? kind }}</el-checkbox
                    >
                </el-checkbox-group>
                <p
                    v-if="
                        form.errors.required_kinds &&
                        form.application_form === item.code
                    "
                    class="mt-[var(--brand-space-16)] text-xs text-[var(--brand-danger)]"
                >
                    {{ form.errors.required_kinds }}
                </p>
                <el-button
                    v-if="canEdit"
                    class="mt-[var(--brand-space-24)]"
                    type="primary"
                    :loading="form.processing"
                    @click="save(item.code)"
                    >保存规则</el-button
                >
            </section>
        </div>
    </main>
</template>
