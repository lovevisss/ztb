<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { IconSearch, IconUserCircle } from '@tabler/icons-vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { BreadcrumbItem } from '@/types';

withDefaults(defineProps<{ breadcrumbs?: BreadcrumbItem[] }>(), {
    breadcrumbs: () => [],
});
const page = usePage();
const user = computed(() => page.props.auth.user);
const search = ref('');
</script>

<template>
    <header class="admin-topbar">
        <div class="admin-topbar-leading">
            <SidebarTrigger aria-label="切换导航菜单" />
            <Breadcrumbs v-if="breadcrumbs.length" :breadcrumbs="breadcrumbs" />
            <span v-else>采购管理平台</span>
        </div>
        <div class="admin-topbar-actions">
            <form
                action="/procurement/history"
                method="get"
                class="admin-topbar-search"
                role="search"
            >
                <IconSearch />
                <input
                    v-model="search"
                    name="search"
                    type="search"
                    placeholder="搜索采购项目"
                    aria-label="搜索采购项目"
                />
            </form>
            <Link
                href="/procurement/history"
                class="admin-topbar-search-icon"
                aria-label="搜索采购项目"
                ><IconSearch
            /></Link>
            <DropdownMenu>
                <DropdownMenuTrigger
                    class="admin-user-trigger"
                    aria-label="用户菜单"
                    ><IconUserCircle /><span>{{
                        user.name
                    }}</span></DropdownMenuTrigger
                >
                <DropdownMenuContent align="end" class="admin-user-menu"
                    ><UserMenuContent :user="user"
                /></DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>
</template>
