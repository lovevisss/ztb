<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

type Attachment = {
    id: number;
    kind: string;
    original_name: string;
    size: number;
};
type Execution = {
    actual_amount_cents: number | null;
    supplier: string | null;
    completed_on: string | null;
    result_note: string | null;
    status: string;
};

const props = defineProps<{
    application: {
        id: number;
        project_number: string;
        title: string;
        budget_cents: number;
        status: string;
    };
    execution: Execution | null;
    attachments: Attachment[];
    canEdit: boolean;
}>();

const form = useForm({
    _method: 'put',
    intent: 'draft',
    actual_amount:
        props.execution?.actual_amount_cents == null
            ? ''
            : String(props.execution.actual_amount_cents / 100),
    supplier: props.execution?.supplier ?? '',
    completed_on: props.execution?.completed_on?.slice(0, 10) ?? '',
    result_note: props.execution?.result_note ?? '',
    contract_document: null as File | null,
    result_announcement_document: null as File | null,
    acceptance_document: null as File | null,
    invoice_document: null as File | null,
    other_execution_documents: [] as File[],
});

const kinds: Record<string, string> = {
    contract: '合同',
    result_announcement: '结果公告',
    acceptance: '验收材料',
    invoice: '发票',
    other_execution: '其他材料',
};

function chooseFile(
    event: Event,
    field:
        | 'contract_document'
        | 'result_announcement_document'
        | 'acceptance_document'
        | 'invoice_document',
) {
    form[field] = (event.target as HTMLInputElement).files?.[0] ?? null;
}

function chooseOthers(event: Event) {
    form.other_execution_documents = Array.from(
        (event.target as HTMLInputElement).files ?? [],
    );
}

function save(intent: 'draft' | 'complete') {
    form.intent = intent;
    form.post(`/procurement/applications/${props.application.id}/execution`, {
        forceFormData: true,
    });
}
</script>

