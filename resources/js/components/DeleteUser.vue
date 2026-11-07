<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { useTemplateRef } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

const passwordInput = useTemplateRef('passwordInput');
</script>

<template>
    <div class="space-y-[var(--brand-space-24)]">
        <Heading
            variant="small"
            title="注销账号"
            description="删除账号及其关联数据"
        />
        <div
            class="space-y-[var(--brand-space-16)] rounded-[var(--brand-radius-md)] border border-[var(--brand-danger)] bg-[var(--brand-card)] p-[var(--brand-space-16)]"
        >
            <div
                class="relative space-y-[var(--brand-space-8)] text-[var(--brand-danger)]"
            >
                <p class="font-medium">警告</p>
                <p class="text-sm">此操作无法撤销，请谨慎操作。</p>
            </div>
            <Dialog>
                <DialogTrigger as-child>
                    <Button variant="destructive" data-test="delete-user-button"
                        >注销账号</Button
                    >
                </DialogTrigger>
                <DialogContent>
                    <Form
                        v-bind="ProfileController.destroy.form()"
                        reset-on-success
                        @error="() => passwordInput?.focus()"
                        :options="{
                            preserveScroll: true,
                        }"
                        class="space-y-[var(--brand-space-24)]"
                        v-slot="{ errors, processing, reset, clearErrors }"
                    >
                        <DialogHeader class="space-y-[var(--brand-space-16)]">
                            <DialogTitle>确定要注销账号吗？</DialogTitle>
                            <DialogDescription>
                                账号及其关联数据将被永久删除。请输入密码确认。
                            </DialogDescription>
                        </DialogHeader>

                        <div class="grid gap-[var(--brand-space-8)]">
                            <Label for="password" class="sr-only">密码</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                ref="passwordInput"
                                placeholder="请输入密码"
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <DialogFooter class="gap-[var(--brand-space-8)]">
                            <DialogClose as-child>
                                <Button
                                    variant="secondary"
                                    @click="
                                        () => {
                                            clearErrors();
                                            reset();
                                        }
                                    "
                                >
                                    取消
                                </Button>
                            </DialogClose>

                            <Button
                                type="submit"
                                variant="destructive"
                                :disabled="processing"
                                data-test="confirm-delete-user-button"
                            >
                                确认注销
                            </Button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
