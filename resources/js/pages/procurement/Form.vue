<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

type Application = {
    id: number;
    title: string | null;
    budget_cents: number | null;
    funding_source: string | null;
    organization_form: string | null;
    purchase_method: string | null;
    reason: string | null;
    responsible_department: string | null;
    is_fixed_asset: boolean | null;
};

const props = defineProps<{
    application: Application | null;
    departmentAssigned: boolean;
    threshold: number;
}>();
const form = useForm({
    title: props.application?.title ?? '',
    budget:
        props.application?.budget_cents === null ||
        props.application?.budget_cents === undefined
            ? ''
            : String(props.application.budget_cents / 100),
    funding_source: props.application?.funding_source ?? '',
    organization_form: props.application?.organization_form ?? '',
    purchase_method: props.application?.purchase_method ?? '',
    reason: props.application?.reason ?? '',
    responsible_department: props.application?.responsible_department ?? '',
    is_fixed_asset: props.application?.is_fixed_asset ?? false,
    intent: 'draft',
});

const classification = computed(() => {
    const amount = Number(form.budget);
    if (!form.budget || !Number.isFinite(amount))
        return '填写预算后显示预计分类';
    return amount < props.threshold
        ? '预计归类：部门自行采购'
        : '预计归类：统一招标';
});
const needsTenderForm = computed(
    () => form.budget !== '' && Number(form.budget) >= props.threshold,
);

function save(intent: 'draft' | 'submit') {
    form.intent = intent;
    const url = props.application
        ? `/procurement/applications/${props.application.id}`
        : '/procurement/applications';
    if (props.application) form.put(url);
    else form.post(url);
}
</script>

