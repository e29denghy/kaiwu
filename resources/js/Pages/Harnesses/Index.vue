<script setup>
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Components/AppLayout.vue';

defineProps({
    connections: Array,
    events: Array,
});

const form = useForm({
    name: '',
    driver: 'jsonl',
    inbox_path: '',
    outbox_path: '',
});

const createConnection = () => form.post('/harnesses', {
    preserveScroll: true,
    onSuccess: () => form.reset(),
});

const syncConnection = (connection) => router.post(`/harnesses/${connection.id}/sync`, {}, {
    preserveScroll: true,
});

const formatDate = (value) => value
    ? new Intl.DateTimeFormat('zh-CN', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
    : '尚未同步';
</script>

<template>
    <AppLayout title="Harness 连接">
        <div class="grid gap-6 xl:grid-cols-[22rem_minmax(0,1fr)]">
            <form class="h-fit rounded-lg border border-slate-200 bg-white p-5" @submit.prevent="createConnection">
                <h2 class="font-semibold text-slate-950">连接一个 Harness</h2>
                <p class="mt-1 text-sm leading-6 text-slate-500">JSONL Inbox 用于回流事件，Outbox 只接收已批准 Quest。</p>
                <label class="mt-4 block text-sm font-medium">名称<input v-model="form.name" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" placeholder="Codex Local" /></label>
                <label class="mt-3 block text-sm font-medium">Inbox JSONL<input v-model="form.inbox_path" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" placeholder="/absolute/path/events.jsonl" /></label>
                <label class="mt-3 block text-sm font-medium">Quest Outbox<input v-model="form.outbox_path" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" placeholder="/absolute/path/outbox" /></label>
                <button :disabled="form.processing" class="mt-4 rounded-md bg-teal-700 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">创建连接</button>
            </form>

            <section class="rounded-lg border border-slate-200 bg-white">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">连接</h2></div>
                <div v-if="connections.length" class="divide-y divide-slate-100">
                    <article v-for="connection in connections" :key="connection.id" class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div><h3 class="font-medium">{{ connection.name }}</h3><p class="mt-1 text-sm text-slate-500">{{ connection.driver }} · {{ connection.events_count }} 个事件 · {{ formatDate(connection.last_synced_at) }}</p></div>
                        <button class="rounded-md border border-teal-300 px-3 py-2 text-sm font-medium text-teal-700" @click="syncConnection(connection)">立即同步</button>
                    </article>
                </div>
                <p v-else class="px-5 py-10 text-sm text-slate-500">还没有 Harness 连接。</p>
            </section>
        </div>

        <section class="mt-6 overflow-hidden rounded-lg border border-slate-200 bg-white">
            <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold">归一化事件</h2></div>
            <div v-if="events.length" class="divide-y divide-slate-100">
                <article v-for="event in events" :key="event.id" class="px-5 py-4">
                    <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500"><span class="rounded bg-teal-50 px-2 py-1 font-medium text-teal-700">{{ event.connection?.name }}</span><span>{{ event.event_type }}</span><span>{{ event.status }}</span><span>{{ formatDate(event.occurred_at) }}</span></div>
                    <h3 class="mt-2 font-medium text-slate-950">{{ event.title }}</h3><p v-if="event.summary" class="mt-1 text-sm text-slate-600">{{ event.summary }}</p>
                </article>
            </div>
            <p v-else class="px-5 py-10 text-sm text-slate-500">Inbox 同步后，事件会显示在这里。</p>
        </section>
    </AppLayout>
</template>
