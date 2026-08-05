<script setup>
import { reactive } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Components/AppLayout.vue';

const props = defineProps({ quests: Array, projects: Array, connections: Array });
const targets = reactive(Object.fromEntries(props.quests.map((quest) => [quest.id, props.connections[0]?.id ?? ''])));
const form = useForm({ project_id: '', title: '', goal: '', acceptance: '', constraints_text: '', verification_text: '', risk_level: 'medium', requires_write: true, execution_mode: 'immediate' });
const lines = (value) => value.split('\n').map((line) => line.trim()).filter(Boolean);
const createQuest = () => form.transform((data) => ({
    project_id: data.project_id || null,
    title: data.title,
    goal: data.goal,
    acceptance_criteria: lines(data.acceptance),
    constraints: lines(data.constraints_text),
    verification: lines(data.verification_text),
    risk_level: data.risk_level,
    requires_write: data.requires_write,
    execution_mode: data.execution_mode,
    scheduled_for: null,
})).post('/quests', { preserveScroll: true, onSuccess: () => form.reset() });
const approve = (quest) => router.patch(`/quests/${quest.id}/approve`, {}, { preserveScroll: true });
const dispatch = (quest) => router.post(`/quests/${quest.id}/dispatch`, { harness_connection_id: targets[quest.id] }, { preserveScroll: true });
const cancel = (quest) => router.patch(`/quests/${quest.id}/cancel`, {}, { preserveScroll: true });
</script>

<template>
    <AppLayout title="Quest 审批与派发">
        <div class="grid gap-6 xl:grid-cols-[24rem_minmax(0,1fr)]">
            <form class="h-fit rounded-lg border border-slate-200 bg-white p-5" @submit.prevent="createQuest">
                <h2 class="font-semibold">新建 Quest</h2><p class="mt-1 text-sm leading-6 text-slate-500">每行一个验收条件。创建后必须单独批准。</p>
                <label class="mt-4 block text-sm font-medium">项目<select v-model="form.project_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"><option value="">不关联项目</option><option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option></select></label>
                <label class="mt-3 block text-sm font-medium">标题<input v-model="form.title" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" /></label>
                <label class="mt-3 block text-sm font-medium">目标<textarea v-model="form.goal" required rows="3" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" /></label>
                <label class="mt-3 block text-sm font-medium">验收条件<textarea v-model="form.acceptance" required rows="3" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" /></label>
                <label class="mt-3 block text-sm font-medium">约束<textarea v-model="form.constraints_text" rows="2" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" /></label>
                <label class="mt-3 block text-sm font-medium">验证命令/方法<textarea v-model="form.verification_text" rows="2" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" /></label>
                <div class="mt-3 flex gap-3"><select v-model="form.risk_level" class="rounded-md border border-slate-300 px-3 py-2 text-sm"><option value="low">低风险</option><option value="medium">中风险</option><option value="high">高风险</option></select><label class="flex items-center gap-2 text-sm"><input v-model="form.requires_write" type="checkbox" />需要写入</label></div>
                <button :disabled="form.processing" class="mt-4 rounded-md bg-teal-700 px-4 py-2 text-sm font-medium text-white">创建并等待批准</button>
            </form>

            <section class="space-y-4">
                <article v-for="quest in quests" :key="quest.id" class="rounded-lg border border-slate-200 bg-white p-5">
                    <div class="flex flex-wrap items-center gap-2 text-xs"><span class="rounded bg-slate-100 px-2 py-1">{{ quest.status }}</span><span class="rounded bg-amber-50 px-2 py-1 text-amber-700">{{ quest.approval_status }}</span><span>{{ quest.risk_level }}</span><span>{{ quest.project?.name }}</span></div>
                    <h2 class="mt-3 text-lg font-semibold">{{ quest.title }}</h2><p class="mt-2 text-sm leading-6 text-slate-600">{{ quest.goal }}</p>
                    <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-slate-600"><li v-for="item in quest.acceptance_criteria" :key="item">{{ item }}</li></ul>
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <button v-if="quest.approval_status !== 'approved' && !['cancelled', 'completed'].includes(quest.status)" class="rounded-md bg-teal-700 px-3 py-2 text-sm font-medium text-white" @click="approve(quest)">人工批准</button>
                        <template v-if="quest.approval_status === 'approved' && ['approved', 'failed'].includes(quest.status)">
                            <select v-model="targets[quest.id]" class="rounded-md border border-slate-300 px-3 py-2 text-sm"><option disabled value="">选择 Harness</option><option v-for="connection in connections" :key="connection.id" :value="connection.id">{{ connection.name }}</option></select>
                            <button :disabled="!targets[quest.id]" class="rounded-md bg-slate-950 px-3 py-2 text-sm font-medium text-white disabled:opacity-40" @click="dispatch(quest)">写入 Outbox</button>
                        </template>
                        <button v-if="quest.status !== 'completed' && quest.status !== 'cancelled'" class="rounded-md border border-rose-200 px-3 py-2 text-sm text-rose-700" @click="cancel(quest)">取消</button>
                    </div>
                    <div v-if="quest.executions.length" class="mt-4 border-t border-slate-100 pt-3 text-xs text-slate-500"><p v-for="execution in quest.executions" :key="execution.id">尝试 {{ execution.attempt }} · {{ execution.status }} · {{ execution.connection?.name }} · {{ execution.dispatch_id }}</p></div>
                </article>
                <p v-if="!quests.length" class="rounded-lg border border-slate-200 bg-white px-5 py-10 text-sm text-slate-500">还没有 Quest。</p>
            </section>
        </div>
    </AppLayout>
</template>
