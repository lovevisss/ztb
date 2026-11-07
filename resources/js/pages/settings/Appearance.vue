<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: '界面外观',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="界面外观" />

    <h1 class="sr-only">界面外观</h1>

    <div class="space-y-[var(--brand-space-24)]">
        <Heading
            variant="small"
            title="界面外观"
            description="管理后台统一使用浅色界面，以保证数据与表单的清晰度。"
        />
    </div>
</template>
