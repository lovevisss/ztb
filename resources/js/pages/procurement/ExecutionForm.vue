<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ElDatePicker } from 'element-plus';
import { computed } from 'vue';
import { canPreviewAttachment } from '@/lib/procurement-attachment';

type Attachment = {
    id: number;
    kind: string;
    original_name: string;
    size: number;
    mime_type: string | null;
};
type Execution = {
    actual_amount_cents: number | null;
    supplier: string | null;
    supplier_id: number | null;
    supplier_contact_name: string | null;
    supplier_contact_phone: string | null;
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
        application_form: string;
        organization_form: string | null;
        purchase_method: string | null;
    };
    execution: Execution | null;
    attachments: Attachment[];
    canEdit: boolean;
    registeredAt: string | null;
    suppliers: { id: number; name: string; contact_name: string; contact_phone: string }[];
}>();

const selfPurchase = computed(() => props.application.application_form === 'self_purchase');
const headline = computed(() => selfPurchase.value ? '采购结果登记' : '采购执行登记');
const form = useForm({
    _method: 'put',
    intent: 'draft',
    actual_amount:
        props.execution?.actual_amount_cents == null
            ? ''
            : String(props.execution.actual_amount_cents / 100),
    supplier: props.execution?.supplier ?? '',
    supplier_id: (props.execution?.supplier_id ?? null) as number | null,
    completed_on: props.execution?.completed_on?.slice(0, 10) ?? '',
    result_note: props.execution?.result_note ?? '',
    contract_document: null as File | null,
    result_announcement_document: null as File | null,
    acceptance_document: null as File | null,
    invoice_document: null as File | null,
    other_execution_documents: [] as File[],
    purchase_process_documents: [] as File[],
});
const selectedSupplier = computed(() => {
    const execution = props.execution;
    if (execution && form.supplier_id === execution.supplier_id && (!props.canEdit || execution.status === 'completed' || execution.supplier_id === null)) {
        return { contact_name: execution.supplier_contact_name, contact_phone: execution.supplier_contact_phone };
    }
    return props.suppliers.find((supplier) => supplier.id === form.supplier_id);
});
const unavailableSavedSupplier = computed(() => {
    const id = props.execution?.supplier_id;
    return id != null && !props.suppliers.some((supplier) => supplier.id === id);
});
const processUploadError = computed(() => Object.entries(form.errors)
    .find(([field]) => field === 'purchase_process_documents' || field.startsWith('purchase_process_documents.'))?.[1]);