<template>
    <Head
        :title="application ? '编辑部门自行采购申请' : '发起部门自行采购申请'"
    />
    <main
        class="mx-auto w-full max-w-5xl px-[var(--brand-space-24)] py-[var(--brand-space-32)] md:px-[var(--brand-space-32)] md:py-[var(--brand-space-32)]"
    >
        <Link
            href="/procurement/applications"
            class="text-sm text-[var(--brand-primary)] hover:underline"
            >← 返回申请列表</Link
        >
        <div
            class="mt-[var(--brand-space-32)] border-b border-[var(--brand-border)] pb-[var(--brand-space-32)]"
        >
            <p
                class="mb-[var(--brand-space-8)] text-xs font-semibold tracking-[0.28em] text-[var(--brand-primary)]"
            >
                APPLICATION / 申请填报
            </p>
            <h1
                class="font-sans text-3xl font-semibold text-[var(--brand-title)] md:text-4xl"
            >
                {{
                    application
                        ? '完善部门自行采购申请'
                        : '发起部门自行采购申请'
                }}
            </h1>
            <p
                class="mt-[var(--brand-space-16)] text-sm text-[var(--brand-muted)]"
            >
                草稿可以稍后完善；提交时需填写全部申请信息。
            </p>
        </div>

        <div
            v-if="needsTenderForm"
            class="mt-[var(--brand-space-32)] rounded-[var(--brand-radius-md)] border border-[var(--brand-warning)] bg-[var(--brand-soft)] px-[var(--brand-space-24)] py-[var(--brand-space-16)] text-sm text-[var(--brand-warning)]"
        >
            当前预算达到统一招标分界金额，请使用
            <Link
                href="/procurement/tenders/create"
                class="font-semibold underline"
                >统一招标申请表</Link
            >。
        </div>

        <div
            v-if="!departmentAssigned"
            class="mt-[var(--brand-space-32)] rounded-[var(--brand-radius-md)] border border-[var(--brand-warning)] bg-[var(--brand-soft)] px-[var(--brand-space-24)] py-[var(--brand-space-16)] text-sm text-[var(--brand-warning)]"
        >
            当前账号尚未关联部门，请联系管理员完成组织信息同步后再提交申请。
        </div>

        <form
            class="mt-[var(--brand-space-32)] grid gap-[var(--brand-space-32)] lg:grid-cols-[minmax(0,1fr)_240px]"
            @submit.prevent="save('submit')"
        >
            <div class="space-y-[var(--brand-space-32)]">
                <section
                    class="rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)] shadow-sm md:p-[var(--brand-space-32)]"
                >
                    <h2
                        class="font-sans text-xl font-semibold text-[var(--brand-title)]"
                    >
                        项目基本信息
                    </h2>
                    <div
                        class="mt-[var(--brand-space-24)] grid gap-[var(--brand-space-24)] sm:grid-cols-2"
                    >
                        <label class="sm:col-span-2"
                            ><span class="field-label">项目名称</span
                            ><input
                                v-model="form.title"
                                class="field-input"
                                placeholder="例如：教学设备采购"
                            /><span
                                v-if="form.errors.title"
                                class="field-error"
                                >{{ form.errors.title }}</span
                            ></label
                        >
                        <label
                            ><span class="field-label">项目预算（元）</span
                            ><input
                                v-model="form.budget"
                                class="field-input"
                                type="number"
                                min="0.01"
                                step="0.01"
                                placeholder="0.00"
                            /><span
                                v-if="form.errors.budget"
                                class="field-error"
                                >{{ form.errors.budget }}</span
                            ></label
                        >
                        <label
                            ><span class="field-label">经费列支</span
                            ><input
                                v-model="form.funding_source"
                                class="field-input"
                                placeholder="填写经费列支"
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
                                placeholder="填写组织形式"
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
                                placeholder="填写采购方式"
                            /><span
                                v-if="form.errors.purchase_method"
                                class="field-error"
                                >{{ form.errors.purchase_method }}</span
                            ></label
                        >
                        <label class="sm:col-span-2"
                            ><span class="field-label">归口部门</span
                            ><input
                                v-model="form.responsible_department"
                                class="field-input"
                                placeholder="填写归口部门"
                            /><span
                                v-if="form.errors.responsible_department"
                                class="field-error"
                                >{{ form.errors.responsible_department }}</span
                            ></label
                        >
                        <label class="sm:col-span-2"
                            ><span class="field-label">采购理由</span
                            ><textarea
                                v-model="form.reason"
                                class="field-input min-h-32 resize-y"
                                placeholder="说明采购背景、用途和必要性"
                            /><span
                                v-if="form.errors.reason"
                                class="field-error"
                                >{{ form.errors.reason }}</span
                            ></label
                        >
                        <label
                            class="flex items-center gap-[var(--brand-space-16)] sm:col-span-2"
                            ><input
                                v-model="form.is_fixed_asset"
                                type="checkbox"
                                class="size-4 accent-[var(--brand-primary)]"
                            /><span class="text-sm text-[var(--brand-body)]"
                                >属于家具设备类固定资产</span
                            ></label
                        >
                    </div>
                </section>
                <div class="flex flex-wrap gap-[var(--brand-space-16)]">
                    <button
                        type="button"
                        :disabled="form.processing || !departmentAssigned"
                        class="rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] px-[var(--brand-space-24)] py-[var(--brand-space-16)] text-sm font-medium text-[var(--brand-body)] hover:bg-[var(--brand-soft)] disabled:opacity-50"
                        @click="save('draft')"
                    >
                        保存草稿</button
                    ><button
                        type="submit"
                        :disabled="
                            form.processing ||
                            !departmentAssigned ||
                            needsTenderForm
                        "
                        class="rounded-[var(--brand-radius-md)] bg-[var(--brand-primary)] px-[var(--brand-space-24)] py-[var(--brand-space-16)] text-sm font-medium text-[var(--brand-on-primary)] hover:bg-[var(--brand-primary-hover)] disabled:opacity-50"
                    >
                        提交申请
                    </button>
                </div>
            </div>
            <aside
                class="h-fit rounded-[var(--brand-radius-md)] border border-[var(--brand-primary)] bg-[var(--brand-primary-light)]/70 p-[var(--brand-space-24)] lg:sticky lg:top-8"
            >
                <p
                    class="text-xs font-semibold tracking-widest text-[var(--brand-primary)]"
                >
                    分类预览
                </p>
                <p
                    class="mt-[var(--brand-space-16)] font-sans text-lg text-[var(--brand-title)]"
                >
                    {{ classification }}
                </p>
                <p
                    class="mt-[var(--brand-space-16)] text-xs leading-6 text-[var(--brand-body)]"
                >
                    当前分类界限为
                    {{ threshold.toLocaleString('zh-CN') }}
                    元，达到界限归入统一招标。最终分类以提交时规则为准。
                </p>
            </aside>
        </form>
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
