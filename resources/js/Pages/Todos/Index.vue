<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../Components/AppLayout.vue';

const props = defineProps({
    todos: Object,
    workspaces: Array,
    projects: Array,
    view: String,
    counts: Object,
});

const form = useForm({
    workspace_id: props.workspaces[0]?.id ?? '',
    project_id: '',
    title: '',
    description: '',
    priority: 'P2',
    status: 'pending',
    planning_state: 'backlog',
    project_module_id: '',
    due_at: '',
    scheduled_for: '',
    focus_rank: '',
});

const projectsForWorkspace = computed(() => props.projects.filter((project) => project.workspace_id === Number(form.workspace_id)));
const statusLabel = (status) => ({
    pending: '待处理',
    in_progress: '进行中',
    waiting_confirmation: '等待确认',
    completed: '已完成',
    cancelled: '已取消',
}[status] ?? status);
const planningLabel = (state) => ({
    backlog: 'Backlog',
    next: '下一步',
    today: '今日聚焦',
    waiting: '等待中',
    archive: '历史记录',
}[state] ?? state);
const formatDate = (value) => value ? new Intl.DateTimeFormat('zh-CN', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '未设置截止时间';
const localToday = () => {
    const date = new Date();
    return new Date(date.getTime() - date.getTimezoneOffset() * 60_000).toISOString().slice(0, 10);
};

const createTodo = () => form.post('/todos', {
    preserveScroll: true,
    onSuccess: () => form.reset('title', 'description', 'due_at'),
});

const decompose = (todo) => router.post(`/todos/${todo.id}/decompose`, {}, { preserveScroll: true });
const updateStatus = (todo, status) => router.patch(`/todos/${todo.id}/status`, { status }, { preserveScroll: true });
const scheduleToday = (todo) => router.patch(`/todos/${todo.id}/plan`, {
    planning_state: 'today',
    scheduled_for: localToday(),
    focus_rank: 3,
    project_module_id: todo.project_module_id,
}, { preserveScroll: true });
const tabs = [
    ['todo', '待办'],
    ['completed', '已完成'],
    ['confirmation', '待确认'],
    ['history', '历史记录'],
    ['all', '全部'],
];
</script>

<template>
    <AppLayout title="任务">
        <nav class="mb-5 flex flex-wrap gap-2" aria-label="任务筛选">
            <Link
                v-for="[key, label] in tabs"
                :key="key"
                :href="`/todos?view=${key}`"
                class="rounded-md border px-3 py-2 text-sm font-medium"
                :class="view === key ? 'border-teal-700 bg-teal-700 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'"
            >
                {{ label }} <span class="ml-1 opacity-75">{{ counts[key] }}</span>
            </Link>
        </nav>
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <div>
                        <h2 class="font-semibold text-slate-950">任务队列</h2>
                        <p class="mt-1 text-sm text-slate-500">按优先级和截止时间排序</p>
                    </div>
                    <span class="text-sm text-slate-500">{{ todos.total }} 项</span>
                </div>

                <div v-if="todos.data.length" class="divide-y divide-slate-100">
                    <article v-for="todo in todos.data" :key="todo.id" class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded px-2 py-1 text-xs font-semibold" :class="todo.priority === 'P0' ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-600'">{{ todo.priority }}</span>
                                <span class="rounded bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700">{{ statusLabel(todo.status) }}</span>
                                <span v-if="todo.steps.length" class="text-xs text-slate-500">{{ todo.steps.length }} 个步骤</span>
                                <span v-if="todo.memory_entry" class="rounded bg-violet-50 px-2 py-1 text-xs font-medium text-violet-700">项目记忆</span>
                                <span v-if="todo.module" class="rounded bg-cyan-50 px-2 py-1 text-xs font-medium text-cyan-700">{{ todo.module.name }}</span>
                                <span class="rounded bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600">{{ planningLabel(todo.planning_state) }}</span>
                            </div>
                            <Link :href="`/todos/${todo.id}`" class="mt-2 block font-semibold text-slate-950 hover:text-teal-700">{{ todo.title }}</Link>
                            <p class="mt-1 text-sm text-slate-500">{{ todo.project?.name || todo.workspace?.name }} · {{ formatDate(todo.due_at) }}</p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <button v-if="todo.status !== 'completed' && todo.status !== 'cancelled'" class="rounded-md bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-800" type="button" @click="updateStatus(todo, 'completed')">完成</button>
                            <button v-if="todo.status === 'completed'" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" type="button" @click="updateStatus(todo, 'in_progress')">重新打开</button>
                            <button v-if="todo.status !== 'completed' && todo.status !== 'cancelled' && todo.planning_state !== 'today' && todo.planning_state !== 'archive'" class="rounded-md border border-teal-300 px-3 py-2 text-sm font-medium text-teal-700 hover:bg-teal-50" type="button" @click="scheduleToday(todo)">安排今天</button>
                            <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" type="button" @click="decompose(todo)">
                                {{ todo.steps.length ? '重新拆解' : 'AI 拆解' }}
                            </button>
                            <Link :href="`/todos/${todo.id}`" class="rounded-md bg-slate-950 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">处理</Link>
                        </div>
                    </article>
                </div>
                <div v-else class="px-5 py-12 text-center text-sm text-slate-500">还没有任务。从右侧创建第一项。</div>
            </section>

            <aside class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="font-semibold text-slate-950">新建任务</h2>
                <form class="mt-4 space-y-3" @submit.prevent="createTodo">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700" for="workspace">所属空间</label>
                        <select id="workspace" v-model="form.workspace_id" class="w-full rounded-md border border-slate-300 px-3 py-2" required @change="form.project_id = ''; form.project_module_id = ''">
                            <option v-for="workspace in workspaces" :key="workspace.id" :value="workspace.id">{{ workspace.name }}</option>
                        </select>
                    </div>
                    <div v-if="form.project_id">
                        <label class="mb-1 block text-sm font-medium text-slate-700" for="module">模块</label>
                        <select id="module" v-model="form.project_module_id" class="w-full rounded-md border border-slate-300 px-3 py-2">
                            <option value="">暂不归类</option>
                            <option v-for="module in projectsForWorkspace.find((project) => project.id === Number(form.project_id))?.modules ?? []" :key="module.id" :value="module.id">{{ module.name }}</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="mb-1 block text-sm font-medium text-slate-700">计划层</label><select v-model="form.planning_state" class="w-full rounded-md border border-slate-300 px-3 py-2"><option value="backlog">Backlog</option><option value="next">下一步</option><option value="today">今日聚焦</option><option value="waiting">等待中</option></select></div>
                        <div><label class="mb-1 block text-sm font-medium text-slate-700">计划日期</label><input v-model="form.scheduled_for" type="date" class="w-full rounded-md border border-slate-300 px-3 py-2"></div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700" for="project">所属项目</label>
                        <select id="project" v-model="form.project_id" class="w-full rounded-md border border-slate-300 px-3 py-2" @change="form.project_module_id = ''">
                            <option value="">暂不关联项目</option>
                            <option v-for="project in projectsForWorkspace" :key="project.id" :value="project.id">{{ project.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700" for="title">任务名称</label>
                        <input id="title" v-model="form.title" class="w-full rounded-md border border-slate-300 px-3 py-2" placeholder="例如：完成 SDK 发布检查" required>
                        <p v-if="form.errors.title" class="mt-1 text-xs text-rose-600">{{ form.errors.title }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700" for="description">说明</label>
                        <textarea id="description" v-model="form.description" class="w-full rounded-md border border-slate-300 px-3 py-2" rows="3" placeholder="背景、期望结果或限制" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700" for="priority">优先级</label>
                            <select id="priority" v-model="form.priority" class="w-full rounded-md border border-slate-300 px-3 py-2"><option>P0</option><option>P1</option><option>P2</option><option>P3</option></select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700" for="due-at">截止时间</label>
                            <input id="due-at" v-model="form.due_at" type="datetime-local" class="w-full rounded-md border border-slate-300 px-3 py-2">
                        </div>
                    </div>
                    <button class="w-full rounded-md bg-teal-700 px-4 py-2 font-medium text-white hover:bg-teal-800 disabled:opacity-50" :disabled="form.processing" type="submit">创建任务</button>
                </form>
            </aside>
        </div>
    </AppLayout>
</template>
