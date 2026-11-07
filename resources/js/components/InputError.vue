<script setup lang="ts">
defineProps<{
    message?: string;
}>();
</script>

<template>
    <div v-show="message">
        <p class="text-sm text-[var(--brand-danger)]">
            {{ message }}
        </p>
    </div>
</template>
