<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Components/AppLayout.vue';

const props = defineProps({ workspace: Object });
const form = useForm({ name: props.workspace.name, description: props.workspace.description ?? '', sort_order: props.workspace.sort_order });
const update = () => form.put(`/workspaces/${props.workspace.id}`, { preserveScroll: true });
const remove = () => {
    if (window.confirm(`删除空间“${props.workspace.name}”？其项目和任务也会被删除。`)) router.delete(`/workspaces/${props.workspace.id}`);
};
</script>

<template>
    <AppLayout :title="workspace.name">
        <div class="mb-5"><Link href="/workspaces" class="text-sm font-medium text-slate-600 hover:text-slate-950">返回空间列表</Link></div>
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <section class="rounded-lg border border-slate-200 bg-white">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-950">空间内项目</h2></div>
                <div v-if="workspace.projects.length" class="divide-y divide-slate-100"><article v-for="project in workspace.projects" :key="project.id" class="px-5 py-4"><Link :href="`/projects/${project.id}`" class="font-medium text-slate-900 hover:text-teal-700">{{ project.name }}</Link><p class="mt-1 text-sm text-slate-500">{{ project.priority }} · {{ project.status }} · {{ project.todos.length }} 项任务</p></article></div>
                <p v-else class="px-5 py-10 text-center text-sm text-slate-500">此空间还没有项目。</p>
            </section>
            <aside class="h-fit rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="font-semibold text-slate-950">编辑空间</h2>
                <form class="mt-4 space-y-3" @submit.prevent="update"><input v-model="form.name" class="w-full rounded-md border border-slate-300 px-3 py-2" required><textarea v-model="form.description" class="w-full rounded-md border border-slate-300 px-3 py-2" rows="4" placeholder="空间说明" /><input v-model.number="form.sort_order" type="number" min="0" class="w-full rounded-md border border-slate-300 px-3 py-2" placeholder="排序"><button class="w-full rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800" type="submit">保存空间</button><button class="w-full rounded-md px-4 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50" type="button" @click="remove">删除空间</button></form>
            </aside>
        </div>
    </AppLayout>
</template>
