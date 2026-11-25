<script setup>
import { syncLabels } from '../data/options'

defineProps({
    status: { type: String, default: 'local' },
})

const styles = {
    local: 'bg-stone-100 text-stone-700',
    pending: 'bg-amber-100 text-amber-950',
    syncing: 'bg-sky-100 text-sky-950',
    synced: 'bg-emerald-100 text-emerald-950',
    failed: 'bg-red-100 text-red-900',
    conflict: 'bg-orange-100 text-orange-950',
}
</script>

<template>
    <span class="inline-flex shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold" :class="styles[status] || styles.local">
        {{ syncLabels[status] || status }}
    </span>
</template>
