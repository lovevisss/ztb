<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

type Application = {
    id: number;
    source_application_id: number | null;
    title: string | null;
    project_leader: string | null;
    budget_cents: number | null;
    funding_source: string | null;
    organization_form: string | null;
    purchase_method: string | null;
    responsible_department: string | null;
    procurement_requirements: string | null;
    purchase_list: string | null;
    technical_parameters: string | null;
    service_requirements: string | null;
    acceptance_criteria: string | null;
    budget_basis_note: string | null;
};
type Attachment = {
    id: number;
    kind: string;
    original_name: string;
    size: number;
};

const props = defineProps<{
    application: Application | null;
    attachments: Attachment[];
    departmentAssigned: boolean;
    threshold: number;
}>();

const form = useForm({
    title: props.application?.title ?? '',
    project_leader: props.application?.project_leader ?? '',
    budget:
        props.application?.budget_cents == null
            ? ''
            : String(props.application.budget_cents / 100),
    funding_source: props.application?.funding_source ?? '',
    organization_form: props.application?.organization_form ?? '',
    purchase_method: props.application?.purchase_method ?? '',
    responsible_department: props.application?.responsible_department ?? '',
    procurement_requirements: props.application?.procurement_requirements ?? '',
    purchase_list: props.application?.purchase_list ?? '',
    technical_parameters: props.application?.technical_parameters ?? '',
    service_requirements: props.application?.service_requirements ?? '',
    acceptance_criteria: props.application?.acceptance_criteria ?? '',
    budget_basis_note: props.application?.budget_basis_note ?? '',
    demand_document: null as File | null,
    budget_basis_document: null as File | null,
    other_documents: [] as File[],
    intent: 'draft',
});

const budgetBelowThreshold = computed(
    () => form.budget !== '' && Number(form.budget) < props.threshold,
);

function chooseFile(
    event: Event,
    key: 'demand_document' | 'budget_basis_document',
) {
    form[key] = (event.target as HTMLInputElement).files?.[0] ?? null;
}

function chooseOtherFiles(event: Event) {
    form.other_documents = Array.from(
        (event.target as HTMLInputElement).files ?? [],
    );
}

function save(intent: 'draft' | 'submit') {
    form.intent = intent;
    if (props.application) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(
            `/procurement/tenders/${props.application.id}`,
            { forceFormData: true },
        );
    } else {
        form.post('/procurement/tenders', { forceFormData: true });
    }
}
</script>

