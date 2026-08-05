<script setup>
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Components/AppLayout.vue';

defineProps({ reminders: Object });

const formatDate = (value) => value ? new Intl.DateTimeFormat('zh-CN', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '未设置';
const complete = (reminder) => router.patch(`/reminders/${reminder.id}/complete`, {}, { preserveScroll: true });
const remove = (reminder) => {
    if (window.confirm(`删除提醒“${reminder.title}”？`)) router.delete(`/reminders/${reminder.id}`, { preserveScroll: true });
};
</script>

<template>
    <AppLayout title="提醒">
        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="font-semibold text-slate-950">系统提醒队列</h2>
                <p class="mt-1 text-sm text-slate-500">任务截止时间和需要人工确认的步骤会自动进入这里。</p>
            </div>
            <div v-if="reminders.data.length" class="divide-y divide-slate-100">
                <article v-for="reminder in reminders.data" :key="reminder.id" class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2"><span class="rounded px-2 py-1 text-xs font-semibold" :class="reminder.status === 'completed' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'">{{ reminder.status === 'completed' ? '已完成' : '待处理' }}</span><p class="font-medium text-slate-950">{{ reminder.title }}</p></div>
                        <p v-if="reminder.body" class="mt-2 text-sm text-slate-600">{{ reminder.body }}</p>
                        <p class="mt-2 text-sm text-slate-500">提醒时间：{{ formatDate(reminder.remind_at) }}<template v-if="reminder.todo"> · <Link :href="`/todos/${reminder.todo.id}`" class="hover:text-teal-700">{{ reminder.todo.title }}</Link></template></p>
                    </div>
                    <div class="flex shrink-0 gap-2"><button v-if="reminder.status !== 'completed'" class="rounded-md bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-800" type="button" @click="complete(reminder)">完成</button><button class="rounded-md px-3 py-2 text-sm text-slate-500 hover:bg-slate-100 hover:text-rose-700" type="button" @click="remove(reminder)">删除</button></div>
                </article>
            </div>
            <p v-else class="px-5 py-12 text-center text-sm text-slate-500">没有待处理提醒。</p>
        </section>
    </AppLayout>
</template>
