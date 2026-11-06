<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

type User = {
    id: number;
    name: string;
    email: string;
    department_name: string | null;
    school_account: string | null;
    is_active: boolean;
};
type PageLink = { url: string | null; label: string; active: boolean };
const props = defineProps<{
    users: { data: User[]; links: PageLink[]; total: number };
    filters: { search?: string };
}>();
const search = useForm({ search: props.filters.search ?? '' });
const mapping = useForm({ school_account: '', is_active: true });
const selected = ref<User | null>(null);

function select(user: User) {
    selected.value = user;
    mapping.school_account = user.school_account ?? '';
    mapping.is_active = user.is_active;
    mapping.clearErrors();
}
function save() {
    if (selected.value)
        mapping.put(`/admin/school-identities/${selected.value.id}`);
}
function find() {
    search.get('/admin/school-identities', { preserveState: true });
}
</script>

<template>
    <Head title="校内账号映射" />
    <main class="mx-auto max-w-6xl px-5 py-8 md:px-10 md:py-12">
        <header class="border-b border-slate-200 pb-7">
            <p class="text-xs font-semibold tracking-[0.25em] text-emerald-700">
                IDENTITY / 人员管理
            </p>
            <h1 class="mt-2 font-serif text-3xl font-semibold text-slate-900">
                校内账号映射
            </h1>
            <p class="mt-3 text-sm text-slate-500">
                将 CAS
                返回的校内账号精确关联到已有在职人员；一个账号只能对应一人。
            </p>
        </header>
        <form class="mt-7 flex max-w-lg gap-3" @submit.prevent="find">
            <el-input
                v-model="search.search"
                clearable
                placeholder="姓名、邮箱或校内账号"
            /><el-button native-type="submit" type="primary">搜索</el-button>
        </form>
        <div class="mt-7 grid gap-6 lg:grid-cols-[minmax(0,1fr)_330px]">
            <section
                class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm"
            >
                <table class="w-full min-w-[560px] text-left text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500">
                        <tr>
                            <th class="px-5 py-4">人员</th>
                            <th>部门</th>
                            <th>校内账号</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="user in users.data" :key="user.id">
                            <td class="px-5 py-4">
                                <strong>{{ user.name }}</strong>
                                <p class="text-xs text-slate-400">
                                    {{ user.email }}
                                </p>
                            </td>
                            <td>{{ user.department_name ?? '未关联' }}</td>
                            <td class="font-mono">
                                {{ user.school_account ?? '—' }}
                            </td>
                            <td>{{ user.is_active ? '在职' : '停用' }}</td>
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
                    class="px-5 py-12 text-center text-sm text-slate-400"
                >
                    没有匹配人员
                </p>
            </section>
            <aside
                class="h-fit rounded-lg border border-emerald-200 bg-emerald-50 p-6"
            >
                <h2 class="font-serif text-xl text-slate-900">
                    {{ selected ? `关联 ${selected.name}` : '选择人员' }}
                </h2>
                <template v-if="selected"
                    ><label class="mt-5 block text-sm text-slate-700"
                        >CAS 校内账号<el-input
                            v-model="mapping.school_account"
                            class="mt-2"
                            placeholder="与 CAS user 完全一致"
                    /></label>
                    <p
                        v-if="mapping.errors.school_account"
                        class="mt-1 text-xs text-red-700"
                    >
                        {{ mapping.errors.school_account }}
                    </p>
                    <label
                        class="mt-5 flex items-center gap-3 text-sm text-slate-700"
                        >在职可访问
                        <el-switch v-model="mapping.is_active" /></label
                    ><el-button
                        class="mt-6"
                        type="primary"
                        :loading="mapping.processing"
                        @click="save"
                        >保存映射</el-button
                    ></template
                >
                <p v-else class="mt-4 text-sm leading-7 text-slate-600">
                    从左侧选择人员后设置账号与在职状态。人员需要已关联部门，才能通过
                    CAS 登录。
                </p>
            </aside>
        </div>
        <nav v-if="users.links.length > 3" class="mt-6 flex gap-2">
            <Link
                v-for="(link, index) in users.links"
                :key="index"
                v-show="link.url"
                :href="link.url ?? '#'"
                class="rounded border px-3 py-1 text-sm"
                :class="
                    link.active
                        ? 'border-emerald-700 bg-emerald-50 text-emerald-800'
                        : 'border-slate-200 text-slate-600'
                "
                >{{
                    link.label.replace('&laquo;', '«').replace('&raquo;', '»')
                }}</Link
            >
        </nav>
    </main>
</template>
