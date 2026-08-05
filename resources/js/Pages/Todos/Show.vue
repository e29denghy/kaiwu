<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../Components/AppLayout.vue';

const props = defineProps({
    todo: Object,
    workspaces: Array,
    projects: Array,
});

const toLocalInput = (value) => value ? new Date(value.getTime() - value.getTimezoneOffset() * 60_000).toISOString().slice(0, 16) : '';
const taskForm = useForm({
    workspace_id: props.todo.workspace_id,
    project_id: props.todo.project_id ?? '',
    title: props.todo.title,
    description: props.todo.description ?? '',
    priority: props.todo.priority,
    status: props.todo.status,
    planning_state: props.todo.planning_state ?? 'backlog',
    project_module_id: props.todo.project_module_id ?? '',
    due_at: props.todo.due_at ? toLocalInput(new Date(props.todo.due_at)) : '',
    scheduled_for: props.todo.scheduled_for ?? '',
    focus_rank: props.todo.focus_rank ?? '',
});
const planForm = useForm({
    planning_state: props.todo.planning_state ?? 'backlog',
    project_module_id: props.todo.project_module_id ?? '',
    scheduled_for: props.todo.scheduled_for ?? '',
    focus_rank: props.todo.focus_rank ?? '',
});
const stepForm = useForm({
    todo_id: props.todo.id,
    title: '',
    description: '',
    execution_type: 'Human',
    status: 'pending',
    sort_order: props.todo.steps.length + 1,
    requires_human_confirmation: true,
});

