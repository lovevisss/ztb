<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

type User = {
    id: number;
    name: string;
    email: string;
    department_name: string | null;
    school_account: string | null;
    is_active: boolean;
    manual_role_codes: string[];
    school_role_codes: string[];
};
type PageLink = { url: string | null; label: string; active: boolean };
type Role = { code: string; name: string };
type RoleEvent = { user_id: number; role_code: string; action: string; channel: string; actor_name: string | null; created_at: string };
const props = defineProps<{
    users: { data: User[]; links: PageLink[]; total: number };
    filters: { search?: string };
    roles: Role[];
    roleEvents: RoleEvent[];
}>();
const search = useForm({ search: props.filters.search ?? '' });
const mapping = useForm({ school_account: '', is_active: true });
const manualRoles = useForm({ manual_role_codes: [] as string[] });
const selected = ref<User | null>(null);
const selectedEvents = computed(() => props.roleEvents.filter((event) => event.user_id === selected.value?.id));
const roleName = (code: string) => props.roles.find((role) => role.code === code)?.name ?? code;

function select(user: User) {
    selected.value = user;
    mapping.school_account = user.school_account ?? '';
    mapping.is_active = user.is_active;
    mapping.clearErrors();
    manualRoles.manual_role_codes = [...user.manual_role_codes];
    manualRoles.clearErrors();
}
watch(() => props.users.data, (users) => {
    if (!selected.value) return;
    selected.value = users.find((user) => user.id === selected.value?.id) ?? null;
    if (selected.value) manualRoles.manual_role_codes = [...selected.value.manual_role_codes];
});
function save() {
    if (selected.value)
        mapping.put(`/admin/school-identities/${selected.value.id}`);
}
function find() {
    search.get('/admin/school-identities', { preserveState: true });
}
function saveRoles() {
    if (selected.value) manualRoles.put(`/admin/school-identities/${selected.value.id}/roles`, { preserveScroll: true });
}
</script>

