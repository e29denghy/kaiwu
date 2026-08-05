<script setup>
import { computed, reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Components/AppLayout.vue';

const props = defineProps({
    sources: Array,
    projects: Array,
    workspaces: Array,
    memoryConfig: Object,
    stats: Object,
});

const mappingSelections = reactive(Object.fromEntries(
    props.sources.map((source) => [source.id, source.project_id ?? '']),
));
const createDrafts = reactive(Object.fromEntries(
    props.sources.map((source) => [source.id, {
        workspace_id: props.workspaces[0]?.id ?? '',
        name: source.discovered_name,
        priority: 'P2',
    }]),
));

const pendingSources = computed(() => props.sources.filter((source) => source.status === 'pending'));
const linkedSources = computed(() => props.sources.filter((source) => source.status === 'linked'));
const ignoredSources = computed(() => props.sources.filter((source) => source.status === 'ignored'));

const syncMemory = () => router.post('/memory-sources/sync', {}, { preserveScroll: true });
const linkSource = (source) => {
    const projectId = mappingSelections[source.id];
    if (!projectId) return;
    router.patch(`/memory-sources/${source.id}`, { project_id: projectId }, { preserveScroll: true });
};
const createProject = (source) => {
    router.post(`/memory-sources/${source.id}/project`, createDrafts[source.id], { preserveScroll: true });
};
const ignoreSource = (source) => {
    if (window.confirm(`忽略“${source.scope_cwd}”？它之后不会生成任务。`)) {
        router.patch(`/memory-sources/${source.id}/ignore`, {}, { preserveScroll: true });
    }
};
const sourceStatus = (source) => source.status === 'linked'
    ? `已关联到 ${source.project?.name}`
    : source.status === 'ignored' ? '已忽略' : '等待关联';
</script>

<template>
    <AppLayout title="项目记忆同步">
        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h2 class="font-semibold text-slate-950">项目记忆知识库</h2>
                    <p class="mt-1 text-sm text-slate-600">以各项目 TODO.md 为状态来源，DevLog、Review 和工程 AGENTS.md 仅作参考；不读取 Codex 会话。</p>
                    <p class="mt-3 break-all font-mono text-xs text-slate-500">{{ memoryConfig.root }}</p>
                </div>
                <button
                    class="rounded-md bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-50"
                    type="button"
                    :disabled="!memoryConfig.root_exists"
                    @click="syncMemory"
                >
                    立即扫描记忆
                </button>
            </div>
            <p v-if="!memoryConfig.root_exists" class="mt-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                找不到项目记忆知识库，请检查 PROJECT_MEMORY_ROOT。
            </p>
        </section>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <section v-for="[value, label] in [
                [stats.pending_sources, '待关联路径'],
                [stats.linked_sources, '已关联路径'],
                [stats.current_entries, '当前记忆条目'],
                [stats.todo_entries, '待办条目'],
            ]" :key="label" class="rounded-lg border border-slate-200 bg-white p-5">
                <p class="text-sm font-medium text-slate-500">{{ label }}</p>
                <p class="mt-2 text-3xl font-semibold text-slate-950">{{ value }}</p>
            </section>
        </div>

        <section class="mt-6 overflow-hidden rounded-lg border border-amber-200 bg-white">
            <div class="border-b border-amber-100 bg-amber-50 px-5 py-4">
                <h2 class="font-semibold text-slate-950">等待你确认关联</h2>
                <p class="mt-1 text-sm text-slate-600">陌生路径不会自动创建项目。可以关联现有项目、明确新建，或忽略。</p>
            </div>
            <div v-if="pendingSources.length" class="divide-y divide-slate-100">
                <article v-for="source in pendingSources" :key="source.id" class="px-5 py-5">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                        <div class="min-w-0">
                            <h3 class="font-semibold text-slate-950">{{ source.discovered_name }}</h3>
                            <p class="mt-1 break-all font-mono text-xs leading-5 text-slate-500">工程：{{ source.scope_cwd }}</p>
                            <p class="mt-1 break-all font-mono text-xs leading-5 text-slate-500">任务记忆：{{ source.registry_path }}</p>
                            <p v-if="source.metadata?.agents_path" class="mt-1 break-all font-mono text-xs leading-5 text-slate-500">参考：{{ source.metadata.agents_path }}</p>
                            <p class="mt-2 text-sm text-slate-600">
                                {{ source.current_entries_count }} 条记忆 ·
                                {{ source.completed_entries_count }} 已完成 ·
                                {{ source.todo_entries_count }} 待办
                            </p>
                        </div>
                        <div class="w-full max-w-xl space-y-3">
                            <div class="flex gap-2">
                                <select v-model="mappingSelections[source.id]" class="min-w-0 flex-1 rounded-md border border-slate-300 px-3 py-2 text-sm">
                                    <option value="">选择现有项目</option>
                                    <option v-for="project in projects" :key="project.id" :value="project.id">
                                        {{ project.workspace?.name }} / {{ project.name }}
                                    </option>
                                </select>
                                <button class="rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white disabled:opacity-40" type="button" :disabled="!mappingSelections[source.id]" @click="linkSource(source)">确认关联</button>
                            </div>
                            <details class="rounded-md border border-slate-200 bg-slate-50">
                                <summary class="cursor-pointer px-3 py-2 text-sm font-medium text-slate-700">明确创建新项目</summary>
                                <form class="grid gap-2 border-t border-slate-200 p-3 sm:grid-cols-3" @submit.prevent="createProject(source)">
                                    <select v-model="createDrafts[source.id].workspace_id" class="rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                                        <option v-for="workspace in workspaces" :key="workspace.id" :value="workspace.id">{{ workspace.name }}</option>
                                    </select>
                                    <input v-model="createDrafts[source.id].name" class="rounded-md border border-slate-300 px-3 py-2 text-sm" required>
                                    <div class="flex gap-2">
                                        <select v-model="createDrafts[source.id].priority" class="rounded-md border border-slate-300 px-3 py-2 text-sm"><option>P0</option><option>P1</option><option>P2</option><option>P3</option></select>
                                        <button class="flex-1 rounded-md bg-teal-700 px-3 py-2 text-sm font-medium text-white" type="submit">创建并关联</button>
                                    </div>
                                </form>
                            </details>
                            <button class="text-sm font-medium text-slate-500 hover:text-rose-700" type="button" @click="ignoreSource(source)">这不是项目，忽略</button>
                        </div>
                    </div>
                </article>
            </div>
            <p v-else class="px-5 py-10 text-center text-sm text-slate-500">没有等待关联的路径。</p>
        </section>

        <section class="mt-6 overflow-hidden rounded-lg border border-slate-200 bg-white">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-950">已关联</h2></div>
            <div v-if="linkedSources.length" class="divide-y divide-slate-100">
                <article v-for="source in linkedSources" :key="source.id" class="flex flex-col gap-3 px-5 py-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="font-medium text-slate-900">{{ source.discovered_name }}</p>
                        <p class="mt-1 break-all font-mono text-xs text-slate-500">{{ source.registry_path }}</p>
                    </div>
                    <div class="text-sm text-slate-600">
                        <Link v-if="source.project" :href="`/projects/${source.project.id}`" class="font-medium text-teal-700 hover:text-teal-900">{{ sourceStatus(source) }}</Link>
                        <span class="ml-3">{{ source.current_entries_count }} 条记忆</span>
                    </div>
                </article>
            </div>
            <p v-else class="px-5 py-8 text-sm text-slate-500">还没有已关联路径。</p>
        </section>

        <details v-if="ignoredSources.length" class="mt-6 rounded-lg border border-slate-200 bg-white">
            <summary class="cursor-pointer px-5 py-4 font-semibold text-slate-700">已忽略路径（{{ ignoredSources.length }}）</summary>
            <div class="divide-y divide-slate-100 border-t border-slate-200">
                <p v-for="source in ignoredSources" :key="source.id" class="break-all px-5 py-3 font-mono text-xs text-slate-500">{{ source.scope_cwd }}</p>
            </div>
        </details>
    </AppLayout>
</template>
