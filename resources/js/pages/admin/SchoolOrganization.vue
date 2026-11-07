<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

type Department = {
    external_code: string;
    name: string;
    parent_id: number | null;
    is_active: boolean;
};
type Position = { id: number; code: string; name: string };
type PositionRole = { position_code: string; role_code: string };
type Role = { code: string; name: string };
type Run = {
    id: number;
    departments_count: number;
    positions_count: number;
    people_count: number;
    deactivated_count: number;
    complete_snapshot: boolean;
    created_at: string;
};
type Feed = {
    complete_snapshot: boolean;
    departments: {
        code: string;
        name: string;
        parent_code?: string | null;
        active?: boolean;
    }[];
    positions: { code: string; name: string }[];
    people: {
        account: string;
        name: string;
        email?: string | null;
        department_code: string;
        position_code: string;
        active: boolean;
    }[];
};
const props = defineProps<{
    departments: Department[];
    positions: Position[];
    positionRoles: PositionRole[];
    roles: Role[];
    runs: Run[];
    flash?: { status?: string };
}>();
const payload = ref('');
const importError = ref('');
const importing = ref(false);
const mapping = useForm({ role_codes: [] as string[] });
const selections = reactive<Record<string, string[]>>({});
for (const position of props.positions) {
    selections[position.code] = props.positionRoles
        .filter((item) => item.position_code === position.code)
        .map((item) => item.role_code);
}

async function loadFile(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (file) payload.value = await file.text();
}

function isObject(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}
function isFeed(value: unknown): value is Feed {
    if (
        !isObject(value) ||
        typeof value.complete_snapshot !== 'boolean' ||
        !Array.isArray(value.departments) ||
        !Array.isArray(value.positions) ||
        !Array.isArray(value.people)
    )
        return false;
    return (
        value.departments.every(
            (item: unknown) =>
                isObject(item) &&
                typeof item.code === 'string' &&
                typeof item.name === 'string' &&
                (item.parent_code == null ||
                    typeof item.parent_code === 'string') &&
                (item.active == null || typeof item.active === 'boolean'),
        ) &&
        value.positions.every(
            (item: unknown) =>
                isObject(item) &&
                typeof item.code === 'string' &&
                typeof item.name === 'string',
        ) &&
        value.people.every(
            (item: unknown) =>
                isObject(item) &&
                typeof item.account === 'string' &&
                typeof item.name === 'string' &&
                (item.email == null || typeof item.email === 'string') &&
                typeof item.department_code === 'string' &&
                typeof item.position_code === 'string' &&
                typeof item.active === 'boolean',
        )
    );
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
        onError: (errors) => {
            importError.value = Object.values(errors).join('；');
        },
        onFinish: () => {
            importing.value = false;
        },
    });
}

function saveRoles(code: string) {
    mapping.role_codes = selections[code] ?? [];
    mapping.put(
        `/admin/organization/positions/${encodeURIComponent(code)}/roles`,
    );
}
</script>

