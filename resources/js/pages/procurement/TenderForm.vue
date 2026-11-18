<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { parseItemCsv, populateItemTemplate, validateItemRows } from '@/lib/procurement-item-import';
import { canPreviewAttachment } from '@/lib/procurement-attachment';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';

type Item = { name: string; specification: string; quantity: number; unit_price: number; unit: string; remarks: string };
type SavedItem = { name: string; specification: string | null; quantity: number; unit_price_cents: number; unit: string | null; remarks: string | null };
type Indicator = { id: number; code: string | null; name: string; director: string | null; department_code: string | null; department_name: string | null; status: string | null; available_cents: number | null };
type CatalogItem = { id: number; name: string; specification: string | null; unit: string | null; unit_price_cents: number; remarks: string | null };
type Application = { id: number; status: string; project_number: string | null; title: string | null; category: string | null; contact_phone: string | null; purchase_method: string | null; is_special_purchase: boolean; special_purchase_reason: string | null; is_non_expenditure: boolean; has_usage_department: boolean; usage_department_id: number | null; procurement_requirements: string | null; remarks: string | null; budget_basis_note: string | null; budget_cents: number | null; responsible_department_id: number | null; reason_category: string | null; reason: string | null; has_furniture: boolean; has_equipment_asset: boolean };
type Attachment = { id: number; original_name: string; size: number; mime_type: string | null };
type Allocation = { budget_indicator_id: number; amount_cents: number; code: string | null; name: string; director: string | null };
type Engineering = { project_name: string | null; construction_content: string | null; project_overview: string | null; schedule_requirements: string | null; estimated_cost_cents: number | null };

const props = defineProps<{
    application: Application | null;
    selfPurchase: boolean;
    responsibleDepartments: { id: number | null; label: string }[];
    applicantDepartmentId: number | null;
    attachments: Attachment[];
    departmentAssigned: boolean;
    threshold: number;
    applicantName: string;
    applicantPhone: string | null;
    departmentName: string | null;
    departments: { id: number; name: string }[];
    items: SavedItem[];
    engineering: Engineering | null;
    engineeringBill: Attachment | null;
    allocations: Allocation[];
    catalog: CatalogItem[];
    canSyncBudgets: boolean;
}>();
const page = usePage();
const flashStatus = ref((page.props.flash as { status?: string } | undefined)?.status ?? '');

