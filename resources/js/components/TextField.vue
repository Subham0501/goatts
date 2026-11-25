<script setup>
defineProps({
    label: { type: String, required: true },
    modelValue: { type: [String, Number], default: '' },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    inputmode: { type: String, default: 'text' },
    autocomplete: { type: String, default: 'off' },
    type: { type: String, default: 'text' },
})
defineEmits(['update:modelValue', 'input'])
</script>

<template>
    <label class="block">
        <span class="mb-2 block text-sm font-semibold">{{ label }}</span>
        <input
            class="min-h-12 w-full rounded-2xl border bg-white px-4 outline-none focus:border-leaf"
            :class="error ? 'border-red-500' : 'border-line'"
            :type="type"
            :inputmode="inputmode"
            :autocomplete="autocomplete"
            :value="modelValue ?? ''"
            @input="$emit('update:modelValue', $event.target.value); $emit('input', $event)"
        >
        <span v-if="hint" class="mt-1 block text-sm text-stone-600">{{ hint }}</span>
        <span v-if="error" class="mt-1 block text-sm font-medium text-red-700">{{ error }}</span>
    </label>
</template>
