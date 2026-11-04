<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="暂无法访问采购系统" />
    <main class="mx-auto flex min-h-screen max-w-lg flex-col justify-center px-6 py-12 text-center">
        <p class="text-xs font-semibold tracking-[0.24em] text-emerald-700">ACCESS / 访问校验</p>
        <h1 class="mt-4 font-serif text-3xl text-slate-900">暂无法访问采购系统</h1>
        <p class="mt-5 text-sm leading-7 text-slate-600">校内账号尚未关联在职人员及所属部门。请联系系统管理员核对人员信息。</p>
        <Link href="/" class="mt-8 self-center rounded-md bg-emerald-800 px-5 py-3 text-sm font-medium text-white">返回首页</Link>
    </main>
</template>