type Tab = 'basic' | 'items' | 'budget' | 'attachments';
const initialTab = typeof window === 'undefined' ? 'basic' : (window.location.hash.slice(1) || new URLSearchParams(window.location.search).get('tab'));
const tab = ref<Tab>(['basic', 'items', 'budget', 'attachments'].includes(initialTab ?? '') ? initialTab as Tab : 'basic');
const tabs: { key: Tab; label: string }[] = [
    { key: 'basic', label: '基本信息' }, { key: 'items', label: '采购清单' },
    { key: 'budget', label: '项目预算指标' }, { key: 'attachments', label: '附件' },
];
function scrollToSection(section: Tab, behavior: ScrollBehavior = 'smooth') {
    tab.value = section;
    document.getElementById(section)?.scrollIntoView({ behavior, block: 'start' });
    window.history.replaceState(window.history.state, '', `#${section}`);
}
function updateActiveSection() {
    if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 8) {
        tab.value = 'attachments';
        return;
    }
    for (const entry of [...tabs].reverse()) {
        const section = document.getElementById(entry.key);
        if (section && section.getBoundingClientRect().top <= 160) {
            tab.value = entry.key;
            return;
        }
    }
    tab.value = 'basic';
}
onMounted(async () => {
    await nextTick();
    if (initialTab && initialTab !== 'basic' && tabs.some((entry) => entry.key === initialTab)) {
        scrollToSection(initialTab as Tab, 'auto');
    } else updateActiveSection();
    window.addEventListener('scroll', updateActiveSection, { passive: true });
});
onBeforeUnmount(() => window.removeEventListener('scroll', updateActiveSection));
const basic = useForm({
    section: 'basic', title: props.application?.title ?? '', category: props.application?.category ?? '',
    contact_phone: props.application ? (props.application.contact_phone ?? '') : (props.applicantPhone ?? ''), purchase_method: props.application?.purchase_method ?? (props.selfPurchase ? '' : '公开招标'),
    is_special_purchase: props.application?.is_special_purchase ?? false,
    special_purchase_reason: props.application?.special_purchase_reason ?? '',
    is_non_expenditure: props.application?.is_non_expenditure ?? false,
    has_usage_department: props.application?.has_usage_department ?? false,
    usage_department_id: props.application?.usage_department_id ?? null as number | null,
    procurement_requirements: props.application?.procurement_requirements ?? '', remarks: props.application?.remarks ?? '',
    responsible_department_id: props.application?.responsible_department_id ?? props.applicantDepartmentId,
    reason_category: props.application?.reason_category ?? '', reason: props.application?.reason ?? '',
    has_furniture: props.application?.has_furniture ?? false,
    has_equipment_asset: props.application?.has_equipment_asset ?? false,
});
const hasResponsibleDepartment = ref(Boolean(props.application?.responsible_department_id && props.application.responsible_department_id !== props.applicantDepartmentId));
watch(hasResponsibleDepartment, (has) => { basic.responsible_department_id = has ? null : props.applicantDepartmentId; });
watch(() => basic.is_special_purchase, (special) => {
    if (props.selfPurchase) return;
    basic.purchase_method = special ? '' : '公开招标';
    if (!special) basic.special_purchase_reason = '';
});
const itemForm = useForm<{ section: string; items: Item[] }>({
    section: 'items', items: props.items.map((item) => ({
        name: item.name, specification: item.specification ?? '', quantity: Number(item.quantity),
        unit_price: item.unit_price_cents / 100, unit: item.unit ?? '', remarks: item.remarks ?? '',
    })),
});
const engineeringForm = useForm({
    section: 'engineering', category: '工程类',
    project_name: props.engineering?.project_name ?? '',
    construction_content: props.engineering?.construction_content ?? '',
    project_overview: props.engineering?.project_overview ?? '',
    schedule_requirements: props.engineering?.schedule_requirements ?? '',
    estimated_cost: props.engineering?.estimated_cost_cents == null ? '' : String(props.engineering.estimated_cost_cents / 100),
    bill_file: null as File | null,
});
const budgetForm = useForm({
    section: 'budget', budget_basis_note: props.application?.budget_basis_note ?? '',
    allocations: props.allocations.map((a) => ({ budget_indicator_id: a.budget_indicator_id, amount: a.amount_cents / 100, code: a.code, name: a.name, director: a.director })),
});
const pickerIndex = ref<number | null>(null);
const pickerQuery = ref('');
const pickerDepartment = ref('');
const pickerStatus = ref('');
const pickerRows = ref<Indicator[]>([]);
const pickerDepartments = ref<{ code: string; name: string | null }[]>([]);
const pickerStatuses = ref<(string | null)[]>([]);
const pickerPage = ref(1);
const pickerLastPage = ref(1);
const pickerTotal = ref(0);
const pickerSourceTotal = ref(0);
const pickerLoading = ref(false);
const pickerError = ref('');
async function searchIndicators(pageNumber = 1) {
    pickerLoading.value = true;
    pickerError.value = '';
    const params = new URLSearchParams({ page: String(pageNumber) });
    if (pickerQuery.value.trim()) params.set('q', pickerQuery.value.trim());
    if (pickerDepartment.value) params.set('department', pickerDepartment.value);
    if (pickerStatus.value) params.set('status', pickerStatus.value);
    try {
        const response = await fetch(`/procurement/budgets?${params}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('经费来源查询失败，请稍后重试。');
        const result = await response.json() as { data: Indicator[]; current_page: number; last_page: number; total: number; source_total: number; departments: { code: string; name: string | null }[]; statuses: (string | null)[] };
        pickerRows.value = result.data;
        pickerDepartments.value = result.departments;
        pickerStatuses.value = result.statuses;
        pickerPage.value = result.current_page;
        pickerLastPage.value = result.last_page;
        pickerTotal.value = result.total;
        pickerSourceTotal.value = result.source_total;
    } catch (error) { pickerError.value = error instanceof Error ? error.message : '经费来源查询失败'; }
    finally { pickerLoading.value = false; }
}
function openIndicatorPicker(index: number) {
    pickerIndex.value = index;
    void searchIndicators();
}
function syncBudgetIndicators() {
    router.post('/procurement/budgets/sync', {}, {
        preserveScroll: true,
        onSuccess: () => {
            if (pickerIndex.value !== null) void searchIndicators();
        },
    });
}
function selectIndicator(indicator: Indicator) {
    if (indicator.status !== '1' || pickerIndex.value === null) return;
    const allocation = budgetForm.allocations[pickerIndex.value];
    if (!allocation) return;
    allocation.budget_indicator_id = indicator.id;
    allocation.code = indicator.code;
    allocation.name = indicator.name;
    allocation.director = indicator.director;
    pickerIndex.value = null;
}
const attachmentInput = ref<HTMLInputElement | null>(null);
const attachmentForm = useForm<{ section: string; files: File[]; delete_ids: number[] }>({ section: 'attachments', files: [], delete_ids: [] });
const catalogForm = useForm({ name: '', specification: '', unit: '', unit_price: 0, remarks: '' });
const editingCatalogId = ref<number | null>(null);
const selectedCatalog = ref<number | ''>('');
const total = computed(() => basic.category === '工程类'
    ? Number(engineeringForm.estimated_cost || 0)
    : itemForm.items.reduce((sum, item) => sum + Math.round(Number(item.unit_price || 0) * 100 * Number(item.quantity || 0)), 0) / 100);
const allocated = computed(() => budgetForm.allocations.reduce((sum, a) => sum + Number(a.amount || 0), 0));
const money = (value: number) => value.toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const invalidMoney = (value: string | number) => value !== '' && (!Number.isFinite(Number(value)) || Number(value) < 0 || !/^\d+(?:\.\d{1,2})?$/.test(String(value)));
const invalidQuantity = (value: string | number) => value !== '' && (!Number.isFinite(Number(value)) || Number(value) <= 0 || !/^\d+(?:\.\d{1,3})?$/.test(String(value)));

type SaveSection = Tab | 'engineering';
type SaveFeedback = { state: 'idle' | 'dirty' | 'saving' | 'saved' | 'error'; message: string; time: string | null };
const saveFeedback = ref<Record<SaveSection, SaveFeedback>>({
    basic: { state: 'idle', message: '', time: null },
    items: { state: 'idle', message: '', time: null },
    engineering: { state: 'idle', message: '', time: null },
    budget: { state: 'idle', message: '', time: null },
    attachments: { state: 'idle', message: '', time: null },
});
const savedLabels: Record<SaveSection, string> = {
    basic: '基本信息', items: '采购清单', engineering: '工程清单', budget: '项目预算指标', attachments: '附件',
};
function markDirty(section: SaveSection) {
    flashStatus.value = '';
    saveFeedback.value[section] = { state: 'dirty', message: '有未保存的修改', time: null };
}
watch(() => basic.data(), () => markDirty('basic'), { deep: true });
watch(() => itemForm.items, () => markDirty('items'), { deep: true });
watch(() => engineeringForm.data(), () => markDirty('engineering'), { deep: true });
watch(() => budgetForm.data(), () => markDirty('budget'), { deep: true });
watch(() => attachmentForm.data(), () => markDirty('attachments'), { deep: true });
function feedbackClass(section: SaveSection) {
    return saveFeedback.value[section].state === 'error' ? 'text-[var(--brand-danger)]'
        : saveFeedback.value[section].state === 'saved' ? 'text-[var(--brand-success)]' : 'text-[var(--brand-muted)]';
}

function blankItem(): Item { return { name: '', specification: '', quantity: 1, unit_price: 0, unit: '', remarks: '' }; }
const itemEditorOpen = ref(false);
const editingItemIndex = ref<number | null>(null);
const itemDraft = ref<Item>(blankItem());
const itemDraftErrors = ref<Partial<Record<keyof Item, string>>>({});
function openItemEditor(index: number | null = null) {
    editingItemIndex.value = index;
    itemDraft.value = index === null ? blankItem() : { ...itemForm.items[index] };
    itemDraftErrors.value = {};
    itemEditorOpen.value = true;
}
function itemErrors(index: number): string[] {
    return Object.entries(itemForm.errors)
        .filter(([key]) => key.startsWith(`items.${index}.`)).map(([, value]) => String(value));
}
function confirmItem() {
    const item = itemDraft.value;
    const errors: Partial<Record<keyof Item, string>> = {};
    if (!item.name.trim() || [...item.name].length > 255) errors.name = '请输入货物名称，最多255个字符。';
    if (String(item.quantity) === '' || invalidQuantity(item.quantity)) errors.quantity = '数量必须大于零，最多保留三位小数。';
    if (String(item.unit_price) === '' || invalidMoney(item.unit_price)) errors.unit_price = '单价必须为非负金额，最多保留两位小数。';
    if ([...item.unit].length > 50) errors.unit = '单位最多50个字符。';
    itemDraftErrors.value = errors;
    if (Object.keys(errors).length) return;
    const value = { ...item, name: item.name.trim(), quantity: Number(item.quantity), unit_price: Number(item.unit_price) };
    if (editingItemIndex.value === null) {
        if (itemForm.items.length >= 500) { itemDraftErrors.value = { name: '单次最多添加500项采购清单。' }; return; }
        itemForm.items.push(value);
    } else {
        const index = editingItemIndex.value;
        itemForm.items[index] = value;
        const keys = Object.keys(itemForm.errors).filter((key) => key.startsWith(`items.${index}.`));
        itemForm.clearErrors(...keys as (keyof typeof itemForm.errors)[]);
    }
    itemEditorOpen.value = false;
}
function deleteItem(index: number) {
    if (!window.confirm(`确定删除“${itemForm.items[index].name || '未命名项目'}”？保存采购清单后生效。`)) return;
    const remainingErrors = Object.entries(itemForm.errors).flatMap(([key, message]) => {
        const match = /^items\.(\d+)\.(.+)$/.exec(key);
        if (!match) return [[key, message]];
        const row = Number(match[1]);
        return row === index ? [] : [[`items.${row > index ? row - 1 : row}.${match[2]}`, message]];
    });
    itemForm.clearErrors();
    itemForm.setError(Object.fromEntries(remainingErrors));
    itemForm.items.splice(index, 1);
}
function save(section: Tab | 'engineering') {
    if (activeSaving.value || !props.departmentAssigned) return;
    const form = section === 'basic' ? basic : section === 'items' ? itemForm : section === 'engineering' ? engineeringForm : section === 'budget' ? budgetForm : attachmentForm;
    let resolved = false;
    saveFeedback.value[section] = { state: 'saving', message: `${savedLabels[section]}保存中…`, time: null };
    const options = {
        preserveScroll: true,
        onSuccess: async () => {
            resolved = true;
            if (section === 'attachments') { attachmentForm.files = []; attachmentForm.delete_ids = []; if (attachmentInput.value) attachmentInput.value.value = ''; }
            if (section === 'engineering') engineeringForm.bill_file = null;
            await nextTick();
            saveFeedback.value[section] = { state: 'saved', message: `${savedLabels[section]}已保存`, time: new Date().toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit' }) };
        },
        onError: (errors: Record<string, string>) => {
            resolved = true;
            saveFeedback.value[section] = { state: 'error', message: Object.values(errors)[0] ?? '保存失败，请检查输入后重试。', time: null };
        },
        onFinish: () => {
            if (!resolved) saveFeedback.value[section] = { state: 'error', message: '保存未完成，请检查网络后重试。', time: null };
        },
    };
    const base = props.selfPurchase ? '/procurement/applications' : '/procurement/tenders';
    if (props.application) form.post(`${base}/${props.application.id}/sections`, options);
    else form.post(`${base}/sections`, options);
}
function submit() {
    if (!props.application) return;
    router.post(`${props.selfPurchase ? '/procurement/applications' : '/procurement/tenders'}/${props.application.id}/submit`, {}, {
        preserveScroll: true,
        onError: (errors) => { submissionErrors.value = Object.values(errors).map(String); },
    });
}
const submissionErrors = ref<string[]>([]);
const activeSaving = computed(() => basic.processing || itemForm.processing || engineeringForm.processing || budgetForm.processing || attachmentForm.processing);
function saveCurrentSection() {
    save(tab.value === 'items' && basic.category === '工程类' ? 'engineering' : tab.value);
}
function addFromCatalog() {
    const source = props.catalog.find((item) => item.id === Number(selectedCatalog.value));
    if (!source) return;
    itemForm.items.push({ name: source.name, specification: source.specification ?? '', quantity: 1, unit_price: source.unit_price_cents / 100, unit: source.unit ?? '', remarks: source.remarks ?? '' });
    selectedCatalog.value = '';
}
function addCatalog() {
    const options = { preserveScroll: true, onSuccess: () => { catalogForm.reset(); editingCatalogId.value = null; } };
    if (editingCatalogId.value) catalogForm.put(`/procurement/catalog/${editingCatalogId.value}`, options);
    else catalogForm.post('/procurement/catalog', options);
}
function editCatalog(item: CatalogItem) {
    editingCatalogId.value = item.id;
    catalogForm.name = item.name;
    catalogForm.specification = item.specification ?? '';
    catalogForm.unit = item.unit ?? '';
    catalogForm.unit_price = item.unit_price_cents / 100;
    catalogForm.remarks = item.remarks ?? '';
}
function removeCatalog(id: number) {
    router.delete(`/procurement/catalog/${id}`, { preserveScroll: true });
}
function chooseFiles(event: Event) {
    attachmentForm.files.push(...Array.from((event.target as HTMLInputElement).files ?? []));
    (event.target as HTMLInputElement).value = '';
}
function toggleAttachmentDeletion(id: number) {
    attachmentForm.delete_ids = attachmentForm.delete_ids.includes(id)
        ? attachmentForm.delete_ids.filter((fileId) => fileId !== id)
        : [...attachmentForm.delete_ids, id];
}
function chooseEngineeringBill(event: Event) {
    engineeringForm.bill_file = (event.target as HTMLInputElement).files?.[0] ?? null;
}
const importingItems = ref(false);
const downloadingTemplate = ref(false);
const importSuccess = ref('');
const importError = ref('');
async function downloadItemTemplate() {
    if (downloadingTemplate.value) return;
    downloadingTemplate.value = true;
    importError.value = '';
    try {
        const { Workbook } = await import('exceljs');
        const workbook = new Workbook();
        populateItemTemplate(workbook);
        const buffer = await workbook.xlsx.writeBuffer();
        const url = URL.createObjectURL(new Blob([new Uint8Array(buffer)], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }));
        const link = document.createElement('a');
        link.href = url;
        link.download = '采购清单导入模板.xlsx';
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch { importError.value = '模板下载失败，请重试。'; }
    finally { downloadingTemplate.value = false; }
}
async function importItems(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file || importingItems.value) return;
    importingItems.value = true;
    importError.value = '';
    importSuccess.value = '';
    try {
        let rows: string[][];
        if (file.name.toLowerCase().endsWith('.csv')) rows = parseItemCsv(await file.text());
        else if (file.name.toLowerCase().endsWith('.xlsx')) {
            const { Workbook } = await import('exceljs');
            const workbook = new Workbook();
            await workbook.xlsx.load(await file.arrayBuffer());
            const sheet = workbook.worksheets[0];
            if (!sheet) throw new Error('文件中没有工作表。');
            rows = [];
            for (let row = 1; row <= sheet.rowCount; row++) {
                const values: string[] = [];
                sheet.getRow(row).eachCell({ includeEmpty: true }, (cell, col) => { values[col - 1] = cell.text; });
                rows.push(Array.from({ length: Math.max(6, values.length) }, (_, index) => values[index] ?? ''));
            }
        } else throw new Error('请选择 .xlsx 或 CSV 文件。');
        const imported = validateItemRows(rows, itemForm.items.length);
        itemForm.items.push(...imported);
        importSuccess.value = `已导入${imported.length}项，请点击“保存采购清单”提交。`;
    } catch (error) { importError.value = error instanceof Error ? error.message : '导入失败，请检查文件是否损坏。'; }
    finally { importingItems.value = false; input.value = ''; }
}
</script>

<template>
    <Head :title="selfPurchase ? '部门自行采购申请填报' : '统一招标申请填报'" />
    <main class="tender-form-page w-full min-w-0 px-4 py-6 md:px-8 md:py-8">
        <header class="mb-6 border-b border-[var(--brand-border)] pb-5">
            <h1 class="text-[18px] font-semibold text-[var(--brand-title)]">{{ selfPurchase ? '部门自行采购申请单' : '采购申请单' }}</h1>
            <p v-if="application?.project_number" class="mt-2 text-xs text-[var(--brand-muted)]">项目编号：{{ application.project_number }}</p>
        </header>
        <div v-if="!application" class="number-notice mb-6 flex items-start gap-3 rounded-[var(--brand-radius-md)] border p-4 text-sm" role="note"><span aria-hidden="true" class="font-bold">i</span><p><strong>项目编号自动生成</strong><br><span class="text-xs">首次保存任一模块后，系统将自动生成项目编号。</span></p></div>
        <div v-if="['returned', 'withdrawn'].includes(application?.status ?? '')" class="mb-5 rounded-[var(--brand-radius-md)] border border-[var(--brand-warning)] bg-[var(--brand-soft)] p-4 text-sm text-[var(--brand-warning)]">此申请{{ application?.status === 'withdrawn' ? '已撤回' : '已退回' }}。请分别保存修改过的页签，再点击底部“提交审核”，系统将从首环节开启新一轮审批。</div>
        <div v-if="submissionErrors.length" role="alert" class="mb-5 rounded-[var(--brand-radius-md)] border border-[var(--brand-danger)] p-4 text-sm text-[var(--brand-danger)]"><p v-for="(message, index) in submissionErrors" :key="index">{{ message }}</p></div>
        <div v-if="flashStatus" role="status" class="mb-5 rounded-[var(--brand-radius-md)] border border-[var(--brand-success)] bg-[var(--brand-soft)] p-4 text-sm text-[var(--brand-success)]">{{ flashStatus }}</div>
        <div v-if="!departmentAssigned" class="mb-5 rounded border border-[var(--brand-warning)] p-4 text-[var(--brand-warning)]">当前账号尚未关联部门，请联系管理员。</div>
        <nav class="section-nav mb-6 flex gap-2 overflow-x-auto border-b border-[var(--brand-border)] bg-[var(--brand-card)]" aria-label="申请内容定位">
            <a v-for="entry in tabs" :key="entry.key" :href="`#${entry.key}`" :aria-current="tab === entry.key ? 'location' : undefined" class="shrink-0 border-b-2 px-4 py-3 text-sm" :class="tab === entry.key ? 'border-[var(--brand-primary)] font-semibold text-[var(--brand-primary)]' : 'border-transparent text-[var(--brand-muted)] hover:text-[var(--brand-primary)]'" @click.prevent="scrollToSection(entry.key)">{{ entry.label }}</a>
        </nav>
        <section id="basic" class="panel">
            <h2 class="section-title">基本信息 <span class="text-xs font-normal text-[var(--brand-muted)]">标记 * 的字段在提交审核时必填</span></h2>
            <div class="form-groups">
                <div class="form-group"><h3>项目基础信息</h3><div class="form-grid">
                    <label><span class="label">申报项目 <span class="required">*</span></span><input v-model="basic.title" class="field" /><small class="error">{{ basic.errors.title }}</small></label>
                    <div><span class="label">项目编号</span><div class="field read-only-value">{{ application?.project_number ?? '保存后自动生成' }}</div></div>
                    <div><span class="label">项目预算</span><div class="field read-only-value amount-value">{{ money(total) }} <span>元</span></div><small class="field-hint">由采购清单合计或工程总造价自动计算</small></div>
                    <div><div class="label"><label for="purchase-category">归属 <span class="required">*</span></label><span class="help-wrap"><button type="button" class="help-trigger" aria-label="归属说明">?</button><span class="help-text" role="tooltip">选择项目所属的采购类别：工程类、货物类或服务类。</span></span></div><select id="purchase-category" v-model="basic.category" class="field" :class="{ 'select-placeholder': !basic.category }"><option value="">请选择</option><option>工程类</option><option>货物类</option><option>服务类</option></select><small class="error">{{ basic.errors.category }}</small></div>
                </div></div>
                <div class="form-group"><h3>经办人信息</h3><div class="form-grid">
                    <div><span class="label">经办人</span><div class="field read-only-value">{{ applicantName }}</div></div>
                    <label><span class="label">经办人电话 <span class="required">*</span></span><input v-model="basic.contact_phone" class="field" type="tel" /><small class="error">{{ basic.errors.contact_phone }}</small></label>
                    <div class="sm:col-span-2"><span class="label">经办人所在部门</span><div class="field read-only-value">{{ departmentName ?? '未关联部门' }}</div></div>
                </div></div>
                <div class="form-group"><h3>采购属性</h3><div class="form-grid">
                    <label v-if="!selfPurchase"><span class="label">是否特殊采购</span><select v-model="basic.is_special_purchase" class="field"><option :value="false">否</option><option :value="true">是</option></select></label>
                    <label><span class="label">拟采购方式 <span v-if="selfPurchase" class="required">*</span></span><select v-if="selfPurchase" v-model="basic.purchase_method" class="field"><option value="">请选择</option><option>谈判</option><option>询价</option><option>政采云等平台</option><option>单一来源</option></select><input v-else-if="!basic.is_special_purchase" class="field" value="公开招标" disabled /><select v-else v-model="basic.purchase_method" class="field" :class="{ 'select-placeholder': !basic.purchase_method }"><option value="">请选择</option><option>邀请招标</option><option>竞争性谈判</option><option>竞争性磋商</option><option>单一来源</option><option>询价</option><option>谈判</option><option>政采云等平台</option></select><small class="error">{{ basic.errors.purchase_method }}</small></label>
                    <label v-if="!selfPurchase && basic.is_special_purchase" class="sm:col-span-2"><span class="label">申请特殊采购方式理由</span><textarea v-model="basic.special_purchase_reason" class="field min-h-24" /><small class="error">{{ basic.errors.special_purchase_reason }}</small></label>
                    <template v-if="selfPurchase">
                        <label><span class="label">是否有经费归口部门 <span class="required">*</span></span><select v-model="hasResponsibleDepartment" class="field"><option :value="false">否（按经办人所在部门）</option><option :value="true">是</option></select></label>
                        <label v-if="hasResponsibleDepartment"><span class="label">归口管理部门 <span class="required">*</span></span><select v-model="basic.responsible_department_id" class="field"><option :value="null">请选择</option><option v-for="department in responsibleDepartments" :key="department.label" :value="department.id" :disabled="department.id === null">{{ department.label }}{{ department.id === null ? '（组织库中未匹配）' : '' }}</option></select><small class="error">{{ basic.errors.responsible_department_id }}</small></label>
                        <label><span class="label">含家具采购</span><select v-model="basic.has_furniture" class="field"><option :value="false">否</option><option :value="true">是</option></select></label>
                        <label><span class="label">含设备固定资产采购</span><select v-model="basic.has_equipment_asset" class="field"><option :value="false">否</option><option :value="true">是</option></select></label>
                    </template>
                    <template v-else><label><span class="label">是否有使用部门 <span class="required">*</span></span><select v-model="basic.has_usage_department" class="field"><option :value="false">否</option><option :value="true">是</option></select></label><label v-if="basic.has_usage_department"><span class="label">使用部门 <span class="required">*</span></span><select v-model="basic.usage_department_id" class="field" :class="{ 'select-placeholder': basic.usage_department_id === null }"><option :value="null">请选择</option><option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option></select><small class="error">{{ basic.errors.usage_department_id }}</small></label><div><span class="label">是否为非支出项目 <span class="required">*</span></span><select v-model="basic.is_non_expenditure" class="field"><option :value="false">否</option><option :value="true">是</option></select></div></template>
                </div></div>
                <div class="form-group"><h3>采购要求与备注</h3><div class="space-y-5">
                    <label v-if="selfPurchase" class="block"><span class="label">采购理由 <span class="required">*</span></span><select v-model="basic.reason_category" class="field"><option value="">请选择</option><option>原采购（服务）合同到期，需重新采购</option><option>原设备损坏或已无法满足现有需求，需重新采购</option><option>因会议或活动（课题等）需要，申请采购（列明会议活动或课题名称及内容）</option><option>上述都不是，请自行列明</option></select><small class="error">{{ basic.errors.reason_category }}</small></label>
                    <label v-if="selfPurchase" class="block"><span class="label">理由说明{{ basic.reason_category === '上述都不是，请自行列明' ? ' *' : '' }}</span><textarea v-model="basic.reason" class="field min-h-24" placeholder="说明具体背景与用途；选择“上述都不是”时必填" /><small class="error">{{ basic.errors.reason }}</small></label>
                    <label class="block"><span class="label">采购要求（评标、供货、质保等） <span class="required">*</span></span><textarea v-model="basic.procurement_requirements" class="field min-h-36" rows="6" /><small class="error">{{ basic.errors.procurement_requirements }}</small></label>
                    <label class="block"><span class="label">备注</span><textarea v-model="basic.remarks" class="field min-h-28" rows="4" /></label>
                </div></div>
            </div>
            <button type="button" class="save" :disabled="activeSaving || !departmentAssigned" :aria-busy="basic.processing" @click="save('basic')">{{ basic.processing ? '保存中…' : '保存基本信息' }}</button>
            <p v-if="saveFeedback.basic.state !== 'idle'" role="status" aria-live="polite" class="mt-3 text-center text-sm" :class="feedbackClass('basic')">{{ saveFeedback.basic.message }}<span v-if="saveFeedback.basic.time"> · {{ saveFeedback.basic.time }}</span></p>
        </section>
        <section v-if="basic.category === '工程类'" id="items" class="panel">
            <h2 class="section-title">采购清单 · 工程类</h2>
            <p v-if="selfPurchase" class="mb-5 rounded-[var(--brand-radius-md)] border border-[var(--brand-warning)] bg-[var(--brand-soft)] p-4 text-sm text-[var(--brand-warning)]">工程（维修）项目须按《浙江财经大学东方学院修缮工程管理办法（试行）》办理，并报招标办备案。</p>
            <div class="space-y-5">
                <label class="block"><span class="label">工程名称 <span class="required">*</span></span><input v-model="engineeringForm.project_name" class="field" /><small class="error">{{ engineeringForm.errors.project_name }}</small></label>
                <label class="block"><span class="label">项目建设内容 <span class="required">*</span></span><textarea v-model="engineeringForm.construction_content" class="field min-h-28" placeholder="面积、层数、结构、维修改造的具体内容等" /><small class="error">{{ engineeringForm.errors.construction_content }}</small></label>
                <label class="block"><span class="label">项目概况 <span class="required">*</span></span><textarea v-model="engineeringForm.project_overview" class="field min-h-28" placeholder="项目经费来源项目说明；如为子项目，说明与总项目的关系" /><small class="error">{{ engineeringForm.errors.project_overview }}</small></label>
                <label class="block"><span class="label">项目工期要求 <span class="required">*</span></span><textarea v-model="engineeringForm.schedule_requirements" class="field min-h-24" /><small class="error">{{ engineeringForm.errors.schedule_requirements }}</small></label>
                <label class="block"><span class="label">工程总造价（元） <span class="required">*</span></span><input v-model="engineeringForm.estimated_cost" class="field" type="number" min="0" step="0.01" inputmode="decimal" /><small class="field-hint">金额保留两位小数</small><small v-if="invalidMoney(engineeringForm.estimated_cost)" class="error">请输入非负金额，最多保留两位小数。</small><small class="error">{{ engineeringForm.errors.estimated_cost }}</small></label>
                <div><span class="label">清单附件 <span class="required">*</span></span><input type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg" @change="chooseEngineeringBill" /><small class="error">{{ engineeringForm.errors.bill_file }}</small><p v-if="engineeringBill" class="mt-2 flex flex-wrap items-center gap-[var(--brand-space-16)] text-sm"><span>已上传：{{ engineeringBill.original_name }}</span><a v-if="canPreviewAttachment(engineeringBill.mime_type)" :href="`/procurement/attachments/${engineeringBill.id}/preview`" target="_blank" rel="noopener noreferrer" class="text-[var(--brand-primary)] hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--brand-primary)]">预览</a><a :href="`/procurement/attachments/${engineeringBill.id}/download`" class="text-[var(--brand-primary)] hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--brand-primary)]">下载</a></p></div>
            </div>
            <button type="button" class="save" :disabled="activeSaving || !departmentAssigned" :aria-busy="engineeringForm.processing" @click="save('engineering')">{{ engineeringForm.processing ? '保存中…' : '保存工程清单' }}</button>
            <p v-if="saveFeedback.engineering.state !== 'idle'" role="status" aria-live="polite" class="mt-3 text-center text-sm" :class="feedbackClass('engineering')">{{ saveFeedback.engineering.message }}<span v-if="saveFeedback.engineering.time"> · {{ saveFeedback.engineering.time }}</span></p>
        </section>
        <section v-else id="items" class="panel">
            <h2 class="section-title">采购清单</h2>
            <div class="mb-4 flex flex-wrap items-center gap-3 text-sm">
                <button type="button" class="action" @click="openItemEditor()">＋ 添加采购清单</button>
                <select v-model="selectedCatalog" class="field max-w-56"><option value="">从个人物资库选择</option><option v-for="item in catalog" :key="item.id" :value="item.id">{{ item.name }}</option></select>
                <button type="button" class="action" @click="addFromCatalog">抽取</button>
                <button type="button" class="action" :disabled="downloadingTemplate" @click="downloadItemTemplate">{{ downloadingTemplate ? '生成模板中…' : '下载导入模板' }}</button>
                <label class="action cursor-pointer focus-within:outline-2 focus-within:outline-[var(--brand-primary)]">{{ importingItems ? '导入中…' : '导入 Excel/CSV' }}<input type="file" accept=".xlsx,.csv" class="sr-only" :disabled="importingItems || activeSaving" @change="importItems" /></label>
            </div>
            <p class="mb-3 text-xs text-[var(--brand-muted)]">请先下载模板。货物名称、数量、单价为必填项；合计最多500项，导入后追加到现有清单。</p>
            <p v-if="importError" role="alert" class="error">{{ importError }}</p>
            <p v-if="importSuccess" role="status" class="mb-3 text-sm text-[var(--brand-success)]">{{ importSuccess }}</p>
            <div v-if="Object.keys(itemForm.errors).length" role="alert" class="mb-4 rounded-[var(--brand-radius-md)] border border-[var(--brand-danger)] p-3 text-sm text-[var(--brand-danger)]">
                <p>采购清单保存失败，请修改以下内容：</p>
                <p v-for="(message, field) in itemForm.errors" :key="field">{{ message }}</p>
            </div>
            <div class="overflow-x-auto rounded-[var(--brand-radius-md)] border border-[var(--brand-border)]">
                <table class="w-full min-w-[1000px] table-fixed text-left text-sm text-[var(--brand-body)]">
                    <thead class="bg-[var(--brand-soft)] text-xs text-[var(--brand-muted)]"><tr>
                        <th class="w-14 p-3">序号</th><th class="w-44 p-3">货物名称</th><th class="w-48 p-3">技术参数</th><th class="w-20 p-3 text-right">数量</th><th class="w-28 p-3 text-right">单价（元）</th><th class="w-16 p-3">单位</th><th class="w-28 p-3 text-right">总价（元）</th><th class="w-40 p-3">备注</th><th class="w-28 p-3">操作</th>
                    </tr></thead>
                    <tbody class="divide-y divide-[var(--brand-border)]">
                        <tr v-for="(item, index) in itemForm.items" :key="index" class="hover:bg-[var(--brand-primary-light)]">
                            <td class="p-3 align-top">{{ index + 1 }}</td>
                            <td class="p-3 align-top"><span class="block truncate font-medium text-[var(--brand-title)]" :title="item.name">{{ item.name }}</span><p v-for="(message, errorIndex) in itemErrors(index)" :key="errorIndex" class="mt-1 text-xs text-[var(--brand-danger)]">{{ message }}</p></td>
                            <td class="p-3 align-top"><span class="block truncate" :title="item.specification">{{ item.specification || '—' }}</span></td>
                            <td class="p-3 text-right align-top tabular-nums">{{ item.quantity }}</td>
                            <td class="whitespace-nowrap p-3 text-right align-top tabular-nums">{{ money(Number(item.unit_price)) }}</td>
                            <td class="p-3 align-top"><span class="block truncate" :title="item.unit">{{ item.unit || '—' }}</span></td>
                            <td class="whitespace-nowrap p-3 text-right align-top tabular-nums">{{ money(Math.round(Number(item.unit_price) * 100 * Number(item.quantity)) / 100) }}</td>
                            <td class="p-3 align-top"><span class="block truncate" :title="item.remarks">{{ item.remarks || '—' }}</span></td>
                            <td class="p-3 align-top"><div class="flex gap-3"><button type="button" class="item-link" :disabled="activeSaving" :aria-label="`修改第${index + 1}项 ${item.name}`" @click="openItemEditor(index)">修改</button><button type="button" class="item-link !text-[var(--brand-danger)]" :disabled="activeSaving" :aria-label="`删除第${index + 1}项 ${item.name}`" @click="deleteItem(index)">删除</button></div></td>
                        </tr>
                        <tr v-if="!itemForm.items.length"><td colspan="9" class="p-8 text-center text-[var(--brand-muted)]">暂无采购清单，请添加项目、从物资库抽取或导入文件。</td></tr>
                    </tbody>
                </table>
            </div>
            <Dialog v-model:open="itemEditorOpen">
                <DialogContent class="item-editor max-h-[90dvh] overflow-y-auto border-[var(--brand-border)] bg-[var(--brand-card)] text-[var(--brand-body)] sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle class="text-xl text-[var(--brand-title)]">{{ editingItemIndex === null ? '添加采购清单' : '修改采购清单' }}</DialogTitle>
                        <DialogDescription class="text-[var(--brand-muted)]">确认后加入下方列表；点击“保存采购清单”提交全部更改。</DialogDescription>
                    </DialogHeader>
                    <form class="grid gap-4 sm:grid-cols-2" novalidate @submit.prevent="confirmItem">
                        <label class="sm:col-span-2"><span class="label">货物名称 <span class="required">*</span></span><input v-model="itemDraft.name" class="field" maxlength="255" :aria-invalid="!!itemDraftErrors.name" /><small v-if="itemDraftErrors.name" role="alert" class="error">{{ itemDraftErrors.name }}</small></label>
                        <label class="sm:col-span-2"><span class="label">技术参数、配置/主要配置</span><textarea v-model="itemDraft.specification" class="field min-h-24" rows="3" /></label>
                        <label><span class="label">数量 <span class="required">*</span></span><input v-model.number="itemDraft.quantity" class="field" type="number" min="0.001" step="0.001" :aria-invalid="!!itemDraftErrors.quantity" /><small class="field-hint">最多保留三位小数</small><small v-if="itemDraftErrors.quantity" role="alert" class="error">{{ itemDraftErrors.quantity }}</small></label>
                        <label><span class="label">单价（元） <span class="required">*</span></span><input v-model.number="itemDraft.unit_price" class="field" type="number" min="0" step="0.01" inputmode="decimal" :aria-invalid="!!itemDraftErrors.unit_price" /><small v-if="itemDraftErrors.unit_price" role="alert" class="error">{{ itemDraftErrors.unit_price }}</small></label>
                        <label><span class="label">单位</span><input v-model="itemDraft.unit" class="field" maxlength="50" /><small v-if="itemDraftErrors.unit" role="alert" class="error">{{ itemDraftErrors.unit }}</small></label>
                        <div class="self-end pb-2 text-sm font-semibold">总价：¥{{ money(Math.round(Number(itemDraft.unit_price || 0) * 100 * Number(itemDraft.quantity || 0)) / 100) }}</div>
                        <label class="sm:col-span-2"><span class="label">备注</span><textarea v-model="itemDraft.remarks" class="field min-h-20" rows="2" /></label>
                        <div class="flex flex-wrap justify-end gap-3 border-t border-[var(--brand-border)] pt-4 sm:col-span-2">
                            <button type="button" class="item-editor-cancel" @click="itemEditorOpen = false">取消</button>
                            <button type="submit" class="item-editor-confirm">{{ editingItemIndex === null ? '添加到清单' : '确定修改' }}</button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>
            <p class="mt-5 text-right font-semibold">合计：¥{{ money(total) }}</p>
            <button type="button" class="save" :disabled="activeSaving || !departmentAssigned" :aria-busy="itemForm.processing" @click="save('items')">{{ itemForm.processing ? '保存中…' : '保存采购清单' }}</button>
            <p v-if="saveFeedback.items.state !== 'idle'" role="status" aria-live="polite" class="mt-3 text-center text-sm" :class="saveFeedback.items.state === 'error' ? 'text-[var(--brand-danger)]' : saveFeedback.items.state === 'saved' ? 'text-[var(--brand-success)]' : 'text-[var(--brand-muted)]'">{{ saveFeedback.items.message }}<span v-if="saveFeedback.items.time"> · {{ saveFeedback.items.time }}</span></p>
            <div class="mt-8 border-t border-[var(--brand-border)] pt-6">
                <h3 class="mb-3 font-semibold">我的物资库</h3>
                <div class="grid gap-3 md:grid-cols-3"><input v-model="catalogForm.name" class="field" placeholder="商品名称" /><input v-model="catalogForm.specification" class="field" placeholder="技术参数" /><input v-model="catalogForm.unit" class="field" placeholder="单位" /><input v-model.number="catalogForm.unit_price" class="field" type="number" min="0" step="0.01" placeholder="单价" /><input v-model="catalogForm.remarks" class="field" placeholder="备注" /><button type="button" class="action" @click="addCatalog">{{ editingCatalogId ? '更新个人物资' : '保存到我的物资库' }}</button></div>
                <p class="error">{{ catalogForm.errors.name }} {{ catalogForm.errors.unit_price }}</p>
                <ul class="mt-4 space-y-2 text-sm"><li v-for="item in catalog" :key="item.id" class="flex justify-between border-b border-[var(--brand-border)] py-2"><span>{{ item.name }} · {{ item.specification }} · ¥{{ money(item.unit_price_cents / 100) }}</span><span class="flex gap-3"><button type="button" class="text-[var(--brand-primary)]" @click="editCatalog(item)">编辑</button><button type="button" class="text-[var(--brand-danger)]" @click="removeCatalog(item.id)">删除</button></span></li></ul>
            </div>
        </section>
        <section id="budget" class="panel">
            <h2 class="section-title">项目预算指标</h2>
            <p class="mb-4 text-sm">采购清单合计：¥{{ money(total) }}　预算指标使用金额合计：¥{{ money(allocated) }}</p>
            <button v-if="canSyncBudgets" type="button" class="action mb-4" @click="syncBudgetIndicators">手动同步预算指标</button>
            <button type="button" class="action mb-4 ml-3" @click="budgetForm.allocations.push({ budget_indicator_id: 0, amount: 0, code: null, name: '', director: null }); openIndicatorPicker(budgetForm.allocations.length - 1)">＋ 添加经费来源</button>
            <p class="error">{{ budgetForm.errors.allocations }}</p>
            <div v-for="(allocation, index) in budgetForm.allocations" :key="index" class="mb-4 grid items-end gap-3 rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] p-4 md:grid-cols-[1fr_11rem_auto]">
                <div><span class="label">预算编码 / 预算项目名称 / 负责人</span><button type="button" class="field text-left" @click="openIndicatorPicker(index)">{{ allocation.budget_indicator_id ? `${allocation.code ?? '无代码'} · ${allocation.name} · ${allocation.director ?? '无负责人'}` : '搜索并选择经费来源' }}</button><small class="error">{{ budgetForm.errors[`allocations.${index}.budget_indicator_id` as keyof typeof budgetForm.errors] }}</small></div>
                <label><span class="label">使用金额（元）</span><input v-model.number="allocation.amount" class="field" type="number" min="0.01" step="0.01" /></label>
                <button type="button" class="text-[var(--brand-danger)]" @click="budgetForm.allocations.splice(index, 1)">删除</button>
            </div>
            <div v-if="pickerIndex !== null" class="mb-5 rounded-[var(--brand-radius-md)] border border-[var(--brand-primary)] bg-[var(--brand-soft)] p-4">
                <div class="mb-3 flex items-center justify-between"><h3 class="font-semibold">选择经费来源</h3><button type="button" class="text-sm text-[var(--brand-muted)]" @click="pickerIndex = null">关闭</button></div>
                <form class="grid gap-2 md:grid-cols-[2fr_1fr_1fr_auto]" @submit.prevent="searchIndicators()">
                    <input v-model="pickerQuery" class="field" placeholder="搜索经费名称或预算代码" aria-label="搜索经费名称或预算代码" />
                    <select v-model="pickerDepartment" class="field" aria-label="按部门筛选"><option value="">全部部门</option><option v-for="department in pickerDepartments" :key="department.code" :value="department.code">{{ department.name ?? department.code }}</option></select>
                    <select v-model="pickerStatus" class="field" aria-label="按状态筛选"><option value="">全部状态</option><option v-for="status in pickerStatuses" :key="status ?? '__null'" :value="status ?? '__null'">{{ status === '1' ? '启用' : status === null ? '未标记' : `状态 ${status}` }}</option></select>
                    <button type="submit" class="action" :disabled="pickerLoading">查询</button>
                </form>
                <p v-if="pickerError" class="error mt-2">{{ pickerError }}</p>
                <p class="my-3 text-xs text-[var(--brand-muted)]">共 {{ pickerTotal }} 条。未启用的经费来源仅供查看。</p>
                <div class="max-h-72 space-y-2 overflow-y-auto">
                    <button v-for="indicator in pickerRows" :key="indicator.id" type="button" class="w-full rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-3 text-left text-sm disabled:opacity-50" :disabled="indicator.status !== '1' || budgetForm.allocations.some((allocation, index) => index !== pickerIndex && allocation.budget_indicator_id === indicator.id)" @click="selectIndicator(indicator)">
                        <span class="font-medium">{{ indicator.code ?? '无代码' }} · {{ indicator.name }}</span>
                        <span class="ml-2 text-[var(--brand-muted)]">{{ indicator.director ?? '无负责人' }} · {{ indicator.department_name ?? indicator.department_code ?? '无部门' }} · {{ indicator.status === '1' ? '启用' : indicator.status === null ? '未标记' : `状态 ${indicator.status}` }}</span>
                    </button>
                    <p v-if="!pickerLoading && pickerRows.length === 0 && pickerSourceTotal === 0" class="text-sm text-[var(--brand-warning)]">经费代码尚未同步，请联系采购管理员执行同步。</p>
                    <p v-else-if="!pickerLoading && pickerRows.length === 0" class="text-sm text-[var(--brand-muted)]">没有匹配的经费来源，请调整关键词或筛选条件。</p>
                </div>
                <div class="mt-3 flex items-center justify-end gap-3 text-sm"><button type="button" :disabled="pickerPage <= 1 || pickerLoading" @click="searchIndicators(pickerPage - 1)">上一页</button><span>{{ pickerPage }} / {{ pickerLastPage }}</span><button type="button" :disabled="pickerPage >= pickerLastPage || pickerLoading" @click="searchIndicators(pickerPage + 1)">下一页</button></div>
            </div>
            <label><span class="label">预算测算说明</span><textarea v-model="budgetForm.budget_basis_note" class="field min-h-24" /></label>
            <button type="button" class="save" :disabled="activeSaving || !departmentAssigned" :aria-busy="budgetForm.processing" @click="save('budget')">{{ budgetForm.processing ? '保存中…' : '保存预算指标' }}</button>
            <p v-if="saveFeedback.budget.state !== 'idle'" role="status" aria-live="polite" class="mt-3 text-center text-sm" :class="feedbackClass('budget')">{{ saveFeedback.budget.message }}<span v-if="saveFeedback.budget.time"> · {{ saveFeedback.budget.time }}</span></p>
        </section>
        <section id="attachments" class="panel">
            <h2 class="section-title">附件</h2>
            <p class="mb-4 text-sm text-[var(--brand-muted)]">按需上传论证资料、办公会纪要、党政联席会议单等。支持 PDF、Word、Excel 和图片，单个文件不超过 20 MB。</p>
            <input ref="attachmentInput" type="file" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg" class="sr-only" tabindex="-1" @change="chooseFiles" />
            <button type="button" class="action" :disabled="attachmentForm.processing || !departmentAssigned" @click="attachmentInput?.click()">选择文件上传</button>
            <p v-if="attachmentForm.files.length" class="mt-[var(--brand-space-16)] text-sm font-semibold text-[var(--brand-title)]">待上传文件</p>
            <ul v-if="attachmentForm.files.length" class="mt-[var(--brand-space-8)] divide-y divide-[var(--brand-border)] rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] px-[var(--brand-space-16)]">
                <li v-for="(file, index) in attachmentForm.files" :key="index" class="flex flex-wrap items-center justify-between gap-[var(--brand-space-8)] py-[var(--brand-space-8)] text-sm"><span class="min-w-0 break-all text-[var(--brand-body)]">{{ file.name }}</span><button type="button" class="text-[var(--brand-danger)] hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--brand-danger)]" @click="attachmentForm.files.splice(index, 1)">移除</button></li>
            </ul>
            <p class="error">{{ attachmentForm.errors.files }}</p>
            <p v-if="attachments.length" class="mt-[var(--brand-space-24)] text-sm font-semibold text-[var(--brand-title)]">已上传附件</p>
            <ul v-if="attachments.length" class="mt-[var(--brand-space-8)] divide-y divide-[var(--brand-border)] rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] px-[var(--brand-space-16)]"><li v-for="file in attachments" :key="file.id" class="flex flex-wrap items-center justify-between gap-[var(--brand-space-8)] py-[var(--brand-space-8)] text-sm"><span class="min-w-0 break-all" :class="attachmentForm.delete_ids.includes(file.id) ? 'text-[var(--brand-muted)] line-through' : 'text-[var(--brand-body)]'">{{ file.original_name }}</span><span class="flex items-center gap-[var(--brand-space-16)]"><span v-if="attachmentForm.delete_ids.includes(file.id)" class="text-[var(--brand-danger)]">待删除</span><a v-if="canPreviewAttachment(file.mime_type) && !attachmentForm.delete_ids.includes(file.id)" class="text-[var(--brand-primary)] hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--brand-primary)]" :href="`/procurement/attachments/${file.id}/preview`" target="_blank" rel="noopener noreferrer">预览</a><a v-if="!attachmentForm.delete_ids.includes(file.id)" class="text-[var(--brand-primary)] hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--brand-primary)]" :href="`/procurement/attachments/${file.id}/download`">下载</a><button type="button" :disabled="attachmentForm.processing" class="text-[var(--brand-danger)] hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--brand-danger)]" @click="toggleAttachmentDeletion(file.id)">{{ attachmentForm.delete_ids.includes(file.id) ? '撤销' : '删除' }}</button></span></li></ul>
            <p v-if="attachmentForm.errors.delete_ids" class="error">{{ attachmentForm.errors.delete_ids }}</p>
            <button type="button" class="save" :disabled="activeSaving || !departmentAssigned" :aria-busy="attachmentForm.processing" @click="save('attachments')">{{ attachmentForm.processing ? '保存中…' : '保存附件' }}</button>
            <p v-if="saveFeedback.attachments.state !== 'idle'" role="status" aria-live="polite" class="mt-3 text-center text-sm" :class="feedbackClass('attachments')">{{ saveFeedback.attachments.message }}<span v-if="saveFeedback.attachments.time"> · {{ saveFeedback.attachments.time }}</span></p>
        </section>
        <div v-if="Object.keys(page.props.errors ?? {}).length" class="mt-6 rounded border border-[var(--brand-danger)] p-4 text-sm text-[var(--brand-danger)]"><p v-for="(message, key) in page.props.errors" :key="key">{{ message }}</p></div>
        <footer class="form-action-bar flex flex-wrap items-center justify-between gap-3 rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-3 shadow-[var(--brand-shadow)] md:px-6">
            <div class="text-xs text-[var(--brand-muted)]"><p>当前模块：{{ tabs.find((entry) => entry.key === tab)?.label }} · {{ application ? '各模块分别保存' : '先保存任一模块以生成草稿' }}</p><p v-if="selfPurchase && total >= threshold" class="text-[var(--brand-warning)]">已达统一采购金额：¥{{ money(threshold) }}</p><p v-else-if="!selfPurchase && total < threshold" class="text-[var(--brand-warning)]">统一采购金额门槛：¥{{ money(threshold) }}</p></div>
            <div class="flex flex-wrap items-center gap-2"><Link href="/procurement/applications" class="action">返回申请列表</Link><button type="button" class="action" :disabled="activeSaving || !departmentAssigned" @click="saveCurrentSection">{{ activeSaving ? '保存中…' : '保存当前模块（草稿）' }}</button><button type="button" class="save mt-0" :disabled="activeSaving || !departmentAssigned || !application" @click="submit">提交审核</button></div>
        </footer>
    </main>
