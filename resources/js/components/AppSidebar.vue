<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    FolderGit2,
    LayoutGrid,
    ClipboardList,
    SlidersHorizontal,
    ListChecks,
    Workflow,
    History,
    ChartColumn,
    Archive,
    Users,
    Network,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: '采购申请',
        href: '/procurement/applications',
        icon: ClipboardList,
    },
    {
        title: '待我审批',
        href: '/procurement/approvals',
        icon: ListChecks,
    },
    {
        title: '历史项目',
        href: '/procurement/history',
        icon: History,
    },
    {
        title: '采购统计',
        href: '/procurement/statistics',
        icon: ChartColumn,
    },
];

const canManageClassification =
    usePage().props.canManageClassification === true;

if (canManageClassification) {
    mainNavItems.push({
        title: '分类规则',
        href: '/procurement/classification-rules',
        icon: SlidersHorizontal,
    });
}

if (usePage().props.canManageApprovals === true) {
    mainNavItems.push({
        title: '审批流程',
        href: '/procurement/approval-templates',
        icon: Workflow,
    });
    mainNavItems.push({
        title: '归档规则',
        href: '/procurement/archive-rules',
        icon: Archive,
    });
}

if (usePage().props.canManageSchoolUsers === true) {
    mainNavItems.push({
        title: '校内账号',
        href: '/admin/school-identities',
        icon: Users,
    });
    mainNavItems.push({
        title: '组织同步',
        href: '/admin/organization',
        icon: Network,
    });
}

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
