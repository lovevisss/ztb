<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

type Department = { external_code: string; name: string; parent_id: number | null; is_active: boolean };
type Position = { id: number; code: string; name: string };
type PositionRole = { position_code: string; role_code: string };
type Role = { code: string; name: string };
type Run = { id: number; departments_count: number; positions_count: number; people_count: number; deactivated_count: number; complete_snapshot: boolean; created_at: string };
type Feed = {
    complete_snapshot: boolean;
    departments: { code: string; name: string; parent_code?: string | null; active?: boolean }[];
    positions: { code: string; name: string }[];
    people: { account: string; name: string; email?: string | null; department_code: string; position_code: string; active: boolean }[];
};
const props = defineProps<{
    departments: Department[]; positions: Position[]; positionRoles: PositionRole[];
    roles: Role[]; runs: Run[]; flash?: { status?: string };
}>();
const payload = ref('');
const importError = ref('');
const importing = ref(false);
const mapping = useForm({ role_codes: [] as string[] });
const selections = reactive<Record<string, string[]>>({});
for (const position of props.positions) {
    selections[position.code] = props.positionRoles.filter((item) => item.position_code === position.code).map((item) => item.role_code);
}

async function loadFile(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (file) payload.value = await file.text();
}

function isObject(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}
function isFeed(value: unknown): value is Feed {
    if (!isObject(value) || typeof value.complete_snapshot !== 'boolean'
        || !Array.isArray(value.departments) || !Array.isArray(value.positions) || !Array.isArray(value.people)) return false;
    return value.departments.every((item: unknown) => isObject(item) && typeof item.code === 'string' && typeof item.name === 'string'
        && (item.parent_code == null || typeof item.parent_code === 'string') && (item.active == null || typeof item.active === 'boolean'))
        && value.positions.every((item: unknown) => isObject(item) && typeof item.code === 'string' && typeof item.name === 'string')
        && value.people.every((item: unknown) => isObject(item) && typeof item.account === 'string' && typeof item.name === 'string'
            && (item.email == null || typeof item.email === 'string') && typeof item.department_code === 'string'
            && typeof item.position_code === 'string' && typeof item.active === 'boolean');
}

function importSnapshot() {
    importError.value = '';
    let data: Feed;
    try {
        const parsed: unknown = JSON.parse(payload.value);
        if (!isFeed(parsed)) throw new Error('对象格式无效');
        data = parsed;
    } catch {
        importError.value = '请选择或粘贴有效的 JSON 对象。';
        return;
    }
    importing.value = true;
    router.post('/admin/organization/sync', data, {
        onError: (errors) => { importError.value = Object.values(errors).join('；'); },
        onFinish: () => { importing.value = false; },
    });
}

function saveRoles(code: string) {
    mapping.role_codes = selections[code] ?? [];
    mapping.put(`/admin/organization/positions/${encodeURIComponent(code)}/roles`);
}
</script>

<template>
    <Head title="组织同步" />
    <main class="mx-auto max-w-6xl px-5 py-8 md:px-10 md:py-12">
        <header class="border-b border-slate-200 pb-7">
            <p class="text-xs font-semibold tracking-[0.25em] text-emerald-700">ORGANIZATION / 基础数据</p>
            <h1 class="mt-2 font-serif text-3xl font-semibold text-slate-900">学校组织与岗位</h1>
            <p class="mt-3 text-sm text-slate-500">导入部门、岗位和人员快照，并维护学校岗位与采购审批角色的映射。</p>
            <Link href="/admin/school-identities" class="mt-4 inline-block text-sm text-emerald-800 underline">查看校内账号 →</Link>
        </header>
        <p v-if="flash?.status" class="mt-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ flash.status }}</p>

        <div class="mt-7 grid gap-7 lg:grid-cols-[minmax(0,1fr)_330px]">
            <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="font-serif text-xl text-slate-900">导入组织快照</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">上传 JSON 文件或粘贴内容。完整快照会停用此前同步过、但本次缺失的人员；增量快照仅更新出现的记录。</p>
                <input type="file" accept=".json,application/json" class="mt-5 block w-full text-sm" @change="loadFile" />
                <el-input v-model="payload" class="mt-4" type="textarea" :rows="12" placeholder="{ &quot;complete_snapshot&quot;: false, &quot;departments&quot;: [...], &quot;positions&quot;: [...], &quot;people&quot;: [...] }" />
                <p v-if="importError" class="mt-3 text-sm text-red-700">{{ importError }}</p>
                <el-button class="mt-5" type="primary" :loading="importing" @click="importSnapshot">校验并导入</el-button>
            </section>
            <aside class="space-y-6">
                <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="font-serif text-xl text-slate-900">同步记录</h2>
                    <ul class="mt-4 space-y-4 text-sm text-slate-600"><li v-for="run in runs" :key="run.id" class="border-b border-slate-100 pb-3"><strong>{{ run.created_at.slice(0, 16).replace('T', ' ') }}</strong><p class="mt-1">{{ run.complete_snapshot ? '完整' : '增量' }} · 部门 {{ run.departments_count }} · 岗位 {{ run.positions_count }} · 人员 {{ run.people_count }}</p><p v-if="run.deactivated_count" class="text-amber-700">停用 {{ run.deactivated_count }} 人</p></li></ul>
                    <p v-if="!runs.length" class="mt-4 text-sm text-slate-400">尚未导入</p>
                </section>
                <section class="rounded-lg border border-amber-200 bg-amber-50 p-6 text-sm leading-7 text-amber-950">正式学校接口的字段与鉴权方式确认后，可将接口数据转换为相同快照结构；首次完整同步前请核对人员唯一账号和离职规则。</section>
            </aside>
        </div>

        <section class="mt-7 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="font-serif text-xl text-slate-900">岗位与业务角色</h2>
            <div class="mt-5 grid gap-5 md:grid-cols-2"><div v-for="position in positions" :key="position.id" class="rounded-md border border-slate-200 p-4"><p class="font-medium text-slate-900">{{ position.name }} <span class="font-mono text-xs text-slate-400">{{ position.code }}</span></p><el-checkbox-group v-model="selections[position.code]" class="mt-3 flex flex-wrap gap-x-4"><el-checkbox v-for="role in roles" :key="role.code" :label="role.code" :value="role.code">{{ role.name }}</el-checkbox></el-checkbox-group><el-button class="mt-4" size="small" :loading="mapping.processing" @click="saveRoles(position.code)">保存映射</el-button></div></div>
            <p v-if="!positions.length" class="mt-4 text-sm text-slate-400">导入岗位后可设置审批角色。</p>
        </section>
        <section class="mt-7 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="font-serif text-xl text-slate-900">已同步部门</h2>
            <div class="mt-5 overflow-x-auto"><table class="w-full min-w-[560px] text-left text-sm"><thead class="border-b border-slate-200 text-xs text-slate-500"><tr><th class="py-3">代码</th><th>名称</th><th>上级部门 ID</th><th>状态</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="department in departments" :key="department.external_code"><td class="py-3 font-mono">{{ department.external_code }}</td><td>{{ department.name }}</td><td>{{ department.parent_id ?? '—' }}</td><td>{{ department.is_active ? '启用' : '停用' }}</td></tr></tbody></table></div>
        </section>
    </main>
</template>
