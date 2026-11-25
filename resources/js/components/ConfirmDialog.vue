<script setup>
defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, required: true },
    message: { type: String, required: true },
    confirmLabel: { type: String, default: 'Confirm' },
    danger: { type: Boolean, default: false },
})
defineEmits(['confirm', 'cancel'])
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-4 sm:items-center">
        <div class="w-full max-w-md rounded-3xl bg-white p-5 shadow-xl" role="dialog" aria-modal="true">
            <h2 class="text-lg font-semibold">{{ title }}</h2>
            <p class="mt-2 text-sm leading-6 text-stone-700">{{ message }}</p>
            <div class="mt-5 grid grid-cols-2 gap-3">
                <button type="button" class="min-h-12 rounded-2xl border border-line font-semibold" @click="$emit('cancel')">
                    Cancel
                </button>
                <button
                    type="button"
                    class="min-h-12 rounded-2xl font-semibold text-white"
                    :class="danger ? 'bg-clay' : 'bg-leaf'"
                    @click="$emit('confirm')"
                >
                    {{ confirmLabel }}
                </button>
            </div>
        </div>
    </div>
</template>