const kinds: Record<string, string> = {
    contract: '合同',
    result_announcement: '结果公告',
    acceptance: '验收材料',
    invoice: '发票',
    other_execution: '其他材料',
    purchase_process: '采购过程文件',
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

function chooseDocuments(event: Event, field: 'other_execution_documents' | 'purchase_process_documents') {
    form[field] = Array.from(
        (event.target as HTMLInputElement).files ?? [],
    );
}

function refreshSuppliers() {
    router.reload({ only: ['suppliers'] });
}

function save(intent: 'draft' | 'complete') {
    if (!props.canEdit || form.processing) return;
    form.intent = intent;
    form.post(`/procurement/applications/${props.application.id}/execution`, {
        forceFormData: true,
    });
}
</script>

<template>
    <Head :title="`${headline} · ${application.title}`" />
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
                {{ headline }}
            </h1>
            <p
                class="mt-[var(--brand-space-16)] text-sm text-[var(--brand-body)]"
            >
                {{ application.title }} · 预算 ¥{{
                    (application.budget_cents / 100).toLocaleString('zh-CN')
                }}
            </p>
            <p v-if="selfPurchase" class="mt-2 text-sm text-[var(--brand-muted)]">
                {{ application.organization_form || '部门自行采购' }}<template v-if="application.purchase_method"> · {{ application.purchase_method }}</template>
            </p>
        </header>

        <div
            class="mt-[var(--brand-space-32)] grid gap-[var(--brand-space-32)]"
            :class="selfPurchase ? 'xl:grid-cols-[minmax(0,1fr)_280px]' : 'lg:grid-cols-[minmax(0,1fr)_300px]'"
        >
            <form
                class="space-y-[var(--brand-space-24)]"
                @submit.prevent="save('draft')"
            >
                <section
                    class="rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)] shadow-sm"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="font-sans text-xl font-semibold text-[var(--brand-title)]">
                            {{ selfPurchase ? '采购结果' : '执行结果' }}
                        </h2>
                        <div v-if="selfPurchase && registeredAt" class="flex flex-wrap items-center gap-2">
                            <span class="rounded-lg bg-[var(--brand-soft)] px-2 py-1 text-xs text-[var(--brand-success)]">采购成功</span>
                            <span class="text-xs text-[var(--brand-muted)]">登记时间 {{ registeredAt }}</span>
                        </div>
                    </div>
                    <div
                        class="mt-[var(--brand-space-24)] grid gap-[var(--brand-space-24)] sm:grid-cols-2"
                    >
                        <label class="block text-sm text-[var(--brand-body)]"
                            >{{ selfPurchase ? '成交金额（元）' : '实际采购金额（元）' }}
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
                        <div v-if="selfPurchase" class="min-w-0 text-sm text-[var(--brand-body)]">
                            <label for="result-supplier">成交供应商</label>
                            <el-select
                                v-if="canEdit"
                                id="result-supplier"
                                v-model="form.supplier_id"
                                class="mt-2 w-full"
                                :teleported="false"
                                filterable
                                placeholder="搜索并选择供应商"
                            >
                                <el-option v-for="supplier in suppliers" :key="supplier.id" :label="supplier.name" :value="supplier.id" />
                                <el-option v-if="unavailableSavedSupplier && execution?.supplier_id != null" :label="`${execution.supplier}（已停用）`" :value="execution.supplier_id" disabled />
                            </el-select>
                            <p v-else class="mt-2 break-words">{{ execution?.supplier ?? '—' }}</p>
                            <p v-if="canEdit && !execution?.supplier_id && execution?.supplier" class="mt-2 text-xs text-[var(--brand-muted)]">原登记供应商：{{ execution.supplier }}</p>
                            <p v-if="form.errors.supplier_id" class="mt-2 text-xs text-[var(--brand-danger)]">{{ form.errors.supplier_id }}</p>
                            <div v-if="canEdit" class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-[var(--brand-primary)]">
                                <Link href="/procurement/suppliers" target="_blank" rel="noopener" class="hover:underline">打开供应商库（新窗口）</Link>
                                <button type="button" class="hover:underline" @click="refreshSuppliers">刷新列表</button>
                            </div>
                        </div>
                        <label v-else class="block text-sm text-[var(--brand-body)]"
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
                        <dl v-if="selfPurchase" class="grid min-w-0 gap-4 rounded-[var(--brand-radius-md)] bg-[var(--brand-soft)] p-4 sm:col-span-2 sm:grid-cols-2">
                            <div>
                                <dt class="text-xs text-[var(--brand-muted)]">供应商联系人</dt>
                                <dd class="mt-1 break-words text-sm text-[var(--brand-title)]">{{ selectedSupplier?.contact_name || '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-[var(--brand-muted)]">供应商联系电话</dt>
                                <dd class="mt-1 break-words text-sm text-[var(--brand-title)]">{{ selectedSupplier?.contact_phone || '—' }}</dd>
                            </div>
                        </dl>
                        <label class="block text-sm text-[var(--brand-body)]"
                            >{{ selfPurchase ? '成交日期' : '完成日期' }}
                            <el-date-picker
                                v-model="form.completed_on"
                                class="mt-[var(--brand-space-8)] !w-full"
                                type="date"
                                :teleported="!selfPurchase"
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
                    class="rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)] shadow-sm"
                >
                    <h2 class="font-sans text-xl text-[var(--brand-title)]">
                        {{ selfPurchase ? '采购材料' : '执行材料' }}
                    </h2>
                    <p
                        class="mt-[var(--brand-space-8)] text-xs text-[var(--brand-muted)]"
                    >
                        <label v-if="selfPurchase" class="block text-sm text-[var(--brand-body)] sm:col-span-2">
                            采购过程文件
                            <input type="file" multiple class="mt-2 block w-full text-xs"
                                accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg"
                                @change="chooseDocuments($event, 'purchase_process_documents')" />
                            <span v-if="processUploadError" class="mt-1 block text-xs text-[var(--brand-danger)]">{{ processUploadError }}</span>
                        </label>
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
                                @change="chooseDocuments($event, 'other_execution_documents')"
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
                            <span class="flex flex-wrap items-center gap-[var(--brand-space-16)]">
                                <span class="break-all text-[var(--brand-body)]">{{ file.original_name }}</span>
                                <a v-if="canPreviewAttachment(file.mime_type)" :href="`/procurement/attachments/${file.id}/preview`" target="_blank" rel="noopener noreferrer" class="text-[var(--brand-primary)] hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--brand-primary)]">预览</a>
                                <a :href="`/procurement/attachments/${file.id}/download`" class="text-[var(--brand-primary)] hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--brand-primary)]">下载</a>
                            </span>
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
                    {{ selfPurchase ? '完成登记时须填写成交金额、成交供应商、成交日期和结果说明。归档前请按项目要求上传采购过程文件等材料。' : '执行中可分次保存。完成登记时须填写实际金额、供应商、完成日期和结果说明。' }}
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