<template>
    <Head :title="application ? '编辑统一招标申请' : '发起统一招标申请'" />
    <main class="mx-auto w-full max-w-5xl px-5 py-8 md:px-10 md:py-12">
        <Link
            href="/procurement/applications"
            class="text-sm text-emerald-800 hover:underline"
            >← 返回申请列表</Link
        >
        <header class="mt-7 border-b border-slate-200 pb-7">
            <p
                class="mb-2 text-xs font-semibold tracking-[0.28em] text-emerald-700"
            >
                CENTRAL TENDER / 统一招标
            </p>
            <h1
                class="font-serif text-3xl font-semibold text-slate-900 md:text-4xl"
            >
                {{ application ? '完善统一招标申请' : '发起统一招标申请' }}
            </h1>
            <p class="mt-3 text-sm text-slate-500">
                填写采购需求与技术要求，上传需求书和预算依据后提交。
            </p>
        </header>
        <div
            v-if="application?.source_application_id"
            class="mt-7 rounded-md border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-900"
        >
            此申请复制自历史项目。采购需求书、预算依据和其他附件需要重新上传，提交时重新走完整审批流程。
        </div>

        <div
            v-if="!departmentAssigned"
            class="mt-7 rounded-md border border-amber-300 bg-amber-50 px-5 py-4 text-sm text-amber-900"
        >
            当前账号尚未关联部门，请联系管理员完成组织信息同步。
        </div>
        <div
            v-if="budgetBelowThreshold"
            class="mt-7 rounded-md border border-amber-300 bg-amber-50 px-5 py-4 text-sm text-amber-900"
        >
            当前预算低于 {{ threshold.toLocaleString('zh-CN') }} 元，请改用
            <Link
                href="/procurement/applications/create"
                class="font-semibold underline"
                >部门自行采购申请</Link
            >。
        </div>

        <form class="mt-8 space-y-7" @submit.prevent="save('submit')">
            <section class="form-section">
                <h2 class="section-title">01 · 项目与经费</h2>
                <div class="mt-6 grid gap-5 sm:grid-cols-2">
                    <label class="sm:col-span-2"
                        ><span class="field-label">项目名称</span
                        ><input
                            v-model="form.title"
                            class="field-input"
                            placeholder="填写正式项目名称"
                        /><span v-if="form.errors.title" class="field-error">{{
                            form.errors.title
                        }}</span></label
                    >
                    <label
                        ><span class="field-label">项目负责人</span
                        ><input
                            v-model="form.project_leader"
                            class="field-input"
                            placeholder="姓名"
                        /><span
                            v-if="form.errors.project_leader"
                            class="field-error"
                            >{{ form.errors.project_leader }}</span
                        ></label
                    >
                    <label
                        ><span class="field-label">项目预算（元）</span
                        ><input
                            v-model="form.budget"
                            type="number"
                            min="0.01"
                            step="0.01"
                            class="field-input"
                        /><span v-if="form.errors.budget" class="field-error">{{
                            form.errors.budget
                        }}</span></label
                    >
                    <label
                        ><span class="field-label">经费列支</span
                        ><input
                            v-model="form.funding_source"
                            class="field-input"
                        /><span
                            v-if="form.errors.funding_source"
                            class="field-error"
                            >{{ form.errors.funding_source }}</span
                        ></label
                    >
                    <label
                        ><span class="field-label">组织形式</span
                        ><input
                            v-model="form.organization_form"
                            class="field-input"
                        /><span
                            v-if="form.errors.organization_form"
                            class="field-error"
                            >{{ form.errors.organization_form }}</span
                        ></label
                    >
                    <label
                        ><span class="field-label">采购方式</span
                        ><input
                            v-model="form.purchase_method"
                            class="field-input"
                        /><span
                            v-if="form.errors.purchase_method"
                            class="field-error"
                            >{{ form.errors.purchase_method }}</span
                        ></label
                    >
                    <label
                        ><span class="field-label">归口部门</span
                        ><input
                            v-model="form.responsible_department"
                            class="field-input"
                        /><span
                            v-if="form.errors.responsible_department"
                            class="field-error"
                            >{{ form.errors.responsible_department }}</span
                        ></label
                    >
                </div>
            </section>

            <section class="form-section">
                <h2 class="section-title">02 · 采购需求与技术要求</h2>
                <div class="mt-6 grid gap-5">
                    <label
                        ><span class="field-label">采购需求</span
                        ><textarea
                            v-model="form.procurement_requirements"
                            class="field-input min-h-28"
                            placeholder="说明采购目标与使用场景"
                        /><span
                            v-if="form.errors.procurement_requirements"
                            class="field-error"
                            >{{ form.errors.procurement_requirements }}</span
                        ></label
                    >
                    <label
                        ><span class="field-label">采购清单</span
                        ><textarea
                            v-model="form.purchase_list"
                            class="field-input min-h-28"
                            placeholder="列出货物或服务名称、数量"
                        /><span
                            v-if="form.errors.purchase_list"
                            class="field-error"
                            >{{ form.errors.purchase_list }}</span
                        ></label
                    >
                    <label
                        ><span class="field-label">技术参数</span
                        ><textarea
                            v-model="form.technical_parameters"
                            class="field-input min-h-28"
                        /><span
                            v-if="form.errors.technical_parameters"
                            class="field-error"
                            >{{ form.errors.technical_parameters }}</span
                        ></label
                    >
                    <div class="grid gap-5 sm:grid-cols-2">
                        <label
                            ><span class="field-label">服务要求</span
                            ><textarea
                                v-model="form.service_requirements"
                                class="field-input min-h-28"
                            /><span
                                v-if="form.errors.service_requirements"
                                class="field-error"
                                >{{ form.errors.service_requirements }}</span
                            ></label
                        >
                        <label
                            ><span class="field-label">验收标准</span
                            ><textarea
                                v-model="form.acceptance_criteria"
                                class="field-input min-h-28"
                            /><span
                                v-if="form.errors.acceptance_criteria"
                                class="field-error"
                                >{{ form.errors.acceptance_criteria }}</span
                            ></label
                        >
                    </div>
                    <label
                        ><span class="field-label">预算测算说明</span
                        ><textarea
                            v-model="form.budget_basis_note"
                            class="field-input min-h-24"
                        /><span
                            v-if="form.errors.budget_basis_note"
                            class="field-error"
                            >{{ form.errors.budget_basis_note }}</span
                        ></label
                    >
                </div>
            </section>

            <section class="form-section">
                <h2 class="section-title">03 · 申请材料</h2>
                <p class="mt-2 text-sm text-slate-500">
                    支持 PDF、Word、Excel 和图片，单个文件不超过 20 MB。
                </p>
                <div class="mt-6 grid gap-5 sm:grid-cols-2">
                    <label
                        ><span class="field-label"
                            >采购需求书
                            <span class="text-rose-700">*</span></span
                        ><input
                            type="file"
                            accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg"
                            class="field-file"
                            @change="chooseFile($event, 'demand_document')"
                        /><span
                            v-if="form.errors.demand_document"
                            class="field-error"
                            >{{ form.errors.demand_document }}</span
                        ></label
                    >
                    <label
                        ><span class="field-label"
                            >预算依据附件
                            <span class="text-rose-700">*</span></span
                        ><input
                            type="file"
                            accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg"
                            class="field-file"
                            @change="
                                chooseFile($event, 'budget_basis_document')
                            "
                        /><span
                            v-if="form.errors.budget_basis_document"
                            class="field-error"
                            >{{ form.errors.budget_basis_document }}</span
                        ></label
                    >
                    <label class="sm:col-span-2"
                        ><span class="field-label">其他相关附件</span
                        ><input
                            type="file"
                            multiple
                            accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg"
                            class="field-file"
                            @change="chooseOtherFiles"
                        /><span
                            v-if="form.errors.other_documents"
                            class="field-error"
                            >{{ form.errors.other_documents }}</span
                        ></label
                    >
                </div>
                <div
                    v-if="attachments.length"
                    class="mt-6 border-t border-slate-100 pt-5"
                >
                    <p
                        class="text-xs font-semibold tracking-widest text-slate-500"
                    >
                        已上传材料
                    </p>
                    <ul class="mt-3 space-y-2 text-sm">
                        <li
                            v-for="attachment in attachments"
                            :key="attachment.id"
                        >
                            <a
                                :href="`/procurement/attachments/${attachment.id}/download`"
                                class="font-medium text-emerald-800 hover:underline"
                                >{{ attachment.original_name }} ↗</a
                            >
                            <span class="text-slate-400"
                                >({{
                                    Math.ceil(attachment.size / 1024)
                                }}
                                KB)</span
                            >
                        </li>
                    </ul>
                </div>
            </section>

            <div class="flex flex-wrap items-center gap-3">
                <button
                    type="button"
                    :disabled="form.processing || !departmentAssigned"
                    class="rounded-md border border-slate-300 px-5 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                    @click="save('draft')"
                >
                    保存草稿</button
                ><button
                    type="submit"
                    :disabled="
                        form.processing ||
                        !departmentAssigned ||
                        budgetBelowThreshold
                    "
                    class="rounded-md bg-emerald-800 px-6 py-3 text-sm font-medium text-white hover:bg-emerald-900 disabled:opacity-50"
                >
                    提交统一招标申请
                </button>
                <p class="text-xs text-slate-500">
                    当前统一招标分界金额：{{
                        threshold.toLocaleString('zh-CN')
                    }}
                    元
                </p>
            </div>
        </form>
    </main>
</template>

<style scoped>
.form-section {
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    background: white;
    padding: 1.5rem;
    box-shadow: 0 1px 2px #0f172a08;
}
.section-title {
    font-family: Georgia, 'Noto Serif SC', serif;
    font-size: 1.25rem;
    font-weight: 600;
    color: #0f172a;
}
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
.field-file {
    display: block;
    width: 100%;
    border: 1px dashed #94a3b8;
    border-radius: 0.375rem;
    background: #f8fafc;
    padding: 0.7rem;
    font-size: 0.75rem;
    color: #475569;
}
.field-error {
    display: block;
    margin-top: 0.35rem;
    font-size: 0.75rem;
    color: #b91c1c;
}
@media (min-width: 768px) {
    .form-section {
        padding: 2rem;
    }
}
</style>