</template>

<style scoped>
.tender-form-page { max-width: none; padding-bottom: 9rem; }
.section-nav { position: sticky; top: 0; z-index: 10; }
.panel { min-width: 0; margin-bottom: 1.5rem; scroll-margin-top: 5.5rem; border: 1px solid var(--brand-border); background: var(--brand-card); padding: 1.5rem; border-radius: var(--brand-radius-lg); box-shadow: var(--brand-shadow); }
.form-action-bar { position: fixed; z-index: 30; right: 2.5rem; bottom: 1rem; left: calc(var(--sidebar-width) + 2.5rem); }
:global(.admin-app:has([data-collapsible='icon'])) .form-action-bar { left: calc(var(--sidebar-width-icon) + 2.5rem); }
.form-groups { display: grid; gap: 1rem; }
.form-group { border: 1px solid var(--brand-border); border-radius: var(--brand-radius-md); padding: 1.25rem; background: var(--brand-card); }
.form-group h3 { margin-bottom: 1rem; color: var(--brand-title); font-size: 1rem; font-weight: 600; }
.form-grid { display: grid; gap: 1.25rem; }
@media (min-width: 640px) { .form-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
.number-notice { border-color: var(--brand-primary-light); background: var(--brand-primary-light); color: var(--brand-primary); }
.number-notice p span { color: var(--brand-body); }
.required { color: var(--brand-danger); font-weight: 700; }
.field-hint { display: block; margin-top: .3rem; color: var(--brand-muted); font-size: .75rem; }
.read-only-value { display: flex; align-items: center; background: var(--brand-soft) !important; color: var(--brand-muted); }
.amount-value { justify-content: space-between; font-variant-numeric: tabular-nums; }
.help-wrap { position: relative; display: inline-flex; margin-left: .4rem; vertical-align: middle; }
.help-trigger { width: 1.1rem; height: 1.1rem; border: 1px solid var(--brand-muted); border-radius: 50%; color: var(--brand-muted); font-size: .7rem; line-height: 1; }
.help-text { display: none; position: absolute; z-index: 20; top: 1.5rem; left: 0; width: min(17rem, 70vw); border: 1px solid var(--brand-border); border-radius: var(--brand-radius-sm); padding: .6rem .75rem; background: var(--brand-card); box-shadow: var(--brand-shadow); color: var(--brand-body); font-size: .75rem; line-height: 1.5; }
.help-wrap:hover .help-text, .help-wrap:focus-within .help-text { display: block; }
.panel :deep(input), .panel :deep(textarea), .panel :deep(select) { max-width: 100%; }
.section-title { border-bottom: 1px solid var(--brand-border); padding-bottom: 1rem; margin-bottom: 1.5rem; color: var(--brand-title); font-size: 1.25rem; font-weight: 600; line-height: 1.35; }
.label { display: block; font-size: .8rem; color: var(--brand-body); margin-bottom: .4rem; }
.field { width: 100%; min-height: 2.5rem; border: 1px solid var(--brand-border); border-radius: 10px; padding: .5rem .75rem; background-color: var(--brand-card); color: var(--brand-body); font-size: .875rem; }
.field:hover:not(:disabled) { border-color: var(--brand-primary); }
.select-placeholder { color: var(--brand-muted); background-color: var(--brand-soft); }
.field:focus { outline: 2px solid var(--brand-primary); outline-offset: 1px; }
.field:disabled { background: var(--brand-soft); color: var(--brand-muted); }
.error { color: var(--brand-danger); font-size: .75rem; }
.item-link { color: var(--brand-primary); white-space: nowrap; }
.item-link:hover { text-decoration: underline; }
.item-link:disabled { opacity: .5; }
.item-editor-confirm, .item-editor-cancel { min-height: 40px; border-radius: var(--brand-radius-md); padding: .5rem 1.25rem; font-size: .875rem; }
.item-editor-confirm { background: var(--brand-primary); color: var(--brand-on-primary); }
.item-editor-confirm:hover { background: var(--brand-primary-hover); }
.item-editor-cancel { border: 1px solid var(--brand-border); color: var(--brand-primary); background: var(--brand-card); }
.item-link:focus-visible, .item-editor-confirm:focus-visible, .item-editor-cancel:focus-visible { outline: 2px solid var(--brand-primary); outline-offset: 2px; }
.save { display: block; margin: 1.5rem auto 0; min-height: 2.5rem; background: var(--brand-primary); color: var(--brand-on-primary); border-radius: var(--brand-radius-md); padding: .65rem 1.75rem; font-size: .875rem; }
.save:hover:not(:disabled) { background: var(--brand-primary-hover); }
.save:disabled { opacity: .5; }
.action { display: inline-flex; align-items: center; justify-content: center; min-height: 2.5rem; border: 1px solid var(--brand-border); border-radius: var(--brand-radius-md); padding: .45rem .85rem; color: var(--brand-primary); background: var(--brand-card); font-size: .875rem; }
.action:hover { background: var(--brand-primary-light); }
.action:disabled { opacity: .5; cursor: not-allowed; }
.section-nav a:focus-visible, .save:focus-visible, .action:focus-visible { outline: 2px solid var(--brand-primary); outline-offset: 2px; }
@media (max-width: 767px) { .form-action-bar { left: 1rem; right: 1rem; bottom: .5rem; } .tender-form-page { padding-bottom: 13rem; } }
@media (max-width: 640px) { .panel { padding: 1rem; } .form-group { padding: 1rem; } }
</style>
