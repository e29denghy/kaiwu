<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    title: {
        type: String,
        default: '开物工作台',
    },
});

const page = usePage();
const successMessage = computed(() => page.props.flash?.success);
const errorMessage = computed(() => page.props.flash?.error);

const nav = [
    ['/', '工作台'],
    ['/quests', 'Quest'],
    ['/harnesses', 'Harness'],
    ['/todos', '任务'],
    ['/projects', '项目'],
    ['/memory-sources', '知识来源'],
    ['/workspaces', '空间'],
    ['/reminders', '提醒'],
];
</script>

<template>
    <main class="min-h-screen bg-slate-50 text-slate-900">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-7xl flex-col gap-4 px-5 py-5 md:flex-row md:items-center md:justify-between">
                <div>
                    <Link href="/" class="text-sm font-semibold tracking-[0.2em] text-teal-700">开物 · KAIWU</Link>
                    <h1 class="mt-1 text-2xl font-semibold tracking-normal text-slate-950">{{ title }}</h1>
                </div>
                <nav class="flex flex-wrap gap-1" aria-label="主导航">
                    <Link
                        v-for="[href, label] in nav"
                        :key="href"
                        :href="href"
                        class="rounded-md px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-950"
                    >
                        {{ label }}
                    </Link>
                </nav>
            </div>
        </header>

        <section class="mx-auto max-w-7xl px-5 py-6">
            <p v-if="successMessage" class="mb-5 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ successMessage }}
            </p>
            <p v-if="errorMessage" class="mb-5 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                {{ errorMessage }}
            </p>
            <slot />
        </section>
    </main>
</template>
