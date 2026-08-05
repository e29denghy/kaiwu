<script setup>
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '../Components/AppLayout.vue';

const props = defineProps({
    stats: Object,
    activeProjects: Array,
    todayActiveProjects: Array,
    todayFocus: Array,
    todayWaiting: Array,
    projectTasks: Array,
    filters: Object,
    projectProgress: Array,
    aiExecutableSteps: Array,
    humanDecisionSteps: Array,
    upcomingReminders: Array,
});

const formatDate = (value) => value
    ? new Intl.DateTimeFormat('zh-CN', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
    : '未设置';
const progress = (project) => project.current_todos_count
    ? Math.round((project.completed_todos_count / project.current_todos_count) * 100)
    : 0;
const statusLabel = (status) => ({
    pending: '待处理',
    in_progress: '进行中',
    waiting_confirmation: '待确认',
    completed: '已完成',
}[status] ?? status);
const planningLabel = (state) => ({
    backlog: 'Backlog',
    next: '下一步',
    today: '今日聚焦',
    waiting: '等待中',
    archive: '历史记录',
}[state] ?? state);
const localToday = () => {
    const date = new Date();
    return new Date(date.getTime() - date.getTimezoneOffset() * 60_000).toISOString().slice(0, 10);
};
const scheduleToday = (todo) => router.patch(`/todos/${todo.id}/plan`, {
    planning_state: 'today',
    scheduled_for: localToday(),
    focus_rank: 3,
    project_module_id: todo.project_module_id,
}, { preserveScroll: true });
const taskDate = (todo) => {
    if (todo.memory_entry?.outcome === 'completed') {
        const label = todo.memory_entry.evidence?.activity_date_source === 'file' ? '知识库更新' : '完成日期';
        return [label, todo.memory_entry.source_updated_at];
    }
    if (todo.status === 'completed') return ['完成于', todo.completed_at ?? todo.activity_at];
    if (todo.due_at) return ['截止', todo.due_at];
    if (todo.memory_entry?.source_updated_at) {
        const label = todo.memory_entry.evidence?.activity_date_source === 'file' ? '知识库更新' : '记忆日期';
        return [label, todo.memory_entry.source_updated_at];
    }
    return ['更新于', todo.updated_at];
};
const filterHref = (patch) => {
    const next = {
        project_id: props.filters.project_id,
        period: props.filters.period,
        status: props.filters.status,
        ...patch,
    };
    const params = new URLSearchParams();

    if (next.project_id) params.set('project', next.project_id);
    params.set('period', next.period);
    params.set('status', next.status);

    return `/?${params.toString()}`;
};
const cards = [
    ['today_focus', '今日聚焦'],
    ['today_focus_completed', '今日聚焦完成'],
    ['today_waiting', '等待我决策'],
    ['open_tasks', '当前待办'],
];
const periods = [
    ['today', '今天'],
    ['7d', '近 7 天'],
    ['30d', '近 30 天'],
    ['all', '全部日期'],
];
const statuses = [
    ['all', '全部状态'],
    ['todo', '待办'],
    ['completed', '已完成'],
];
</script>

<template>
    <AppLayout title="今日工作台">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <section v-for="[key, label] in cards" :key="key" class="rounded-lg border border-slate-200 bg-white p-5">
                <p class="text-sm font-medium text-slate-500">{{ label }}</p>
                <p class="mt-2 text-3xl font-semibold text-slate-950">{{ stats[key] }}</p>
            </section>
        </div>

        <section class="mt-6 overflow-hidden rounded-lg border border-teal-200 bg-teal-50/50">
            <div class="flex flex-col gap-2 border-b border-teal-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-semibold text-slate-950">今天只看这里</h2>
                    <p class="mt-1 text-sm text-slate-600">每天最多承诺 3 项；知识库里的历史活动不会自动占用今日计划。</p>
                </div>
                <Link href="/todos?view=todo" class="text-sm font-medium text-teal-700 hover:text-teal-900">去下一步列表</Link>
            </div>
            <div v-if="todayFocus.length" class="grid gap-3 p-5 md:grid-cols-3">
                <article v-for="todo in todayFocus" :key="todo.id" class="rounded-lg border border-white bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <span class="rounded bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-700">{{ todo.priority }}</span>
                        <span class="text-xs text-slate-400">#{{ todo.focus_rank ?? '—' }}</span>
                    </div>
                    <Link :href="`/todos/${todo.id}`" class="mt-3 block font-semibold leading-6 text-slate-950 hover:text-teal-700">{{ todo.title }}</Link>
                    <p class="mt-2 text-xs text-slate-500">{{ todo.project?.name }}<span v-if="todo.module"> · {{ todo.module.name }}</span></p>
                    <p class="mt-2 text-xs text-teal-700">{{ planningLabel(todo.planning_state) }} · {{ todo.status === 'in_progress' ? '进行中' : '待处理' }}</p>
                </article>
            </div>
            <p v-else class="px-5 py-8 text-center text-sm text-slate-600">今天还没有主动承诺的任务。请从任务详情或下面的列表选择 1–3 项。</p>
        </section>

        <section class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="font-semibold text-slate-950">跨 Harness 协作</h2>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Harness 事件先归一化进入开物；任务被拆成 Quest 后必须人工批准，才会写入目标 Harness 的 Outbox。执行结果以追加记录回流，不覆盖历史尝试。</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <Link href="/harnesses" class="rounded-md bg-slate-950 px-3 py-2 text-sm font-medium text-white">管理 Harness</Link>
                    <Link href="/quests" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700">查看 Quest</Link>
                </div>
            </div>
            <div class="rounded-lg border border-amber-200 bg-amber-50/60 p-5">
                <h2 class="font-semibold text-slate-950">等待我决策</h2>
                <div v-if="todayWaiting.length" class="mt-3 space-y-3">
                    <Link v-for="todo in todayWaiting" :key="todo.id" :href="`/todos/${todo.id}`" class="block rounded-md bg-white p-3 text-sm font-medium text-slate-900 hover:text-teal-700">
                        {{ todo.title }}<span class="mt-1 block text-xs font-normal text-slate-500">{{ todo.project?.name }}<span v-if="todo.module"> · {{ todo.module.name }}</span></span>
                    </Link>
                </div>
                <p v-else class="mt-3 text-sm text-slate-600">暂无需要决策的任务。</p>
            </div>
        </section>

        <section class="mt-6 overflow-hidden rounded-lg border border-slate-200 bg-white">
            <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-semibold text-slate-950">知识库活动</h2>
                    <p class="mt-1 text-sm text-slate-500">这是记忆来源里的近期活动，不等于今天的执行承诺。</p>
                </div>
                <span class="text-sm text-slate-500">{{ todayActiveProjects.length }} 个项目</span>
            </div>
            <div v-if="todayActiveProjects.length" class="grid gap-3 p-5 sm:grid-cols-2 xl:grid-cols-4">
                <Link
                    v-for="project in todayActiveProjects"
                    :key="project.id"
                    :href="filterHref({ project_id: project.id, period: 'today', status: 'all' })"
                    class="rounded-lg border border-teal-100 bg-teal-50/60 p-4 transition hover:border-teal-300 hover:bg-teal-50"
                >
                    <div class="flex items-center justify-between gap-3">
                        <span class="rounded bg-white px-2 py-1 text-xs font-semibold text-teal-700">{{ project.priority }}</span>
                        <span class="text-xs text-slate-500">顺序 {{ project.sort_order }}</span>
                    </div>
                    <h3 class="mt-3 font-semibold text-slate-950">{{ project.name }}</h3>
                    <p class="mt-2 text-sm text-slate-600">
                        今日 {{ project.today_active_todos_count }} 项 ·
                        完成 {{ project.today_completed_todos_count }} 项
                    </p>
                </Link>
            </div>
            <p v-else class="px-5 py-10 text-center text-sm text-slate-500">今天还没有项目活动。</p>
        </section>

        <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                <div class="border-b border-slate-200 px-5 py-4">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="font-semibold text-slate-950">项目任务</h2>
                            <p class="mt-1 text-sm text-slate-500">先选择项目，再按日期、状态和执行承诺缩小范围。</p>
                        </div>
                        <span class="text-sm text-slate-500">显示 {{ projectTasks.length }} 项</span>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <Link
                            :href="filterHref({ project_id: null })"
                            class="rounded-full border px-3 py-1.5 text-sm font-medium"
                            :class="filters.project_id === null ? 'border-slate-950 bg-slate-950 text-white' : 'border-slate-300 text-slate-600 hover:bg-slate-50'"
                        >全部项目</Link>
                        <Link
                            v-for="project in activeProjects"
                            :key="project.id"
                            :href="filterHref({ project_id: project.id })"
                            class="rounded-full border px-3 py-1.5 text-sm font-medium"
                            :class="filters.project_id === project.id ? 'border-slate-950 bg-slate-950 text-white' : 'border-slate-300 text-slate-600 hover:bg-slate-50'"
                        >
                            {{ project.name }}
                            <span class="ml-1 opacity-65">{{ project.open_todos_count }}/{{ project.current_todos_count }}</span>
                        </Link>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <span class="mr-1 text-xs font-medium uppercase tracking-wide text-slate-400">日期</span>
                        <Link
                            v-for="[key, label] in periods"
                            :key="key"
                            :href="filterHref({ period: key })"
                            class="rounded-md px-2.5 py-1.5 text-sm"
                            :class="filters.period === key ? 'bg-teal-700 font-medium text-white' : 'text-slate-600 hover:bg-slate-100'"
                        >{{ label }}</Link>
                        <span class="ml-2 mr-1 text-xs font-medium uppercase tracking-wide text-slate-400">状态</span>
                        <Link
                            v-for="[key, label] in statuses"
                            :key="key"
                            :href="filterHref({ status: key })"
                            class="rounded-md px-2.5 py-1.5 text-sm"
                            :class="filters.status === key ? 'bg-teal-700 font-medium text-white' : 'text-slate-600 hover:bg-slate-100'"
                        >{{ label }}</Link>
                    </div>
                </div>

                <div v-if="projectTasks.length" class="divide-y divide-slate-100">
                    <article v-for="todo in projectTasks" :key="todo.id" class="px-5 py-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded px-2 py-1 text-xs font-semibold" :class="todo.priority === 'P0' ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-600'">{{ todo.priority }}</span>
                                    <span class="rounded bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700">{{ statusLabel(todo.status) }}</span>
                                    <span class="text-xs text-slate-500">{{ todo.project?.name || todo.workspace?.name }}</span>
                                    <span v-if="todo.module" class="rounded bg-cyan-50 px-2 py-1 text-xs font-medium text-cyan-700">{{ todo.module.name }}</span>
                                </div>
                                <Link :href="`/todos/${todo.id}`" class="mt-2 block font-semibold leading-6 text-slate-950 hover:text-teal-700">{{ todo.title }}</Link>
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-2">
                                <p class="text-xs text-slate-500">{{ taskDate(todo)[0] }} · {{ formatDate(taskDate(todo)[1]) }}</p>
                                <button v-if="todo.status !== 'completed' && todo.planning_state !== 'today'" class="rounded-md border border-teal-300 px-2 py-1 text-xs font-medium text-teal-700 hover:bg-teal-50" type="button" @click="scheduleToday(todo)">安排今天</button>
                            </div>
                        </div>
                    </article>
                </div>
                <p v-else class="px-5 py-12 text-center text-sm text-slate-500">这个项目和日期范围内没有任务。</p>
            </section>

            <section class="h-fit rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="font-semibold text-slate-950">项目进度</h2>
                <p class="mt-1 text-sm text-slate-500">顺序与首页项目筛选一致。</p>
                <div v-if="projectProgress.length" class="mt-4 space-y-4">
                    <article v-for="project in projectProgress" :key="project.id">
                        <div class="flex items-center justify-between gap-3">
                            <p class="truncate text-sm font-medium text-slate-800">{{ project.name }}</p>
                            <span class="text-sm text-slate-500">{{ progress(project) }}%</span>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded bg-slate-100"><div class="h-full bg-teal-600" :style="{ width: `${progress(project)}%` }" /></div>
                    </article>
                </div>
                <p v-else class="mt-4 text-sm text-slate-500">暂无进行中的项目。</p>
            </section>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <section class="rounded-lg border border-slate-200 bg-white">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-950">AI 可执行</h2></div>
                <div v-if="aiExecutableSteps.length" class="divide-y divide-slate-100"><article v-for="step in aiExecutableSteps" :key="step.id" class="px-5 py-4"><Link :href="`/todos/${step.todo_id}`" class="font-medium text-slate-900 hover:text-teal-700">{{ step.title }}</Link><p class="mt-1 text-sm text-slate-500">{{ step.todo?.title }}</p></article></div>
                <p v-else class="px-5 py-8 text-sm text-slate-500">没有待执行的 AI 步骤。</p>
            </section>
            <section class="rounded-lg border border-slate-200 bg-white">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-950">需要我决策</h2></div>
                <div v-if="humanDecisionSteps.length" class="divide-y divide-slate-100"><article v-for="step in humanDecisionSteps" :key="step.id" class="px-5 py-4"><Link :href="`/todos/${step.todo_id}`" class="font-medium text-slate-900 hover:text-teal-700">{{ step.title }}</Link><p class="mt-1 text-sm text-slate-500">{{ step.todo?.title }}</p></article></div>
                <p v-else class="px-5 py-8 text-sm text-slate-500">没有待确认的步骤。</p>
            </section>
            <section class="rounded-lg border border-slate-200 bg-white">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-950">系统提醒</h2><Link href="/reminders" class="text-sm font-medium text-teal-700">查看全部</Link></div>
                <div v-if="upcomingReminders.length" class="divide-y divide-slate-100"><article v-for="reminder in upcomingReminders" :key="reminder.id" class="px-5 py-4"><p class="font-medium text-slate-900">{{ reminder.title }}</p><p class="mt-1 text-sm text-slate-500">{{ formatDate(reminder.remind_at) }}</p></article></div>
                <p v-else class="px-5 py-8 text-sm text-slate-500">没有待处理提醒。</p>
            </section>
        </div>
    </AppLayout>
</template>
