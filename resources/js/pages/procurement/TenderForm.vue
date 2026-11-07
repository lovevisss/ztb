<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';

type Item = { name: string; specification: string; quantity: number; unit_price: number; unit: string; remarks: string };
type SavedItem = { name: string; specification: string | null; quantity: number; unit_price_cents: number; unit: string | null; remarks: string | null };
type Indicator = { id: number; code: string | null; name: string; director: string | null; available_cents: number | null };
type CatalogItem = { id: number; name: string; specification: string | null; unit: string | null; unit_price_cents: number; remarks: string | null };
type Application = { id: number; project_number: string | null; title: string | null; category: string | null; contact_phone: string | null; purchase_method: string | null; is_special_purchase: boolean; is_non_expenditure: boolean; has_usage_department: boolean; usage_department_id: number | null; procurement_requirements: string | null; remarks: string | null; budget_basis_note: string | null; budget_cents: number | null };
type Attachment = { id: number; original_name: string; size: number };
type Allocation = { budget_indicator_id: number; amount_cents: number };

const props = defineProps<{
    application: Application | null;
    attachments: Attachment[];
    departmentAssigned: boolean;
    threshold: number;
    applicantName: string;
    departmentName: string | null;
    departments: { id: number; name: string }[];
    items: SavedItem[];
    allocations: Allocation[];
    indicators: Indicator[];
    catalog: CatalogItem[];
    canSyncBudgets: boolean;
}>();
const page = usePage();

