<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: '个人资料',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head title="个人资料" />

    <h1 class="sr-only">个人资料</h1>

    <div class="flex flex-col space-y-[var(--brand-space-24)]">
        <Heading
            variant="small"
            title="个人资料"
            description="更新姓名与电子邮箱"
        />

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-[var(--brand-space-24)]"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-[var(--brand-space-8)]">
                <Label for="name"
                    >姓名
                    <span class="text-[var(--brand-danger)]">*</span></Label
                >
                <Input
                    id="name"
                    class="mt-[var(--brand-space-8)] block w-full"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    placeholder="请输入姓名"
                />
                <InputError
                    class="mt-[var(--brand-space-8)]"
                    :message="errors.name"
                />
            </div>

            <div class="grid gap-[var(--brand-space-8)]">
                <Label for="email"
                    >电子邮箱
                    <span class="text-[var(--brand-danger)]">*</span></Label
                >
                <Input
                    id="email"
                    type="email"
                    class="mt-[var(--brand-space-8)] block w-full"
                    name="email"
                    :default-value="user.email"
                    required
                    autocomplete="username"
                    placeholder="请输入电子邮箱"
                />
                <InputError
                    class="mt-[var(--brand-space-8)]"
                    :message="errors.email"
                />
            </div>

            <div v-if="page.props.mustVerifyEmail && !user.email_verified_at">
                <p
                    class="-mt-[var(--brand-space-16)] text-sm text-muted-foreground"
                >
                    电子邮箱尚未验证。
                    <Link
                        :href="send()"
                        as="button"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current!"
                    >
                        点击重新发送验证邮件。
                    </Link>
                </p>

                <div
                    v-if="page.props.status === 'verification-link-sent'"
                    class="mt-[var(--brand-space-8)] text-sm font-medium text-[var(--brand-success)]"
                >
                    新的验证链接已发送至你的邮箱。
                </div>
            </div>

            <div class="flex items-center gap-[var(--brand-space-16)]">
                <Button :disabled="processing" data-test="update-profile-button"
                    >保存</Button
                >
            </div>
        </Form>
    </div>

    <DeleteUser />
</template>