<template>
    <Head :title="`采购执行 · ${application.title}`" />
    <main
        class="mx-auto w-full max-w-5xl px-[var(--brand-space-24)] py-[var(--brand-space-32)] md:px-[var(--brand-space-32)] md:py-[var(--brand-space-32)]"
    >
        <Link
            :href="`/procurement/applications/${application.id}`"
            class="text-sm text-[var(--brand-primary)] hover:underline"
            >← 返回项目</Link
        >
        <header
            class="mt-[var(--brand-space-32)] border-b border-[var(--brand-border)] pb-[var(--brand-space-32)]"
        >
            <p class="font-mono text-xs text-[var(--brand-primary)]">
                {{ application.project_number }}
            </p>
            <h1
                class="mt-[var(--brand-space-8)] font-sans text-3xl font-semibold text-[var(--brand-title)]"
            >
                采购执行登记
            </h1>
            <p
                class="mt-[var(--brand-space-16)] text-sm text-[var(--brand-body)]"
            >
                {{ application.title }} · 预算 ¥{{
                    (application.budget_cents / 100).toLocaleString('zh-CN')
                }}
            </p>
        </header>

        <div
            class="mt-[var(--brand-space-32)] grid gap-[var(--brand-space-32)] lg:grid-cols-[minmax(0,1fr)_300px]"
        >
            <form
                class="space-y-[var(--brand-space-24)]"
                @submit.prevent="save('draft')"
            >
                <section
                    class="rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)] shadow-sm"
                >
                    <h2 class="font-sans text-xl text-[var(--brand-title)]">
                        执行结果
                    </h2>
                    <div
                        class="mt-[var(--brand-space-24)] grid gap-[var(--brand-space-24)] sm:grid-cols-2"
                    >
                        <label class="block text-sm text-[var(--brand-body)]"
                            >实际采购金额（元）
                            <el-input
                                v-model="form.actual_amount"
                                class="mt-[var(--brand-space-8)]"
                                type="number"
                                min="0.01"
                                step="0.01"
                                :disabled="!canEdit"
                            />
                            <span
                                v-if="form.errors.actual_amount"
                                class="text-xs text-[var(--brand-danger)]"
                                >{{ form.errors.actual_amount }}</span
                            >
                        </label>
                        <label class="block text-sm text-[var(--brand-body)]"
                            >中标或成交供应商
                            <el-input
                                v-model="form.supplier"
                                class="mt-[var(--brand-space-8)]"
                                :disabled="!canEdit"
                            />
                            <span
                                v-if="form.errors.supplier"
                                class="text-xs text-[var(--brand-danger)]"
                                >{{ form.errors.supplier }}</span
                            >
                        </label>
                        <label class="block text-sm text-[var(--brand-body)]"
                            >完成日期
                            <el-date-picker
                                v-model="form.completed_on"
                                class="mt-[var(--brand-space-8)] !w-full"
                                type="date"
                                value-format="YYYY-MM-DD"
                                placeholder="选择日期"
                                :disabled="!canEdit"
                            />
                            <span
                                v-if="form.errors.completed_on"
                                class="text-xs text-[var(--brand-danger)]"
                                >{{ form.errors.completed_on }}</span
                            >
                        </label>
                        <label
                            class="block text-sm text-[var(--brand-body)] sm:col-span-2"
                            >结果说明
                            <el-input
                                v-model="form.result_note"
                                class="mt-[var(--brand-space-8)]"
                                type="textarea"
                                :rows="5"
                                :disabled="!canEdit"
                            />
                            <span
                                v-if="form.errors.result_note"
                                class="text-xs text-[var(--brand-danger)]"
                                >{{ form.errors.result_note }}</span
                            >
                        </label>
                    </div>
                </section>

                <section
                    class="rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)] shadow-sm"
                >
                    <h2 class="font-sans text-xl text-[var(--brand-title)]">
                        执行材料
                    </h2>
                    <p
                        class="mt-[var(--brand-space-8)] text-xs text-[var(--brand-muted)]"
                    >
                        支持 PDF、Word、Excel 和图片；单份不超过 20 MB。
                    </p>
                    <div
                        v-if="canEdit"
                        class="mt-[var(--brand-space-24)] grid gap-[var(--brand-space-16)] sm:grid-cols-2"
                    >
                        <label
                            v-for="item in [
                                ['contract_document', '合同'],
                                ['result_announcement_document', '结果公告'],
                                ['acceptance_document', '验收材料'],
                                ['invoice_document', '发票'],
                            ] as const"
                            :key="item[0]"
                            class="block text-sm text-[var(--brand-body)]"
                        >
                            {{ item[1] }}
                            <input
                                type="file"
                                class="mt-[var(--brand-space-8)] block w-full text-xs"
                                accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg"
                                @change="chooseFile($event, item[0])"
                            />
                            <span
                                v-if="form.errors[item[0]]"
                                class="text-xs text-[var(--brand-danger)]"
                                >{{ form.errors[item[0]] }}</span
                            >
                        </label>
                        <label
                            class="block text-sm text-[var(--brand-body)] sm:col-span-2"
                            >其他材料
                            <input
                                type="file"
                                multiple
                                class="mt-[var(--brand-space-8)] block w-full text-xs"
                                accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg"
                                @change="chooseOthers"
                            />
                            <span
                                v-if="form.errors.other_execution_documents"
                                class="text-xs text-[var(--brand-danger)]"
                                >{{
                                    form.errors.other_execution_documents
                                }}</span
                            >
                        </label>
                    </div>
                    <ul
                        v-if="attachments.length"
                        class="mt-[var(--brand-space-24)] space-y-[var(--brand-space-8)] border-t border-[var(--brand-border)] pt-[var(--brand-space-16)]"
                    >
                        <li
                            v-for="file in attachments"
                            :key="file.id"
                            class="flex flex-wrap justify-between gap-[var(--brand-space-8)] text-sm"
                        >
                            <span class="text-[var(--brand-muted)]">{{
                                kinds[file.kind] ?? file.kind
                            }}</span>
                            <a
                                :href="`/procurement/attachments/${file.id}/download`"
                                class="text-[var(--brand-primary)] hover:underline"
                                >{{ file.original_name }} ↗</a
                            >
                        </li>
                    </ul>
                </section>
                <div
                    v-if="canEdit"
                    class="flex flex-wrap gap-[var(--brand-space-16)]"
                >
                    <el-button native-type="submit" :loading="form.processing"
                        >保存进度</el-button
                    >
                    <el-button
                        type="primary"
                        :loading="form.processing"
                        @click="save('complete')"
                        >完成登记</el-button
                    >
                </div>
            </form>
            <aside
                class="h-fit rounded-[var(--brand-radius-md)] border border-[var(--brand-primary)] bg-[var(--brand-primary-light)] p-[var(--brand-space-24)] text-sm text-[var(--brand-primary)]"
            >
                <h2 class="font-sans text-lg">登记说明</h2>
                <p class="mt-[var(--brand-space-16)] leading-7">
                    执行中可分次保存。完成登记时须填写实际金额、供应商、完成日期和结果说明。
                </p>
                <p class="mt-[var(--brand-space-16)] leading-7">
                    实际金额按执行结果记录，可与申请预算不同。上传的材料仅限有项目查看权限的人员下载。
                </p>
                <p
                    v-if="!canEdit"
                    class="mt-[var(--brand-space-16)] font-medium"
                >
                    当前项目仅可查看。
                </p>
            </aside>
        </div>
    </main>
</template>