type Tab = 'basic' | 'items' | 'budget' | 'attachments';
const initialTab = typeof window === 'undefined' ? 'basic' : new URLSearchParams(window.location.search).get('tab');
const tab = ref<Tab>(['basic', 'items', 'budget', 'attachments'].includes(initialTab ?? '') ? initialTab as Tab : 'basic');
const tabs: { key: Tab; label: string }[] = [
    { key: 'basic', label: '基本信息' }, { key: 'items', label: '采购清单' },
    { key: 'budget', label: '项目预算指标' }, { key: 'attachments', label: '附件' },
];
const basic = useForm({
    section: 'basic', title: props.application?.title ?? '', category: props.application?.category ?? '',
    contact_phone: props.application?.contact_phone ?? '', purchase_method: props.application?.purchase_method ?? '公开招标',
    is_special_purchase: props.application?.is_special_purchase ?? false,
    is_non_expenditure: props.application?.is_non_expenditure ?? false,
    has_usage_department: props.application?.has_usage_department ?? false,
    usage_department_id: props.application?.usage_department_id ?? null as number | null,
    procurement_requirements: props.application?.procurement_requirements ?? '', remarks: props.application?.remarks ?? '',
});
const itemForm = useForm<{ section: string; items: Item[] }>({
    section: 'items', items: props.items.map((item) => ({
        name: item.name, specification: item.specification ?? '', quantity: Number(item.quantity),
        unit_price: item.unit_price_cents / 100, unit: item.unit ?? '', remarks: item.remarks ?? '',
    })),
});
const budgetForm = useForm({
    section: 'budget', budget_basis_note: props.application?.budget_basis_note ?? '',
    allocations: props.allocations.map((a) => ({ budget_indicator_id: a.budget_indicator_id, amount: a.amount_cents / 100 })),
});
const attachmentForm = useForm<{ section: string; files: File[] }>({ section: 'attachments', files: [] });
const catalogForm = useForm({ name: '', specification: '', unit: '', unit_price: 0, remarks: '' });
const editingCatalogId = ref<number | null>(null);
const selectedCatalog = ref<number | ''>('');
const total = computed(() => itemForm.items.reduce((sum, item) => sum + Math.round(Number(item.unit_price || 0) * 100 * Number(item.quantity || 0)), 0) / 100);
const allocated = computed(() => budgetForm.allocations.reduce((sum, a) => sum + Number(a.amount || 0), 0));
const money = (value: number) => value.toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function blankItem(): Item { return { name: '', specification: '', quantity: 1, unit_price: 0, unit: '', remarks: '' }; }
function save(section: Tab) {
    const form = section === 'basic' ? basic : section === 'items' ? itemForm : section === 'budget' ? budgetForm : attachmentForm;
    const options = { preserveScroll: true, onSuccess: () => { if (section === 'attachments') attachmentForm.files = []; } };
    if (props.application) form.post(`/procurement/tenders/${props.application.id}/sections`, options);
    else form.post('/procurement/tenders/sections', options);
}
function submit() {
    if (!props.application) { save(tab.value); return; }
    router.post(`/procurement/tenders/${props.application.id}/submit`, {}, { preserveScroll: true });
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
    <main class="mx-auto w-full max-w-6xl px-5 py-8 md:px-8">
        <Link href="/procurement/applications" class="text-sm text-[var(--brand-primary)] hover:underline">← 返回申请列表</Link>
        <header class="my-8 text-center">
            <h1 class="text-2xl font-semibold text-[var(--brand-title)]">采购申请单</h1>
            <p class="mt-2 text-sm text-[var(--brand-muted)]">{{ application?.project_number ?? '首次保存后自动生成项目编号' }}</p>
        </header>
        <div v-if="!departmentAssigned" class="mb-5 rounded border border-[var(--brand-warning)] p-4 text-[var(--brand-warning)]">当前账号尚未关联部门，请联系管理员。</div>
        <nav class="mb-6 flex flex-wrap border-b border-[var(--brand-border)]" aria-label="申请内容">
            <button v-for="entry in tabs" :key="entry.key" type="button" :aria-current="tab === entry.key ? 'page' : undefined" class="px-5 py-3 text-sm" :class="tab === entry.key ? 'border-b-2 border-[var(--brand-primary)] font-semibold text-[var(--brand-primary)]' : 'text-[var(--brand-muted)]'" @click="tab = entry.key">{{ entry.label }}</button>
        </nav>
        <section v-show="tab === 'basic'" class="panel">
            <h2 class="section-title">基本信息 <span class="text-xs font-normal">（*号为必填内容）</span></h2>
            <div class="grid gap-5 sm:grid-cols-2">
                <label><span class="label">项目编号</span><input class="field bg-[var(--brand-page)]" :value="application?.project_number ?? '保存后自动生成'" disabled /></label>
                <label><span class="label">*申报项目</span><input v-model="basic.title" class="field" /><small class="error">{{ basic.errors.title }}</small></label>
                <label><span class="label">经办人</span><input class="field bg-[var(--brand-page)]" :value="applicantName" disabled /></label>
                <label><span class="label">*经办人电话</span><input v-model="basic.contact_phone" class="field" /><small class="error">{{ basic.errors.contact_phone }}</small></label>
                <label><span class="label">经办人所在部门</span><input class="field bg-[var(--brand-page)]" :value="departmentName ?? '未关联部门'" disabled /></label>
                <label><span class="label">项目预算（元）</span><input class="field bg-[var(--brand-page)]" :value="money(total)" disabled /></label>
                <label><span class="label">*归属</span><select v-model="basic.category" class="field"><option value="">请选择</option><option>工程类</option><option>货物类</option><option>服务类</option></select><small class="error">{{ basic.errors.category }}</small></label>
                <label><span class="label">是否特殊采购</span><select v-model="basic.is_special_purchase" class="field"><option :value="false">否</option><option :value="true">是</option></select></label>
                <label><span class="label">拟采购方式</span><select v-model="basic.purchase_method" class="field"><option>公开招标</option><option>邀请招标</option><option>竞争性磋商</option><option>竞争性谈判</option><option>询价</option><option>单一来源</option></select></label>
                <label><span class="label">*是否有使用部门</span><select v-model="basic.has_usage_department" class="field"><option :value="false">否</option><option :value="true">是</option></select></label>
                <label v-if="basic.has_usage_department"><span class="label">*使用部门</span><select v-model="basic.usage_department_id" class="field"><option :value="null">请选择</option><option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option></select><small class="error">{{ basic.errors.usage_department_id }}</small></label>
                <label><span class="label">*是否为非支出项目</span><select v-model="basic.is_non_expenditure" class="field"><option :value="false">否</option><option :value="true">是</option></select></label>
                <label class="sm:col-span-2"><span class="label">*采购要求（评标要求、供货、质保等）</span><textarea v-model="basic.procurement_requirements" class="field min-h-28" /><small class="error">{{ basic.errors.procurement_requirements }}</small></label>
                <label class="sm:col-span-2"><span class="label">备注</span><textarea v-model="basic.remarks" class="field min-h-20" /></label>
            </div>
            <button type="button" class="save" :disabled="basic.processing || !departmentAssigned" @click="save('basic')">保存基本信息</button>
        </section>
        <section v-show="tab === 'items'" class="panel">
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
                <div v-for="(item, index) in itemForm.items" :key="index" class="rounded border border-[var(--brand-border)] p-4">
                    <div class="grid gap-3 md:grid-cols-3">
                        <label><span class="label">货物名称 *</span><input v-model="item.name" class="field" /></label>
                        <label class="md:col-span-2"><span class="label">技术参数、配置/主要配置</span><input v-model="item.specification" class="field" /></label>
                        <label><span class="label">数量 *</span><input v-model.number="item.quantity" class="field" type="number" min="0.001" step="0.001" /></label>
                        <label><span class="label">单价（元）*</span><input v-model.number="item.unit_price" class="field" type="number" min="0" step="0.01" /></label>
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
        <section v-show="tab === 'budget'" class="panel">
            <h2 class="section-title">项目预算指标</h2>
            <p class="mb-4 text-sm">采购清单合计：¥{{ money(total) }}　预算指标使用金额合计：¥{{ money(allocated) }}</p>
            <button v-if="canSyncBudgets" type="button" class="action mb-4" @click="router.post('/procurement/budgets/sync')">手动同步预算指标</button>
            <button type="button" class="action mb-4 ml-3" @click="budgetForm.allocations.push({ budget_indicator_id: 0, amount: 0 })">＋ 添加经费来源</button>
            <p class="error">{{ budgetForm.errors.allocations }}</p>
            <div v-for="(allocation, index) in budgetForm.allocations" :key="index" class="mb-4 grid items-end gap-3 rounded border border-[var(--brand-border)] p-4 md:grid-cols-[1fr_11rem_auto]">
                <label><span class="label">预算编码 / 预算项目名称 / 负责人</span><select v-model.number="allocation.budget_indicator_id" class="field"><option :value="0">请选择预算指标</option><option v-for="indicator in indicators" :key="indicator.id" :value="indicator.id">{{ indicator.code }} · {{ indicator.name }} · {{ indicator.director }}</option></select></label>
                <label><span class="label">使用金额（元）</span><input v-model.number="allocation.amount" class="field" type="number" min="0.01" step="0.01" /></label>
                <button type="button" class="text-[var(--brand-danger)]" @click="budgetForm.allocations.splice(index, 1)">删除</button>
            </div>
            <label><span class="label">预算测算说明</span><textarea v-model="budgetForm.budget_basis_note" class="field min-h-24" /></label>
            <button type="button" class="save" :disabled="budgetForm.processing || !departmentAssigned" @click="save('budget')">保存预算指标</button>
        </section>
        <section v-show="tab === 'attachments'" class="panel">
            <h2 class="section-title">附件</h2>
            <p class="mb-4 text-sm text-[var(--brand-muted)]">按需上传论证资料、办公会纪要、党政联席会议单等。支持 PDF、Word、Excel 和图片，单个文件不超过 20 MB。</p>
            <input type="file" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg" @change="chooseFiles" />
            <p class="error">{{ attachmentForm.errors.files }}</p>
            <ul class="mt-5 space-y-2"><li v-for="file in attachments" :key="file.id"><a class="text-[var(--brand-primary)] hover:underline" :href="`/procurement/attachments/${file.id}/download`">{{ file.original_name }}</a></li></ul>
            <button type="button" class="save" :disabled="attachmentForm.processing || !departmentAssigned" @click="save('attachments')">保存附件</button>
        </section>
        <div v-if="Object.keys(page.props.errors ?? {}).length" class="mt-6 rounded border border-[var(--brand-danger)] p-4 text-sm text-[var(--brand-danger)]"><p v-for="(message, key) in page.props.errors" :key="key">{{ message }}</p></div>
        <div class="mt-8 flex flex-wrap items-center justify-center gap-4"><button type="button" class="save mt-0" :disabled="!departmentAssigned" @click="submit">提交审核</button><Link href="/procurement/applications" class="action">关闭</Link><span v-if="total < threshold" class="text-xs text-[var(--brand-warning)]">统一招标金额门槛：¥{{ money(threshold) }}</span></div>
    </main>
</template>

<style scoped>
.panel { border: 1px solid var(--brand-border); background: var(--brand-card); padding: 1.5rem; border-radius: var(--brand-radius-md); }
.section-title { border-bottom: 1px solid var(--brand-primary); padding-bottom: .7rem; margin-bottom: 1.25rem; color: var(--brand-title); font-weight: 700; }
.label { display: block; font-size: .8rem; color: var(--brand-body); margin-bottom: .4rem; }
.field { width: 100%; min-height: 2.5rem; border: 1px solid var(--brand-border); border-radius: .25rem; padding: .5rem .65rem; background-color: var(--brand-card); }
.field:focus { outline: 2px solid var(--brand-primary); outline-offset: 1px; }
.field:disabled { background: var(--brand-page); }
.error { color: var(--brand-danger); font-size: .75rem; }
.save { display: block; margin: 1.5rem auto 0; background: var(--brand-primary); color: white; border-radius: .25rem; padding: .65rem 1.75rem; font-size: .875rem; }
.save:disabled { opacity: .5; }
.action { display: inline-block; border: 1px solid var(--brand-primary); border-radius: .25rem; padding: .45rem .8rem; color: var(--brand-primary); font-size: .875rem; }
</style>
