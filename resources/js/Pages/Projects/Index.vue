<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Components/AppLayout.vue';

defineProps({ projects: Array, workspaces: Array });

const form = useForm({
    workspace_id: '',
    name: '',
    description: '',
    priority: 'P2',
    sort_order: 100,
    status: 'active',
    due_at: '',
});

const submit = () => form.post('/projects', { preserveScroll: true, onSuccess: () => form.reset() });
</script>

<template>
    <AppLayout title="项目">
        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="font-semibold text-slate-950">新建项目</h2>
            <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="submit">
                <select v-model="form.workspace_id" class="rounded-md border border-slate-300 px-3 py-2" required><option value="" disabled>选择所属空间</option><option v-for="workspace in workspaces" :key="workspace.id" :value="workspace.id">{{ workspace.name }}</option></select>
                <input v-model="form.name" class="rounded-md border border-slate-300 px-3 py-2" placeholder="项目名称" required>
                <textarea v-model="form.description" class="rounded-md border border-slate-300 px-3 py-2 md:col-span-2" rows="2" placeholder="项目目标或背景" />
                <select v-model="form.priority" class="rounded-md border border-slate-300 px-3 py-2"><option>P0</option><option>P1</option><option>P2</option><option>P3</option></select>
                <input v-model.number="form.sort_order" type="number" min="0" max="9999" class="rounded-md border border-slate-300 px-3 py-2" placeholder="显示顺序（越小越靠前）">
                <button class="rounded-md bg-teal-700 px-4 py-2 font-medium text-white hover:bg-teal-800" type="submit">创建项目</button>
            </form>
        </section>
        <section class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article v-for="project in projects" :key="project.id" class="rounded-lg border border-slate-200 bg-white p-5">
                <div class="flex items-center justify-between gap-3"><div class="flex items-center gap-2"><span class="rounded bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600">{{ project.priority }}</span><span class="text-xs text-slate-400">顺序 {{ project.sort_order }}</span></div><span class="text-xs text-slate-500">{{ project.todos_count }} 项任务</span></div>
                <Link :href="`/projects/${project.id}`" class="mt-3 block font-semibold text-slate-950 hover:text-teal-700">{{ project.name }}</Link>
                <p class="mt-2 min-h-10 text-sm leading-5 text-slate-600">{{ project.description || '未填写项目说明。' }}</p>
                <p class="mt-3 text-sm text-slate-500">{{ project.workspace?.name }} · {{ project.status }}</p>
            </article>
        </section>
    </AppLayout>
</template>
