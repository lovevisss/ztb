<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ElDialog } from 'element-plus';
import { ref } from 'vue';

type Supplier = {
    id: number;
    name: string;
    contact_name: string;
    contact_phone: string;
    department_name: string;
    is_active: boolean;
    can_edit: boolean;
};

const props = defineProps<{
    suppliers: { data: Supplier[]; current_page: number; last_page: number; total: number };
    search: string;
    canCreate: boolean;
    canChangeStatus: boolean;
}>();
const page = usePage<{ flash?: { status?: string } }>();
const search = ref(props.search);
const editorOpen = ref(false);
const editingId = ref<number | null>(null);
const form = useForm({ name: '', contact_name: '', contact_phone: '' });
const statusForm = useForm({ is_active: true });
const statusTarget = ref<Supplier | null>(null);

function load(targetPage = 1) {
    router.get('/procurement/suppliers', { search: search.value.trim(), page: targetPage }, { preserveState: true });
}

function openEditor(supplier?: Supplier) {
    editingId.value = supplier?.id ?? null;
    form.name = supplier?.name ?? '';
    form.contact_name = supplier?.contact_name ?? '';
    form.contact_phone = supplier?.contact_phone ?? '';
    form.clearErrors();
    editorOpen.value = true;
}

function save() {
    if (form.processing) return;
    const onSuccess = () => { editorOpen.value = false; };
    if (editingId.value === null) {
        form.post('/procurement/suppliers', { onSuccess });
    } else {
        form.put(`/procurement/suppliers/${editingId.value}`, { preserveScroll: true, onSuccess });
    }
}

function toggleStatus() {
    const supplier = statusTarget.value;
    if (!supplier || statusForm.processing) return;
    statusForm.is_active = !supplier.is_active;
    statusForm.put(`/procurement/suppliers/${supplier.id}/status`, {
        preserveScroll: true,
        onSuccess: () => { statusTarget.value = null; },
    });
}
</script>

