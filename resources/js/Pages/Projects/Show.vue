<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Components/AppLayout.vue';

const props = defineProps({ project: Object, workspaces: Array });
const form = useForm({
    workspace_id: props.project.workspace_id,
    name: props.project.name,
    description: props.project.description ?? '',
    priority: props.project.priority,
    sort_order: props.project.sort_order,
    status: props.project.status,
    due_at: props.project.due_at ? new Date(new Date(props.project.due_at).getTime() - new Date(props.project.due_at).getTimezoneOffset() * 60_000).toISOString().slice(0, 16) : '',
});

const update = () => form.put(`/projects/${props.project.id}`, { preserveScroll: true });
const remove = () => {
    if (window.confirm(`删除项目“${props.project.name}”？关联任务会保留，但不再关联此项目。`)) router.delete(`/projects/${props.project.id}`);
};
const statusLabel = (status) => ({ active: '进行中', paused: '已暂停', completed: '已完成', cancelled: '已取消' }[status] ?? status);
</script>

<template>
    <AppLayout :title="project.name">
        <div class="mb-5"><Link href="/projects" class="text-sm font-medium text-slate-600 hover:text-slate-950">返回项目列表</Link></div>
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <section class="rounded-lg border border-slate-200 bg-white">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-950">项目任务</h2></div>
                <div v-if="project.todos.length" class="divide-y divide-slate-100"><article v-for="todo in project.todos" :key="todo.id" class="px-5 py-4"><div class="flex flex-wrap items-center gap-2"><span class="rounded bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600">{{ todo.priority }}</span><span v-if="todo.module" class="rounded bg-cyan-50 px-2 py-1 text-xs font-medium text-cyan-700">{{ todo.module.name }}</span><span class="rounded bg-slate-100 px-2 py-1 text-xs text-slate-600">{{ todo.planning_state === 'archive' ? '历史记录' : todo.planning_state }}</span></div><Link :href="`/todos/${todo.id}`" class="mt-2 block font-medium text-slate-900 hover:text-teal-700">{{ todo.title }}</Link><p class="mt-1 text-sm text-slate-500">{{ todo.status }} · {{ todo.steps.length }} 个步骤</p></article></div>
                <p v-else class="px-5 py-10 text-center text-sm text-slate-500">项目还没有任务。请从任务页创建并关联到这里。</p>
            </section>
            <aside class="h-fit rounded-lg border border-slate-200 bg-white p-5">
                <div v-if="project.modules?.length" class="mb-5 border-b border-slate-200 pb-5">
                    <h2 class="font-semibold text-slate-950">项目模块</h2>
                    <div class="mt-3 space-y-2"><div v-for="module in project.modules" :key="module.id" class="rounded-md bg-slate-50 px-3 py-2 text-sm text-slate-700">{{ module.name }}</div></div>
                </div>
                <h2 class="font-semibold text-slate-950">编辑项目</h2>
                <form class="mt-4 space-y-3" @submit.prevent="update">
                    <input v-model="form.name" class="w-full rounded-md border border-slate-300 px-3 py-2" placeholder="项目名称" required>
                    <select v-model="form.workspace_id" class="w-full rounded-md border border-slate-300 px-3 py-2"><option v-for="workspace in workspaces" :key="workspace.id" :value="workspace.id">{{ workspace.name }}</option></select>
                    <textarea v-model="form.description" class="w-full rounded-md border border-slate-300 px-3 py-2" rows="4" placeholder="项目说明" />
                    <div class="grid grid-cols-2 gap-3"><select v-model="form.priority" class="rounded-md border border-slate-300 px-3 py-2"><option>P0</option><option>P1</option><option>P2</option><option>P3</option></select><select v-model="form.status" class="rounded-md border border-slate-300 px-3 py-2"><option value="active">进行中</option><option value="paused">已暂停</option><option value="completed">已完成</option><option value="cancelled">已取消</option></select></div>
                    <div><label class="mb-1 block text-sm font-medium text-slate-700">显示顺序</label><input v-model.number="form.sort_order" type="number" min="0" max="9999" class="w-full rounded-md border border-slate-300 px-3 py-2"><p class="mt-1 text-xs text-slate-500">数值越小，在首页和项目列表越靠前。</p></div>
                    <input v-model="form.due_at" type="datetime-local" class="w-full rounded-md border border-slate-300 px-3 py-2">
                    <button class="w-full rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800" type="submit">保存项目</button>
                    <button class="w-full rounded-md px-4 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50" type="button" @click="remove">删除项目</button>
                </form>
                <p class="mt-3 text-xs text-slate-500">当前状态：{{ statusLabel(project.status) }}</p>
            </aside>
        </div>
    </AppLayout>
</template>
