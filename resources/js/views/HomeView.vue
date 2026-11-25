<script setup>
import { useSessionStore } from '../stores/session'

const session = useSessionStore()

const topics = [
    {
        title: 'Farm visit',
        text: 'Record the ward, herd size, feeding system, and how the farm deworms its goats.',
        image: '/images/farm-visit.jpg',
        alt: 'Goats standing beside a wooden farm shelter',
    },
    {
        title: 'Each goat',
        text: 'Add age, sex, breed, body condition, and the last deworming for every sampled goat.',
        image: '/images/sampled-goat.jpg',
        alt: 'A goat standing in profile on a grassy hillside',
    },
    {
        title: 'Study records',
        text: 'Review the farms and goats, then download the farm and goat tables for analysis.',
        image: '/images/study-records.jpg',
        alt: 'A field notebook and blank record sheet on a wooden table',
    },
]
</script>

<template>
    <div class="min-h-dvh">
        <header class="sticky top-0 z-20 border-b border-line bg-paper/95 backdrop-blur">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-3 px-5 py-4">
                <RouterLink to="/" class="font-semibold text-leaf">Goat Health Survey</RouterLink>
                <RouterLink
                    :to="session.user ? '/dashboard' : '/login'"
                    class="inline-flex min-h-10 items-center rounded-xl bg-leaf px-4 text-sm font-semibold text-white"
                >
                    {{ session.user ? 'Dashboard' : 'Sign in' }}
                </RouterLink>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-5 py-8">
            <figure class="overflow-hidden rounded-3xl border border-line bg-white">
                <img
                    src="/images/hero-goats.jpg"
                    alt="A herd of goats on a terraced hillside in Lamjung"
                    class="h-64 w-full object-cover sm:h-96"
                >
            </figure>

            <div class="mt-8 grid items-end gap-8 lg:grid-cols-[minmax(0,1.4fr)_18rem]">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-leaf">Sundarbazar Municipality, Lamjung</p>
                    <h1 class="mt-3 max-w-2xl text-4xl font-semibold leading-tight tracking-tight">
                        Goat health and deworming survey
                    </h1>
                    <p class="mt-4 max-w-xl text-base leading-7 text-stone-700">
                        Field records of gastrointestinal nematodes in goats. Visit a farm once, then add each sampled goat to that farm.
                    </p>
                    <RouterLink
                        :to="session.user ? '/dashboard' : '/login'"
                        class="mt-6 flex min-h-14 max-w-sm items-center justify-center rounded-2xl bg-leaf text-base font-semibold text-white"
                    >
                        {{ session.user ? 'Open dashboard' : 'Sign in to start' }}
                    </RouterLink>
                    <p v-if="session.user" class="mt-3 text-sm text-stone-600">Signed in as {{ session.user.name }}</p>
                </div>
                <aside class="rounded-3xl border border-line bg-white p-5">
                    <p class="text-sm font-semibold text-leaf">What is collected</p>
                    <ul class="mt-3 grid gap-2 text-sm leading-6 text-stone-700">
                        <li>One farm record for the whole herd</li>
                        <li>A separate record for each sampled goat</li>
                        <li>Wards 1 to 11 of Sundarbazar</li>
                    </ul>
                </aside>
            </div>

            <div class="mt-12 grid gap-4 md:grid-cols-3">
                <article v-for="topic in topics" :key="topic.title" class="overflow-hidden rounded-3xl border border-line bg-white">
                    <img :src="topic.image" :alt="topic.alt" class="h-44 w-full object-cover">
                    <div class="p-5">
                        <h2 class="text-lg font-semibold">{{ topic.title }}</h2>
                        <p class="mt-2 text-sm leading-6 text-stone-600">{{ topic.text }}</p>
                    </div>
                </article>
            </div>
        </main>

        <footer class="border-t border-line">
            <p class="mx-auto max-w-5xl px-5 py-6 text-sm text-stone-600">
                Sundarbazar Municipality · Lamjung · Nepal
            </p>
        </footer>
    </div>
</template>
