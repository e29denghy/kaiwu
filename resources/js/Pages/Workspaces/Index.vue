<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Components/AppLayout.vue';

defineProps({ workspaces: Array });
const form = useForm({ name: '', description: '', sort_order: 0 });
const submit = () => form.post('/workspaces', { preserveScroll: true, onSuccess: () => form.reset() });
</script>

<template>
    <AppLayout title="空间">
        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="font-semibold text-slate-950">新建空间</h2>
            <form class="mt-4 grid gap-3 md:grid-cols-[1fr_2fr_auto]" @submit.prevent="submit">
                <input v-model="form.name" class="rounded-md border border-slate-300 px-3 py-2" placeholder="例如：学习与职业" required>
                <input v-model="form.description" class="rounded-md border border-slate-300 px-3 py-2" placeholder="这个空间用来管理什么">
                <button class="rounded-md bg-teal-700 px-4 py-2 font-medium text-white hover:bg-teal-800" type="submit">创建空间</button>
            </form>
        </section>
        <section class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article v-for="workspace in workspaces" :key="workspace.id" class="rounded-lg border border-slate-200 bg-white p-5">
                <Link :href="`/workspaces/${workspace.id}`" class="font-semibold text-slate-950 hover:text-teal-700">{{ workspace.name }}</Link>
                <p class="mt-2 min-h-10 text-sm leading-5 text-slate-600">{{ workspace.description || '未填写说明。' }}</p>
                <p class="mt-3 text-sm text-slate-500">{{ workspace.projects_count }} 个项目 · {{ workspace.todos_count }} 项任务</p>
            </article>
        </section>
    </AppLayout>
</template>
