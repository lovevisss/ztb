<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { SidebarProvider } from '@/components/ui/sidebar';
import type { AppVariant } from '@/types';
import { computed } from 'vue';

type Props = {
    variant?: AppVariant;
};

withDefaults(defineProps<Props>(), {
    variant: 'sidebar',
});

const page = usePage();
const isOpen = page.props.sidebarOpen;
const isSettingsPage = computed(() => page.url.startsWith('/settings'));
</script>

<template>
    <div v-if="variant === 'header'" class="flex min-h-screen w-full flex-col">
        <slot />
    </div>
    <SidebarProvider v-else :default-open="isOpen" class="admin-app" :class="{ 'account-settings': isSettingsPage }">
        <slot />
    </SidebarProvider>
</template>