<template>
    <Head title="组织同步" />
    <main
        class="mx-auto max-w-6xl px-[var(--brand-space-24)] py-[var(--brand-space-32)] md:px-[var(--brand-space-32)] md:py-[var(--brand-space-32)]"
    >
        <header
            class="border-b border-[var(--brand-border)] pb-[var(--brand-space-32)]"
        >
            <p
                class="text-xs font-semibold tracking-[0.25em] text-[var(--brand-primary)]"
            >
                ORGANIZATION / 基础数据
            </p>
            <h1
                class="mt-[var(--brand-space-8)] font-sans text-3xl font-semibold text-[var(--brand-title)]"
            >
                学校组织与岗位
            </h1>
            <p
                class="mt-[var(--brand-space-16)] text-sm text-[var(--brand-muted)]"
            >
                导入部门、岗位和人员快照，并维护学校岗位与采购审批角色的映射。
            </p>
            <Link
                href="/admin/school-identities"
                class="mt-[var(--brand-space-16)] inline-block text-sm text-[var(--brand-primary)] underline"
                >查看校内账号 →</Link
            >
        </header>
        <p
            v-if="flash?.status"
            class="mt-[var(--brand-space-24)] rounded-[var(--brand-radius-md)] border border-[var(--brand-primary)] bg-[var(--brand-primary-light)] px-[var(--brand-space-16)] py-[var(--brand-space-16)] text-sm text-[var(--brand-primary)]"
        >
            {{ flash.status }}
        </p>

        <div
            class="mt-[var(--brand-space-32)] grid gap-[var(--brand-space-32)] lg:grid-cols-[minmax(0,1fr)_330px]"
        >
            <section
                class="rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)] shadow-sm"
            >
                <h2 class="font-sans text-xl text-[var(--brand-title)]">
                    导入组织快照
                </h2>
                <p
                    class="mt-[var(--brand-space-8)] text-sm leading-6 text-[var(--brand-muted)]"
                >
                    上传 JSON
                    文件或粘贴内容。完整快照会停用此前同步过、但本次缺失的人员；增量快照仅更新出现的记录。
                </p>
                <input
                    type="file"
                    accept=".json,application/json"
                    class="mt-[var(--brand-space-24)] block w-full text-sm"
                    @change="loadFile"
                />
                <el-input
                    v-model="payload"
                    class="mt-[var(--brand-space-16)]"
                    type="textarea"
                    :rows="12"
                    placeholder='{ "complete_snapshot": false, "departments": [...], "positions": [...], "people": [...] }'
                />
                <p
                    v-if="importError"
                    class="mt-[var(--brand-space-16)] text-sm text-[var(--brand-danger)]"
                >
                    {{ importError }}
                </p>
                <el-button
                    class="mt-[var(--brand-space-24)]"
                    type="primary"
                    :loading="importing"
                    @click="importSnapshot"
                    >校验并导入</el-button
                >
            </section>
            <aside class="space-y-[var(--brand-space-24)]">
                <section
                    class="rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)] shadow-sm"
                >
                    <h2 class="font-sans text-xl text-[var(--brand-title)]">
                        同步记录
                    </h2>
                    <ul
                        class="mt-[var(--brand-space-16)] space-y-[var(--brand-space-16)] text-sm text-[var(--brand-body)]"
                    >
                        <li
                            v-for="run in runs"
                            :key="run.id"
                            class="border-b border-[var(--brand-border)] pb-[var(--brand-space-16)]"
                        >
                            <strong>{{
                                run.created_at.slice(0, 16).replace('T', ' ')
                            }}</strong>
                            <p class="mt-[var(--brand-space-8)]">
                                {{ run.complete_snapshot ? '完整' : '增量' }} ·
                                部门 {{ run.departments_count }} · 岗位
                                {{ run.positions_count }} · 人员
                                {{ run.people_count }}
                            </p>
                            <p
                                v-if="run.deactivated_count"
                                class="text-[var(--brand-warning)]"
                            >
                                停用 {{ run.deactivated_count }} 人
                            </p>
                        </li>
                    </ul>
                    <p
                        v-if="!runs.length"
                        class="mt-[var(--brand-space-16)] text-sm text-[var(--brand-muted)]"
                    >
                        尚未导入
                    </p>
                </section>
                <section
                    class="rounded-[var(--brand-radius-md)] border border-[var(--brand-warning)] bg-[var(--brand-page)] p-[var(--brand-space-24)] text-sm leading-7 text-[var(--brand-warning)]"
                >
                    正式学校接口的字段与鉴权方式确认后，可将接口数据转换为相同快照结构；首次完整同步前请核对人员唯一账号和离职规则。
                </section>
            </aside>
        </div>

        <section
            class="mt-[var(--brand-space-32)] rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)] shadow-sm"
        >
            <h2 class="font-sans text-xl text-[var(--brand-title)]">
                岗位与业务角色
            </h2>
            <div
                class="mt-[var(--brand-space-24)] grid gap-[var(--brand-space-24)] md:grid-cols-2"
            >
                <div
                    v-for="position in positions"
                    :key="position.id"
                    class="rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] p-[var(--brand-space-16)]"
                >
                    <p class="font-medium text-[var(--brand-title)]">
                        {{ position.name }}
                        <span
                            class="font-mono text-xs text-[var(--brand-muted)]"
                            >{{ position.code }}</span
                        >
                    </p>
                    <el-checkbox-group
                        v-model="selections[position.code]"
                        class="mt-[var(--brand-space-16)] flex flex-wrap gap-x-[var(--brand-space-16)]"
                        ><el-checkbox
                            v-for="role in roles"
                            :key="role.code"
                            :label="role.code"
                            :value="role.code"
                            >{{ role.name }}</el-checkbox
                        ></el-checkbox-group
                    ><el-button
                        class="mt-[var(--brand-space-16)]"
                        size="small"
                        :loading="mapping.processing"
                        @click="saveRoles(position.code)"
                        >保存映射</el-button
                    >
                </div>
            </div>
            <p
                v-if="!positions.length"
                class="mt-[var(--brand-space-16)] text-sm text-[var(--brand-muted)]"
            >
                导入岗位后可设置审批角色。
            </p>
        </section>
        <section
            class="mt-[var(--brand-space-32)] rounded-[var(--brand-radius-md)] border border-[var(--brand-border)] bg-[var(--brand-card)] p-[var(--brand-space-24)] shadow-sm"
        >
            <h2 class="font-sans text-xl text-[var(--brand-title)]">
                已同步部门
            </h2>
            <div class="mt-[var(--brand-space-24)] overflow-x-auto">
                <table class="w-full min-w-[560px] text-left text-sm">
                    <thead
                        class="border-b border-[var(--brand-border)] text-xs text-[var(--brand-muted)]"
                    >
                        <tr>
                            <th class="py-[var(--brand-space-16)]">代码</th>
                            <th>名称</th>
                            <th>上级部门 ID</th>
                            <th>状态</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--brand-border)]">
                        <tr
                            v-for="department in departments"
                            :key="department.external_code"
                        >
                            <td class="py-[var(--brand-space-16)] font-mono">
                                {{ department.external_code }}
                            </td>
                            <td>{{ department.name }}</td>
                            <td>{{ department.parent_id ?? '—' }}</td>
                            <td>
                                {{ department.is_active ? '启用' : '停用' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</template>
