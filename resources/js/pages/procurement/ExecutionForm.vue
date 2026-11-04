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
    <main class="mx-auto w-full max-w-5xl px-5 py-8 md:px-10 md:py-12">
        <Link
            :href="`/procurement/applications/${application.id}`"
            class="text-sm text-emerald-800 hover:underline"
            >← 返回项目</Link
        >
        <header class="mt-7 border-b border-slate-200 pb-7">
            <p class="font-mono text-xs text-emerald-700">
                {{ application.project_number }}
            </p>
            <h1 class="mt-2 font-serif text-3xl font-semibold text-slate-900">
                采购执行登记
            </h1>
            <p class="mt-3 text-sm text-slate-600">
                {{ application.title }} · 预算 ¥{{
                    (application.budget_cents / 100).toLocaleString('zh-CN')
                }}
            </p>
        </header>

        <div class="mt-8 grid gap-7 lg:grid-cols-[minmax(0,1fr)_300px]">
            <form class="space-y-6" @submit.prevent="save('draft')">
                <section
                    class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm"
                >
                    <h2 class="font-serif text-xl text-slate-900">执行结果</h2>
                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <label class="block text-sm text-slate-700"
                            >实际采购金额（元）
                            <el-input
                                v-model="form.actual_amount"
                                class="mt-2"
                                type="number"
                                min="0.01"
                                step="0.01"
                                :disabled="!canEdit"
                            />
                            <span
                                v-if="form.errors.actual_amount"
                                class="text-xs text-red-700"
                                >{{ form.errors.actual_amount }}</span
                            >
                        </label>
                        <label class="block text-sm text-slate-700"
                            >中标或成交供应商
                            <el-input
                                v-model="form.supplier"
                                class="mt-2"
                                :disabled="!canEdit"
                            />
                            <span
                                v-if="form.errors.supplier"
                                class="text-xs text-red-700"
                                >{{ form.errors.supplier }}</span
                            >
                        </label>
                        <label class="block text-sm text-slate-700"
                            >完成日期
                            <el-date-picker
                                v-model="form.completed_on"
                                class="mt-2 !w-full"
                                type="date"
                                value-format="YYYY-MM-DD"
                                placeholder="选择日期"
                                :disabled="!canEdit"
                            />
                            <span
                                v-if="form.errors.completed_on"
                                class="text-xs text-red-700"
                                >{{ form.errors.completed_on }}</span
                            >
                        </label>
                        <label
                            class="block text-sm text-slate-700 sm:col-span-2"
                            >结果说明
                            <el-input
                                v-model="form.result_note"
                                class="mt-2"
                                type="textarea"
                                :rows="5"
                                :disabled="!canEdit"
                            />
                            <span
                                v-if="form.errors.result_note"
                                class="text-xs text-red-700"
                                >{{ form.errors.result_note }}</span
                            >
                        </label>
                    </div>
                </section>

                <section
                    class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm"
                >
                    <h2 class="font-serif text-xl text-slate-900">执行材料</h2>
                    <p class="mt-2 text-xs text-slate-500">
                        支持 PDF、Word、Excel 和图片；单份不超过 20 MB。
                    </p>
                    <div v-if="canEdit" class="mt-5 grid gap-4 sm:grid-cols-2">
                        <label
                            v-for="item in [
                                ['contract_document', '合同'],
                                ['result_announcement_document', '结果公告'],
                                ['acceptance_document', '验收材料'],
                                ['invoice_document', '发票'],
                            ] as const"
                            :key="item[0]"
                            class="block text-sm text-slate-700"
                        >
                            {{ item[1] }}
                            <input
                                type="file"
                                class="mt-2 block w-full text-xs"
                                accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg"
                                @change="chooseFile($event, item[0])"
                            />
                            <span
                                v-if="form.errors[item[0]]"
                                class="text-xs text-red-700"
                                >{{ form.errors[item[0]] }}</span
                            >
                        </label>
                        <label
                            class="block text-sm text-slate-700 sm:col-span-2"
                            >其他材料
                            <input
                                type="file"
                                multiple
                                class="mt-2 block w-full text-xs"
                                accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg"
                                @change="chooseOthers"
                            />
                            <span
                                v-if="form.errors.other_execution_documents"
                                class="text-xs text-red-700"
                                >{{
                                    form.errors.other_execution_documents
                                }}</span
                            >
                        </label>
                    </div>
                    <ul
                        v-if="attachments.length"
                        class="mt-6 space-y-2 border-t border-slate-100 pt-4"
                    >
                        <li
                            v-for="file in attachments"
                            :key="file.id"
                            class="flex flex-wrap justify-between gap-2 text-sm"
                        >
                            <span class="text-slate-500">{{
                                kinds[file.kind] ?? file.kind
                            }}</span>
                            <a
                                :href="`/procurement/attachments/${file.id}/download`"
                                class="text-emerald-800 hover:underline"
                                >{{ file.original_name }} ↗</a
                            >
                        </li>
                    </ul>
                </section>
                <div v-if="canEdit" class="flex flex-wrap gap-3">
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
                class="h-fit rounded-lg border border-emerald-200 bg-emerald-50 p-6 text-sm text-emerald-950"
            >
                <h2 class="font-serif text-lg">登记说明</h2>
                <p class="mt-3 leading-7">
                    执行中可分次保存。完成登记时须填写实际金额、供应商、完成日期和结果说明。
                </p>
                <p class="mt-3 leading-7">
                    实际金额按执行结果记录，可与申请预算不同。上传的材料仅限有项目查看权限的人员下载。
                </p>
                <p v-if="!canEdit" class="mt-4 font-medium">
                    当前项目仅可查看。
                </p>
            </aside>
        </div>
    </main>
</template>
