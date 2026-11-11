<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    IconLayoutDashboard,
    IconClipboardList,
    IconAdjustments,
    IconListCheck,
    IconGitBranch,
    IconHistory,
    IconChartBar,
    IconArchive,
    IconUsers,
    IconHierarchy,
} from '@tabler/icons-vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: '工作台',
        href: dashboard(),
        icon: IconLayoutDashboard,
    },
    {
        title: '采购申请',
        href: '/procurement/applications',
        icon: IconClipboardList,
    },
    {
        title: '待我审批',
        href: '/procurement/approvals',
        icon: IconListCheck,
    },
    {
        title: '历史项目',
        href: '/procurement/history',
        icon: IconHistory,
    },
    {
        title: '采购统计',
        href: '/procurement/statistics',
        icon: IconChartBar,
    },
];

const canManageClassification =
    usePage().props.canManageClassification === true;

if (canManageClassification) {
    mainNavItems.push({
        title: '分类规则',
        href: '/procurement/classification-rules',
        icon: IconAdjustments,
    });
}

if (usePage().props.canManageApprovals === true) {
    mainNavItems.push({
        title: '流程进度',
        href: '/procurement/approval-progress',
        icon: IconGitBranch,
    });
    mainNavItems.push({
        title: '审批流程',
        href: '/procurement/approval-templates',
        icon: IconGitBranch,
    });
    mainNavItems.push({
        title: '归档规则',
        href: '/procurement/archive-rules',
        icon: IconArchive,
    });
}

if (usePage().props.canManageSchoolUsers === true) {
    mainNavItems.push({
        title: '校内账号',
        href: '/admin/school-identities',
        icon: IconUsers,
    });
    mainNavItems.push({
        title: '组织同步',
        href: '/admin/organization',
        icon: IconHierarchy,
    });
}
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar">
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
    </Sidebar>
    <slot />
</template>