const projectsForWorkspace = computed(() => props.projects.filter((project) => project.workspace_id === Number(taskForm.workspace_id)));
const statusLabel = (status) => ({ pending: '待处理', in_progress: '进行中', waiting_confirmation: '等待确认', completed: '已完成', cancelled: '已取消' }[status] ?? status);
const executionLabel = (type) => ({ AI: 'AI 执行', Human: '人工执行', Hybrid: 'AI 辅助 + 人工确认' }[type] ?? type);
const formatDate = (value) => value ? new Intl.DateTimeFormat('zh-CN', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '未设置';
const planningLabel = (state) => ({ backlog: 'Backlog', next: '下一步', today: '今日聚焦', waiting: '等待中', archive: '历史记录' }[state] ?? state);

const updateTask = () => taskForm.put(`/todos/${props.todo.id}`, { preserveScroll: true });
const updatePlan = () => planForm.patch(`/todos/${props.todo.id}/plan`, { preserveScroll: true });
const decompose = () => router.post(`/todos/${props.todo.id}/decompose`, {}, { preserveScroll: true });
const updateTaskStatus = (status) => router.patch(`/todos/${props.todo.id}/status`, { status }, { preserveScroll: true });
const updateStepStatus = (step, status) => router.patch(`/todo-steps/${step.id}/status`, { status }, { preserveScroll: true });
const deleteStep = (step) => {
    if (window.confirm(`删除步骤“${step.title}”？`)) router.delete(`/todo-steps/${step.id}`, { preserveScroll: true });
};
const addStep = () => stepForm.post('/todo-steps', {
    preserveScroll: true,
    onSuccess: () => stepForm.reset('title', 'description'),
});
</script>

<template>
    <AppLayout :title="todo.title">
        <div class="mb-5 flex items-center justify-between">
            <Link href="/todos" class="text-sm font-medium text-slate-600 hover:text-slate-950">返回任务列表</Link>
            <div class="flex gap-2">
                <button v-if="todo.status !== 'completed'" class="rounded-md bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-800" type="button" @click="updateTaskStatus('completed')">完成任务</button>
                <button v-if="todo.status === 'completed'" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" type="button" @click="updateTaskStatus('in_progress')">重新打开</button>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="space-y-6">
                <section class="rounded-lg border border-slate-200 bg-white p-5">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-700">{{ todo.priority }}</span>
                        <span class="rounded bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700">{{ statusLabel(todo.status) }}</span>
                        <span class="text-sm text-slate-500">截止：{{ formatDate(todo.due_at) }}</span>
                        <span class="rounded bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600">{{ planningLabel(todo.planning_state) }}</span>
                        <span v-if="todo.module" class="rounded bg-cyan-50 px-2 py-1 text-xs font-medium text-cyan-700">{{ todo.module.name }}</span>
                    </div>
                    <p v-if="todo.description" class="mt-4 whitespace-pre-wrap text-sm leading-6 text-slate-700">{{ todo.description }}</p>
                    <p v-else class="mt-4 text-sm text-slate-400">没有补充说明。</p>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <button class="rounded-md bg-teal-700 px-3 py-2 text-sm font-medium text-white hover:bg-teal-800" type="button" @click="decompose">{{ todo.steps.length ? '重新执行 AI 拆解' : '执行 AI 拆解' }}</button>
                        <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" type="button" @click="updateTaskStatus('waiting_confirmation')">等待我确认</button>
                    </div>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white">
                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                        <div>
                            <h2 class="font-semibold text-slate-950">执行步骤</h2>
                            <p class="mt-1 text-sm text-slate-500">先处理可执行步骤，再由你做最终确认。</p>
                        </div>
                        <span class="text-sm text-slate-500">{{ todo.steps.length }} 项</span>
                    </div>
                    <div v-if="todo.steps.length" class="divide-y divide-slate-100">
                        <article v-for="step in todo.steps" :key="step.id" class="px-5 py-5">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700">{{ executionLabel(step.execution_type) }}</span>
                                        <span class="rounded bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600">{{ statusLabel(step.status) }}</span>
                                        <span v-if="step.requires_human_confirmation" class="text-xs text-amber-700">需人工确认</span>
                                    </div>
                                    <h3 class="mt-2 font-semibold text-slate-950">{{ step.sort_order }}. {{ step.title }}</h3>
                                    <p v-if="step.description" class="mt-1 text-sm leading-6 text-slate-600">{{ step.description }}</p>
                                </div>
                                <div class="flex shrink-0 flex-wrap gap-2">
                                    <button v-if="step.status === 'pending'" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium hover:bg-slate-50" type="button" @click="updateStepStatus(step, 'in_progress')">开始</button>
                                    <button v-if="step.status !== 'completed'" class="rounded-md bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-800" type="button" @click="updateStepStatus(step, 'completed')">完成</button>
                                    <button class="rounded-md px-2 py-2 text-sm text-slate-500 hover:bg-slate-100 hover:text-rose-700" type="button" @click="deleteStep(step)">删除</button>
                                </div>
                            </div>
                            <details v-if="step.ai_prompt" class="mt-4 rounded-md border border-slate-200 bg-slate-50">
                                <summary class="cursor-pointer px-3 py-2 text-sm font-medium text-slate-700">查看 Codex Prompt</summary>
                                <pre class="overflow-auto border-t border-slate-200 p-3 text-xs leading-5 text-slate-700">{{ step.ai_prompt }}</pre>
                            </details>
                        </article>
                    </div>
                    <p v-else class="px-5 py-10 text-center text-sm text-slate-500">还没有步骤。可以执行 AI 拆解，或手动添加步骤。</p>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white p-5">
                    <h2 class="font-semibold text-slate-950">手动添加步骤</h2>
                    <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="addStep">
                        <input v-model="stepForm.title" class="rounded-md border border-slate-300 px-3 py-2" placeholder="步骤名称" required>
                        <select v-model="stepForm.execution_type" class="rounded-md border border-slate-300 px-3 py-2"><option value="Human">人工执行</option><option value="AI">AI 执行</option><option value="Hybrid">AI 辅助 + 人工确认</option></select>
                        <textarea v-model="stepForm.description" class="rounded-md border border-slate-300 px-3 py-2 md:col-span-2" rows="2" placeholder="补充说明" />
                        <label class="flex items-center gap-2 text-sm text-slate-700"><input v-model="stepForm.requires_human_confirmation" type="checkbox"> 需要人工确认</label>
                        <button class="rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800" type="submit">添加步骤</button>
                    </form>
                </section>
            </div>

            <aside class="h-fit rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="font-semibold text-slate-950">编辑任务</h2>
                <form class="mt-4 space-y-3" @submit.prevent="updateTask">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">任务名称</label>
                        <input v-model="taskForm.title" class="w-full rounded-md border border-slate-300 px-3 py-2" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">空间</label>
                        <select v-model="taskForm.workspace_id" class="w-full rounded-md border border-slate-300 px-3 py-2" @change="taskForm.project_id = ''">
                            <option v-for="workspace in workspaces" :key="workspace.id" :value="workspace.id">{{ workspace.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">项目</label>
                        <select v-model="taskForm.project_id" class="w-full rounded-md border border-slate-300 px-3 py-2"><option value="">暂不关联项目</option><option v-for="project in projectsForWorkspace" :key="project.id" :value="project.id">{{ project.name }}</option></select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="mb-1 block text-sm font-medium text-slate-700">优先级</label><select v-model="taskForm.priority" class="w-full rounded-md border border-slate-300 px-3 py-2"><option>P0</option><option>P1</option><option>P2</option><option>P3</option></select></div>
                        <div><label class="mb-1 block text-sm font-medium text-slate-700">状态</label><select v-model="taskForm.status" class="w-full rounded-md border border-slate-300 px-3 py-2"><option value="pending">待处理</option><option value="in_progress">进行中</option><option value="waiting_confirmation">等待确认</option><option value="completed">已完成</option><option value="cancelled">已取消</option></select></div>
                    </div>
                    <div><label class="mb-1 block text-sm font-medium text-slate-700">截止时间</label><input v-model="taskForm.due_at" type="datetime-local" class="w-full rounded-md border border-slate-300 px-3 py-2"></div>
                    <div><label class="mb-1 block text-sm font-medium text-slate-700">说明</label><textarea v-model="taskForm.description" class="w-full rounded-md border border-slate-300 px-3 py-2" rows="4" /></div>
                    <button class="w-full rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50" :disabled="taskForm.processing" type="submit">保存任务</button>
                </form>
                <div class="mt-6 border-t border-slate-200 pt-5">
                    <h3 class="font-semibold text-slate-950">安排计划</h3>
                    <form class="mt-3 space-y-3" @submit.prevent="updatePlan">
                        <select v-model="planForm.project_module_id" class="w-full rounded-md border border-slate-300 px-3 py-2">
                            <option value="">暂不归类模块</option>
                            <option v-for="module in projects.find((project) => project.id === Number(taskForm.project_id))?.modules ?? []" :key="module.id" :value="module.id">{{ module.name }}</option>
                        </select>
                        <select v-model="planForm.planning_state" class="w-full rounded-md border border-slate-300 px-3 py-2"><option value="backlog">Backlog</option><option value="next">下一步</option><option value="today">今日聚焦</option><option value="waiting">等待中</option><option value="archive">历史记录</option></select>
                        <div class="grid grid-cols-2 gap-3"><input v-model="planForm.scheduled_for" type="date" class="rounded-md border border-slate-300 px-3 py-2"><input v-model.number="planForm.focus_rank" type="number" min="1" max="3" placeholder="聚焦序号" class="rounded-md border border-slate-300 px-3 py-2"></div>
                        <button class="w-full rounded-md bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800 disabled:opacity-50" :disabled="planForm.processing" type="submit">保存计划</button>
                    </form>
                </div>
            </aside>
        </div>
    </AppLayout>
</template>
