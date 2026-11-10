<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';

type Item = { name: string; specification: string; quantity: number; unit_price: number; unit: string; remarks: string };
type SavedItem = { name: string; specification: string | null; quantity: number; unit_price_cents: number; unit: string | null; remarks: string | null };
type Indicator = { id: number; code: string | null; name: string; director: string | null; department_code: string | null; department_name: string | null; status: string | null; available_cents: number | null };
type CatalogItem = { id: number; name: string; specification: string | null; unit: string | null; unit_price_cents: number; remarks: string | null };
type Application = { id: number; status: string; project_number: string | null; title: string | null; category: string | null; contact_phone: string | null; purchase_method: string | null; is_special_purchase: boolean; special_purchase_reason: string | null; is_non_expenditure: boolean; has_usage_department: boolean; usage_department_id: number | null; procurement_requirements: string | null; remarks: string | null; budget_basis_note: string | null; budget_cents: number | null };
type Attachment = { id: number; original_name: string; size: number };
type Allocation = { budget_indicator_id: number; amount_cents: number; code: string | null; name: string; director: string | null };
type Engineering = { project_name: string | null; construction_content: string | null; project_overview: string | null; schedule_requirements: string | null; estimated_cost_cents: number | null };

const props = defineProps<{
    application: Application | null;
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
    contact_phone: props.application ? (props.application.contact_phone ?? '') : (props.applicantPhone ?? ''), purchase_method: props.application?.purchase_method ?? '公开招标',
    is_special_purchase: props.application?.is_special_purchase ?? false,
    special_purchase_reason: props.application?.special_purchase_reason ?? '',
    is_non_expenditure: props.application?.is_non_expenditure ?? false,
    has_usage_department: props.application?.has_usage_department ?? false,
    usage_department_id: props.application?.usage_department_id ?? null as number | null,
    procurement_requirements: props.application?.procurement_requirements ?? '', remarks: props.application?.remarks ?? '',
});
watch(() => basic.is_special_purchase, (special) => {
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
const attachmentForm = useForm<{ section: string; files: File[] }>({ section: 'attachments', files: [] });
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

function blankItem(): Item { return { name: '', specification: '', quantity: 1, unit_price: 0, unit: '', remarks: '' }; }
function save(section: Tab | 'engineering') {
    const form = section === 'basic' ? basic : section === 'items' ? itemForm : section === 'engineering' ? engineeringForm : section === 'budget' ? budgetForm : attachmentForm;
    const options = { preserveScroll: true, onSuccess: () => { if (section === 'attachments') attachmentForm.files = []; if (section === 'engineering') engineeringForm.bill_file = null; } };
    if (props.application) form.post(`/procurement/tenders/${props.application.id}/sections`, options);
    else form.post('/procurement/tenders/sections', options);
}
function submit() {
    if (!props.application) return;
    router.post(`/procurement/tenders/${props.application.id}/submit`, {}, { preserveScroll: true });
}
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
    attachmentForm.files = Array.from((event.target as HTMLInputElement).files ?? []);
}
function chooseEngineeringBill(event: Event) {
    engineeringForm.bill_file = (event.target as HTMLInputElement).files?.[0] ?? null;
}
async function importItems(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;
    try {
        const ExcelJS = await import('exceljs');
        const workbook = new ExcelJS.Workbook();
        if (file.name.toLowerCase().endsWith('.csv')) {
            const csv = await file.text();
            const rows = csv.split(/\r?\n/).filter(Boolean).map((line) => {
                const cells: string[] = [];
                let value = ''; let quoted = false;
                for (let i = 0; i < line.length; i++) {
                    const char = line[i];
                    if (char === '"' && quoted && line[i + 1] === '"') { value += '"'; i++; }
                    else if (char === '"') quoted = !quoted;
                    else if (char === ',' && !quoted) { cells.push(value); value = ''; }
                    else value += char;
                }
                cells.push(value);
                return cells;
            });
            const csvSheet = workbook.addWorksheet('导入');
            for (const row of rows.slice(1)) csvSheet.addRow(row);
        } else await workbook.xlsx.load(await file.arrayBuffer());
        const sheet = workbook.worksheets[0];
        if (!sheet) throw new Error('文件中没有工作表');
        const imported: Item[] = [];
        sheet.eachRow((row, rowNumber) => {
            if (rowNumber === 1 && !file.name.toLowerCase().endsWith('.csv')) return;
            const cell = (index: number) => String(row.getCell(index).text ?? '').trim();
            if (!cell(1)) return;
            imported.push({ name: cell(1), specification: cell(2), quantity: Number(cell(3)), unit_price: Number(cell(4)), unit: cell(5), remarks: cell(6) });
        });
        if (imported.some((item) => !Number.isFinite(item.quantity) || item.quantity <= 0 || !Number.isFinite(item.unit_price) || item.unit_price < 0)) throw new Error('数量或单价无效');
        itemForm.items.push(...imported);
        importError.value = '';
    } catch (error) { importError.value = error instanceof Error ? error.message : '导入失败'; }
    (event.target as HTMLInputElement).value = '';
}
const importError = ref('');
</script>

<template>
    <Head title="统一招标申请填报" />
    <main class="tender-form-page w-full min-w-0 px-4 py-6 md:px-8 md:py-8">
        <header class="mb-6 border-b border-[var(--brand-border)] pb-5">
            <h1 class="text-[18px] font-semibold text-[var(--brand-title)]">采购申请单</h1>
            <p v-if="application?.project_number" class="mt-2 text-xs text-[var(--brand-muted)]">项目编号：{{ application.project_number }}</p>
        </header>
        <div v-if="!application" class="number-notice mb-6 flex items-start gap-3 rounded-[var(--brand-radius-md)] border p-4 text-sm" role="note"><span aria-hidden="true" class="font-bold">i</span><p><strong>项目编号自动生成</strong><br><span class="text-xs">首次保存任一模块后，系统将自动生成项目编号。</span></p></div>
        <div v-if="application?.status === 'returned'" class="mb-5 rounded-[var(--brand-radius-md)] border border-[var(--brand-warning)] bg-[var(--brand-soft)] p-4 text-sm text-[var(--brand-warning)]">此申请已退回。请分别保存修改过的页签，再点击底部“提交审核”，系统将开启新一轮审批。</div>
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
                    <label><span class="label">是否特殊采购</span><select v-model="basic.is_special_purchase" class="field"><option :value="false">否</option><option :value="true">是</option></select></label>
                    <label><span class="label">拟采购方式</span><input v-if="!basic.is_special_purchase" class="field" value="公开招标" disabled /><select v-else v-model="basic.purchase_method" class="field" :class="{ 'select-placeholder': !basic.purchase_method }"><option value="">请选择</option><option>邀请招标</option><option>竞争性谈判</option><option>竞争性磋商</option><option>单一来源</option><option>询价</option><option>谈判</option><option>政采云等平台</option></select><small class="error">{{ basic.errors.purchase_method }}</small></label>
                    <label v-if="basic.is_special_purchase" class="sm:col-span-2"><span class="label">申请特殊采购方式理由</span><textarea v-model="basic.special_purchase_reason" class="field min-h-24" /><small class="error">{{ basic.errors.special_purchase_reason }}</small></label>
                    <label><span class="label">是否有使用部门 <span class="required">*</span></span><select v-model="basic.has_usage_department" class="field"><option :value="false">否</option><option :value="true">是</option></select></label>
                    <label v-if="basic.has_usage_department"><span class="label">使用部门 <span class="required">*</span></span><select v-model="basic.usage_department_id" class="field" :class="{ 'select-placeholder': basic.usage_department_id === null }"><option :value="null">请选择</option><option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option></select><small class="error">{{ basic.errors.usage_department_id }}</small></label>
                    <div><div class="label"><label for="non-expenditure">是否为非支出项目 <span class="required">*</span></label><span class="help-wrap"><button type="button" class="help-trigger" aria-label="非支出项目说明">?</button><span class="help-text" role="tooltip">非支出项目指调拨、捐赠等不产生经费支付的采购事项。</span></span></div><select id="non-expenditure" v-model="basic.is_non_expenditure" class="field"><option :value="false">否</option><option :value="true">是</option></select></div>
                </div></div>
                <div class="form-group"><h3>采购要求与备注</h3><div class="space-y-5">
                    <label class="block"><span class="label">采购要求（评标、供货、质保等） <span class="required">*</span></span><textarea v-model="basic.procurement_requirements" class="field min-h-36" rows="6" /><small class="error">{{ basic.errors.procurement_requirements }}</small></label>
                    <label class="block"><span class="label">备注</span><textarea v-model="basic.remarks" class="field min-h-28" rows="4" /></label>
                </div></div>
            </div>
            <button type="button" class="save" :disabled="basic.processing || !departmentAssigned" @click="save('basic')">保存基本信息</button>
        </section>
        <section v-if="basic.category === '工程类'" id="items" class="panel">
            <h2 class="section-title">采购清单 · 工程类</h2>
            <div class="space-y-5">
                <label class="block"><span class="label">工程名称 <span class="required">*</span></span><input v-model="engineeringForm.project_name" class="field" /><small class="error">{{ engineeringForm.errors.project_name }}</small></label>
                <label class="block"><span class="label">项目建设内容 <span class="required">*</span></span><textarea v-model="engineeringForm.construction_content" class="field min-h-28" placeholder="面积、层数、结构、维修改造的具体内容等" /><small class="error">{{ engineeringForm.errors.construction_content }}</small></label>
                <label class="block"><span class="label">项目概况 <span class="required">*</span></span><textarea v-model="engineeringForm.project_overview" class="field min-h-28" placeholder="项目经费来源项目说明；如为子项目，说明与总项目的关系" /><small class="error">{{ engineeringForm.errors.project_overview }}</small></label>
                <label class="block"><span class="label">项目工期要求 <span class="required">*</span></span><textarea v-model="engineeringForm.schedule_requirements" class="field min-h-24" /><small class="error">{{ engineeringForm.errors.schedule_requirements }}</small></label>
                <label class="block"><span class="label">工程总造价（元） <span class="required">*</span></span><input v-model="engineeringForm.estimated_cost" class="field" type="number" min="0" step="0.01" inputmode="decimal" /><small class="field-hint">金额保留两位小数</small><small v-if="invalidMoney(engineeringForm.estimated_cost)" class="error">请输入非负金额，最多保留两位小数。</small><small class="error">{{ engineeringForm.errors.estimated_cost }}</small></label>
                <div><span class="label">清单附件 <span class="required">*</span></span><input type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg" @change="chooseEngineeringBill" /><small class="error">{{ engineeringForm.errors.bill_file }}</small><p v-if="engineeringBill" class="mt-2 text-sm"><a :href="`/procurement/attachments/${engineeringBill.id}/download`" class="text-[var(--brand-primary)] hover:underline">已上传：{{ engineeringBill.original_name }}</a></p></div>
            </div>
            <button type="button" class="save" :disabled="engineeringForm.processing || !departmentAssigned" @click="save('engineering')">保存工程清单</button>
        </section>
        <section v-else id="items" class="panel">
            <h2 class="section-title">采购清单</h2>
            <div class="mb-4 flex flex-wrap items-center gap-3 text-sm">
                <button type="button" class="action" @click="itemForm.items.push(blankItem())">＋ 添加采购清单</button>
                <select v-model="selectedCatalog" class="field max-w-56"><option value="">从个人物资库选择</option><option v-for="item in catalog" :key="item.id" :value="item.id">{{ item.name }}</option></select>
                <button type="button" class="action" @click="addFromCatalog">抽取</button>
                <label class="action cursor-pointer">导入 Excel/CSV<input type="file" accept=".xlsx,.csv" class="sr-only" @change="importItems" /></label>
            </div>
            <p class="mb-3 text-xs text-[var(--brand-muted)]">导入列顺序：货物名称、技术参数、数量、单价、单位、备注（首行为表头）。</p>
            <p v-if="importError" class="error">{{ importError }}</p>
            <p v-if="itemForm.errors.items" class="error">{{ itemForm.errors.items }}</p>
            <div class="space-y-4">
                <div v-for="(item, index) in itemForm.items" :key="index" class="rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] p-4">
                    <div class="grid gap-3 md:grid-cols-3">
                        <label><span class="label">货物名称 <span class="required">*</span></span><input v-model="item.name" class="field" /></label>
                        <label class="md:col-span-2"><span class="label">技术参数、配置/主要配置</span><input v-model="item.specification" class="field" /></label>
                        <label><span class="label">数量 <span class="required">*</span></span><input v-model.number="item.quantity" class="field" type="number" min="0.001" step="0.001" /><small class="field-hint">支持小数，精确到三位</small><small v-if="invalidQuantity(item.quantity)" class="error">请输入大于零的数量，最多保留三位小数。</small></label>
                        <label><span class="label">单价（元） <span class="required">*</span></span><input v-model.number="item.unit_price" class="field" type="number" min="0" step="0.01" inputmode="decimal" /><small v-if="invalidMoney(item.unit_price)" class="error">请输入非负单价，最多保留两位小数。</small></label>
                        <label><span class="label">单位</span><input v-model="item.unit" class="field" /></label>
                        <label class="md:col-span-2"><span class="label">备注</span><input v-model="item.remarks" class="field" /></label>
                        <div class="flex items-end justify-between"><span>总价：¥{{ money(Number(item.quantity || 0) * Number(item.unit_price || 0)) }}</span><button type="button" class="text-[var(--brand-danger)]" @click="itemForm.items.splice(index, 1)">删除</button></div>
                    </div>
                    <p v-for="field in ['name', 'quantity', 'unit_price']" :key="field" class="error">{{ itemForm.errors[`items.${index}.${field}` as keyof typeof itemForm.errors] }}</p>
                </div>
            </div>
            <p class="mt-5 text-right font-semibold">合计：¥{{ money(total) }}</p>
            <button type="button" class="save" :disabled="itemForm.processing || !departmentAssigned" @click="save('items')">保存采购清单</button>
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
            <button type="button" class="save" :disabled="budgetForm.processing || !departmentAssigned" @click="save('budget')">保存预算指标</button>
        </section>
        <section id="attachments" class="panel">
            <h2 class="section-title">附件</h2>
            <p class="mb-4 text-sm text-[var(--brand-muted)]">按需上传论证资料、办公会纪要、党政联席会议单等。支持 PDF、Word、Excel 和图片，单个文件不超过 20 MB。</p>
            <input type="file" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg" @change="chooseFiles" />
            <p class="error">{{ attachmentForm.errors.files }}</p>
            <ul class="mt-5 space-y-2"><li v-for="file in attachments" :key="file.id"><a class="text-[var(--brand-primary)] hover:underline" :href="`/procurement/attachments/${file.id}/download`">{{ file.original_name }}</a></li></ul>
            <button type="button" class="save" :disabled="attachmentForm.processing || !departmentAssigned" @click="save('attachments')">保存附件</button>
        </section>
        <div v-if="Object.keys(page.props.errors ?? {}).length" class="mt-6 rounded border border-[var(--brand-danger)] p-4 text-sm text-[var(--brand-danger)]"><p v-for="(message, key) in page.props.errors" :key="key">{{ message }}</p></div>
        <footer class="form-action-bar flex flex-wrap items-center justify-between gap-3 rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-3 shadow-[var(--brand-shadow)] md:px-6">
            <div class="text-xs text-[var(--brand-muted)]"><p>当前模块：{{ tabs.find((entry) => entry.key === tab)?.label }} · {{ application ? '各模块分别保存' : '先保存任一模块以生成草稿' }}</p><p v-if="total < threshold" class="text-[var(--brand-warning)]">统一招标金额门槛：¥{{ money(threshold) }}</p></div>
            <div class="flex flex-wrap items-center gap-2"><Link href="/procurement/applications" class="action">返回申请列表</Link><button type="button" class="action" :disabled="activeSaving || !departmentAssigned" @click="saveCurrentSection">保存当前模块（草稿）</button><button type="button" class="save mt-0" :disabled="activeSaving || !departmentAssigned || !application" @click="submit">提交审核</button></div>
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