<template>
    <Head title="供应商库" />
    <main class="mx-auto w-full max-w-6xl px-4 py-8 sm:px-8">
        <header class="flex flex-wrap items-end justify-between gap-4 border-b border-[var(--brand-border)] pb-6">
            <div>
                <h1 class="text-[28px] leading-[1.3] font-semibold text-[var(--brand-title)]">供应商库</h1>
                <p class="mt-2 text-sm text-[var(--brand-muted)]">供应商档案全校共享，部门自行采购登记时可选择已启用的供应商。</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <Link v-if="canChangeStatus" href="/procurement/supplier-deals" class="inline-flex min-h-10 items-center rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] px-4 text-sm font-medium text-[var(--brand-primary)] hover:bg-[var(--brand-primary-light)]">按采购内容查供应商</Link>
                <el-button v-if="canCreate" type="primary" @click="openEditor()">新增供应商</el-button>
            </div>
        </header>
        <p v-if="page.props.flash?.status" role="status" class="mt-4 text-sm text-[var(--brand-success)]">{{ page.props.flash.status }}</p>

        <section class="mt-6 min-w-0 overflow-hidden rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] shadow-[var(--brand-shadow)]">
            <form class="flex flex-wrap items-end gap-3 p-6" @submit.prevent="load()">
                <label class="min-w-0 flex-1 text-sm text-[var(--brand-body)] sm:max-w-xs">
                    供应商名称
                    <el-input v-model="search" class="mt-2" placeholder="输入供应商名称" maxlength="200" />
                </label>
                <el-button native-type="submit">查询</el-button>
            </form>
            <div class="overflow-x-auto" role="region" aria-label="供应商列表" tabindex="0">
                <table class="w-full min-w-[720px] table-fixed text-left text-sm">
                    <thead class="bg-[var(--brand-soft)] text-xs text-[var(--brand-muted)]">
                        <tr>
                            <th scope="col" class="w-[25%] px-6 py-3">供应商</th>
                            <th scope="col" class="w-[14%] px-3 py-3">联系人</th>
                            <th scope="col" class="w-[17%] px-3 py-3">联系电话</th>
                            <th scope="col" class="w-[20%] px-3 py-3">创建部门</th>
                            <th scope="col" class="w-[10%] px-3 py-3">状态</th>
                            <th scope="col" class="w-[14%] px-3 py-3">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--brand-border)] text-[var(--brand-body)]">
                        <tr v-for="supplier in suppliers.data" :key="supplier.id" class="hover:bg-[var(--brand-primary-light)]">
                            <th scope="row" class="break-words px-6 py-4 font-medium text-[var(--brand-title)]">
                                <Link v-if="canChangeStatus" :href="`/procurement/supplier-deals/${supplier.id}`" class="text-[var(--brand-primary)] hover:underline">{{ supplier.name }}</Link>
                                <template v-else>{{ supplier.name }}</template>
                            </th>
                            <td class="break-words px-3 py-4">{{ supplier.contact_name }}</td>
                            <td class="break-words px-3 py-4 tabular-nums">{{ supplier.contact_phone }}</td>
                            <td class="break-words px-3 py-4">{{ supplier.department_name }}</td>
                            <td class="px-3 py-4">
                                <span class="inline-flex rounded-lg bg-[var(--brand-soft)] px-2 py-1 text-xs whitespace-nowrap" :class="supplier.is_active ? 'text-[var(--brand-success)]' : 'text-[var(--brand-muted)]'">{{ supplier.is_active ? '已启用' : '已停用' }}</span>
                            </td>
                            <td class="px-3 py-4">
                                <div v-if="supplier.can_edit || canChangeStatus" class="flex flex-wrap gap-2">
                                    <button v-if="supplier.can_edit" type="button" class="text-[var(--brand-primary)] hover:underline" @click="openEditor(supplier)">编辑</button>
                                    <button v-if="canChangeStatus" type="button" :class="supplier.is_active ? 'text-[var(--brand-danger)]' : 'text-[var(--brand-primary)]'" class="hover:underline" @click="statusTarget = supplier">{{ supplier.is_active ? '停用' : '启用' }}</button>
                                </div>
                                <span v-else aria-hidden="true">—</span>
                            </td>
                        </tr>
                        <tr v-if="!suppliers.data.length">
                            <td colspan="6" class="px-6 py-10 text-center text-[var(--brand-muted)]">{{ search ? '没有找到匹配的供应商。' : '暂无供应商档案。' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <footer v-if="suppliers.total > 0" class="flex flex-wrap items-center justify-between gap-3 border-t border-[var(--brand-border)] px-6 py-4 text-sm text-[var(--brand-muted)]">
                <span>共 {{ suppliers.total }} 家供应商</span>
                <div v-if="suppliers.last_page > 1" class="flex items-center gap-2">
                    <el-button :disabled="suppliers.current_page === 1" @click="load(suppliers.current_page - 1)">上一页</el-button>
                    <span>{{ suppliers.current_page }} / {{ suppliers.last_page }}</span>
                    <el-button :disabled="suppliers.current_page === suppliers.last_page" @click="load(suppliers.current_page + 1)">下一页</el-button>
                </div>
            </footer>
        </section>

        <el-dialog v-model="editorOpen" :title="editingId === null ? '新增供应商' : '编辑供应商'" width="min(480px, calc(100vw - 32px))">
            <form id="supplier-editor" class="grid gap-4" @submit.prevent="save">
                <label v-for="field in [{ key: 'name', label: '供应商名称', max: 200 }, { key: 'contact_name', label: '联系人', max: 100 }, { key: 'contact_phone', label: '联系电话', max: 30 }] as const" :key="field.key" class="text-sm text-[var(--brand-body)]">
                    {{ field.label }} <span aria-hidden="true" class="text-[var(--brand-danger)]">*</span>
                    <el-input v-model="form[field.key]" class="mt-2" :maxlength="field.max" :type="field.key === 'contact_phone' ? 'tel' : 'text'" required :aria-invalid="Boolean(form.errors[field.key])" />
                    <span v-if="form.errors[field.key]" class="mt-1 block text-xs text-[var(--brand-danger)]">{{ form.errors[field.key] }}</span>
                </label>
            </form>
            <template #footer>
                <el-button :disabled="form.processing" @click="editorOpen = false">取消</el-button>
                <el-button type="primary" native-type="submit" form="supplier-editor" :loading="form.processing">保存</el-button>
            </template>
        </el-dialog>
        <el-dialog :model-value="statusTarget !== null" :title="statusTarget?.is_active ? '停用供应商' : '启用供应商'" width="min(480px, calc(100vw - 32px))" @update:model-value="statusTarget = null">
            <p class="break-words text-sm text-[var(--brand-body)]">确认{{ statusTarget?.is_active ? '停用' : '启用' }}「{{ statusTarget?.name }}」？</p>
            <template #footer>
                <el-button :disabled="statusForm.processing" @click="statusTarget = null">取消</el-button>
                <el-button :type="statusTarget?.is_active ? 'danger' : 'primary'" :loading="statusForm.processing" @click="toggleStatus">确认</el-button>
            </template>
        </el-dialog>
    </main>
</template>