<template>
    <Head title="校内账号映射" />
    <main
        class="mx-auto max-w-6xl px-[var(--brand-space-24)] py-[var(--brand-space-32)] md:px-[var(--brand-space-32)] md:py-[var(--brand-space-32)]"
    >
        <header
            class="border-b border-[var(--brand-border)] pb-[var(--brand-space-32)]"
        >
            <p
                class="text-xs font-semibold tracking-[0.25em] text-[var(--brand-primary)]"
            >
                IDENTITY / 人员管理
            </p>
            <h1
                class="mt-[var(--brand-space-8)] font-sans text-3xl font-semibold text-[var(--brand-title)]"
            >
                校内账号与角色管理
            </h1>
            <p
                class="mt-[var(--brand-space-16)] text-sm text-[var(--brand-muted)]"
            >
                精确关联 CAS 校内账号，并按人员维护手动角色。学校岗位继承的角色只读。
            </p>
        </header>
        <form
            class="mt-[var(--brand-space-32)] flex max-w-lg gap-[var(--brand-space-16)]"
            @submit.prevent="find"
        >
            <el-input
                v-model="search.search"
                clearable
                placeholder="姓名、邮箱或校内账号"
            /><el-button native-type="submit">搜索</el-button>
        </form>
        <div
            class="mt-[var(--brand-space-32)] grid gap-[var(--brand-space-24)] lg:grid-cols-[minmax(0,1fr)_330px]"
        >
            <section
                class="overflow-x-auto rounded-[var(--brand-radius-lg)] border border-[var(--brand-border)] bg-[var(--brand-card)] shadow-sm"
            >
                <table class="w-full min-w-[680px] text-left text-sm">
                    <thead
                        class="bg-[var(--brand-soft)] text-xs text-[var(--brand-muted)]"
                    >
                        <tr>
                            <th
                                class="px-[var(--brand-space-24)] py-[var(--brand-space-16)]"
                            >
                                人员
                            </th>
                            <th>部门</th>
                            <th>校内账号</th>
                            <th>状态</th>
                            <th>角色</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--brand-border)]">
                        <tr v-for="user in users.data" :key="user.id">
                            <td
                                class="px-[var(--brand-space-24)] py-[var(--brand-space-16)]"
                            >
                                <strong>{{ user.name }}</strong>
                                <p class="text-xs text-[var(--brand-muted)]">
                                    {{ user.email }}
                                </p>
                            </td>
                            <td>{{ user.department_name ?? '未关联' }}</td>
                            <td class="font-mono">
                                {{ user.school_account ?? '—' }}
                            </td>
                            <td>{{ user.is_active ? '在职' : '停用' }}</td>
                            <td class="max-w-40 text-xs text-[var(--brand-muted)]">{{ [...user.manual_role_codes, ...user.school_role_codes].map(roleName).join('、') || '未配置' }}</td>
                            <td>
                                <el-button size="small" @click="select(user)"
                                    >编辑</el-button
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p
                    v-if="!users.data.length"
                    class="px-[var(--brand-space-24)] py-[var(--brand-space-32)] text-center text-sm text-[var(--brand-muted)]"
                >
                    没有匹配人员
                </p>
            </section>
            <aside
                class="h-fit rounded-[var(--brand-radius-md)] border border-[var(--brand-primary)] bg-[var(--brand-primary-light)] p-[var(--brand-space-24)]"
            >
                <h2 class="font-sans text-xl text-[var(--brand-title)]">
                    {{ selected ? `关联 ${selected.name}` : '选择人员' }}
                </h2>
                <template v-if="selected">
                    <label
                        class="mt-[var(--brand-space-24)] block text-sm text-[var(--brand-body)]"
                        >CAS 校内账号<el-input
                            v-model="mapping.school_account"
                            class="mt-[var(--brand-space-8)]"
                            placeholder="与 CAS user 完全一致"
                    /></label>
                    <p
                        v-if="mapping.errors.school_account"
                        class="mt-[var(--brand-space-8)] text-xs text-[var(--brand-danger)]"
                    >
                        {{ mapping.errors.school_account }}
                    </p>
                    <label
                        class="mt-[var(--brand-space-24)] flex items-center gap-[var(--brand-space-16)] text-sm text-[var(--brand-body)]"
                        >在职可访问
                        <el-switch v-model="mapping.is_active" /></label
                    ><el-button
                        class="mt-[var(--brand-space-24)]"
                        type="primary"
                        :loading="mapping.processing"
                        @click="save"
                        >保存映射</el-button
                    >
                    <div class="mt-[var(--brand-space-32)] border-t border-[var(--brand-border)] pt-[var(--brand-space-24)]">
                        <h3 class="font-semibold text-[var(--brand-title)]">手动角色</h3>
                        <p class="mt-2 text-xs leading-5 text-[var(--brand-muted)]">仅调整当前人员；岗位继承角色由组织同步维护。</p>
                        <fieldset class="mt-4 space-y-3">
                            <legend class="sr-only">选择手动角色</legend>
                            <label v-for="role in roles" :key="role.code" class="flex items-center gap-3 text-sm text-[var(--brand-body)]" :class="{ 'opacity-60': selected.school_role_codes.includes(role.code) }">
                                <input v-model="manualRoles.manual_role_codes" type="checkbox" :value="role.code" :disabled="selected.school_role_codes.includes(role.code)" class="size-4 accent-[var(--brand-primary)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--brand-primary)]" />
                                <span>{{ role.name }}</span><span v-if="selected.school_role_codes.includes(role.code)" class="text-xs text-[var(--brand-muted)]">岗位继承</span>
                            </label>
                        </fieldset>
                        <p v-if="manualRoles.errors.manual_role_codes" class="mt-3 text-xs text-[var(--brand-danger)]">{{ manualRoles.errors.manual_role_codes }}</p>
                        <el-button class="mt-[var(--brand-space-24)]" type="primary" :loading="manualRoles.processing" @click="saveRoles">保存手动角色</el-button>
                    </div>
                    <div v-if="selectedEvents.length" class="mt-[var(--brand-space-32)] border-t border-[var(--brand-border)] pt-[var(--brand-space-24)]">
                        <h3 class="font-semibold text-[var(--brand-title)]">角色变更记录</h3>
                        <ul class="mt-3 space-y-2 text-xs leading-5 text-[var(--brand-muted)]">
                            <li v-for="(event, index) in selectedEvents" :key="index">
                                {{ event.created_at }} · {{ event.action === 'granted' ? '授予' : '撤销' }}{{ roleName(event.role_code) }} · {{ event.actor_name ?? event.channel.replace('console:', '控制台操作人：') }}
                            </li>
                        </ul>
                    </div>
                </template>
                <p
                    v-else
                    class="mt-[var(--brand-space-16)] text-sm leading-7 text-[var(--brand-body)]"
                >
                    从左侧选择人员后设置账号与在职状态。人员需要已关联部门，才能通过
                    CAS 登录。
                </p>
            </aside>
        </div>
        <nav
            v-if="users.links.length > 3"
            class="mt-[var(--brand-space-24)] flex gap-[var(--brand-space-8)]"
        >
            <Link
                v-for="(link, index) in users.links"
                :key="index"
                v-show="link.url"
                :href="link.url ?? '#'"
                class="rounded border px-[var(--brand-space-16)] py-[var(--brand-space-8)] text-sm"
                :class="
                    link.active
                        ? 'border-[var(--brand-primary)] bg-[var(--brand-primary-light)] text-[var(--brand-primary)]'
                        : 'border-[var(--brand-border)] text-[var(--brand-body)]'
                "
                >{{
                    link.label.replace('&laquo;', '«').replace('&raquo;', '»')
                }}</Link
            >
        </nav>
    </main>
</template>
